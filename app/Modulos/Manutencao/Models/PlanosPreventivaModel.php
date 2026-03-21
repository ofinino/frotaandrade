<?php
namespace App\Modulos\Manutencao\Models;

class PlanosPreventivaModel
{
    private \PDO $db;
    private int $empresaId;
    private ?int $filialId;
    private array $filiais;
    private array $filialCols = [];

    public function __construct(\PDO $db, int $empresaId, ?int $filialId, array $filiais = [])
    {
        $this->db = $db;
        $this->empresaId = $empresaId;
        $this->filialId = $filialId;
        $this->filiais = $filiais;
    }

    private function detectarFilialColuna(string $table): ?string
    {
        if (isset($this->filialCols[$table])) {
            return $this->filialCols[$table];
        }
        $stmt = $this->db->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME IN ('filial_id','branch_id','filial') LIMIT 1"
        );
        $stmt->execute([$table]);
        $col = $stmt->fetchColumn() ?: null;
        $this->filialCols[$table] = $col;
        return $col;
    }

    private function filialWhere(string $alias = 'p', string $table = 'man_maintenance_plans'): array
    {
        $col = $this->detectarFilialColuna($table);
        if (!$col) {
            return ['', []];
        }
        $ids = $this->filiais;
        if (empty($ids) && $this->filialId) {
            $ids = [$this->filialId];
        }
        if (!$ids) {
            return ['', []];
        }
        $place = implode(',', array_fill(0, count($ids), '?'));
        $prefix = $alias ? "{$alias}." : '';
        return [" AND ({$prefix}`$col` IS NULL OR {$prefix}`$col` IN ($place))", $ids];
    }

    private function detectarOdometroColuna(): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cad_veiculos' AND COLUMN_NAME IN ('odometro_atual','odometro','km_atual') LIMIT 1"
        );
        $stmt->execute();
        $col = $stmt->fetchColumn();
        return $col ?: null;
    }

    public function listarPlanos(array $filters = []): array
    {
        $sql = 'SELECT p.*, v.plate AS vehicle_plate FROM man_maintenance_plans p
                LEFT JOIN cad_veiculos v ON v.id = p.veiculo_id
                WHERE p.empresa_id = ?';
        $params = [$this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere('p');
        $sql .= $filialSql;
        $params = array_merge($params, $filialParams);
        if (isset($filters['ativo'])) {
            $sql .= ' AND p.ativo = ?';
            $params[] = (int)$filters['ativo'];
        }
        if (!empty($filters['q'])) {
            $like = '%' . $filters['q'] . '%';
            $sql .= ' AND (p.nome LIKE ? OR p.descricao LIKE ? OR v.plate LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= ' ORDER BY p.created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obterPlano(int $id): ?array
    {
        $sql = 'SELECT p.*, v.plate AS vehicle_plate FROM man_maintenance_plans p
                LEFT JOIN cad_veiculos v ON v.id = p.veiculo_id
                WHERE p.id = ? AND p.empresa_id = ?';
        $params = [$id, $this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere('p');
        $sql .= $filialSql;
        $params = array_merge($params, $filialParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['tasks'] = $this->listarTarefas($id);
        return $row;
    }

    public function salvarPlano(array $data, ?int $id = null): int
    {
        if ($id) {
            $stmt = $this->db->prepare(
                'UPDATE man_maintenance_plans SET nome = ?, descricao = ?, veiculo_id = ?, tipo = ?, km_intervalo = ?, dias_intervalo = ?, due_soon_km = ?, due_soon_dias = ?, ativo = ?, updated_at = NOW()
                 WHERE id = ? AND empresa_id = ?'
            );
            $stmt->execute([
                $data['nome'],
                $data['descricao'] ?? null,
                $data['veiculo_id'] ?? null,
                $data['tipo'] ?? 'km_tempo',
                $data['km_intervalo'] ?? null,
                $data['dias_intervalo'] ?? null,
                $data['due_soon_km'] ?? 0,
                $data['due_soon_dias'] ?? 0,
                $data['ativo'] ?? 1,
                $id,
                $this->empresaId,
            ]);
            return $id;
        }
        $stmt = $this->db->prepare(
            'INSERT INTO man_maintenance_plans (empresa_id, filial_id, nome, descricao, veiculo_id, tipo, km_intervalo, dias_intervalo, due_soon_km, due_soon_dias, ativo, criado_por, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $this->empresaId,
            $this->filialId,
            $data['nome'],
            $data['descricao'] ?? null,
            $data['veiculo_id'] ?? null,
            $data['tipo'] ?? 'km_tempo',
            $data['km_intervalo'] ?? null,
            $data['dias_intervalo'] ?? null,
            $data['due_soon_km'] ?? 0,
            $data['due_soon_dias'] ?? 0,
            $data['ativo'] ?? 1,
            $data['criado_por'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function listarTarefas(int $planId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM man_maintenance_tasks WHERE plan_id = ? ORDER BY created_at DESC');
        $stmt->execute([$planId]);
        return $stmt->fetchAll();
    }

    public function salvarTarefas(int $planId, array $tasks): void
    {
        $idsMantidos = [];
        foreach ($tasks as $task) {
            $nome = trim($task['nome'] ?? '');
            if ($nome === '') {
                continue;
            }
            $taskId = isset($task['id']) ? (int)$task['id'] : 0;
            if ($taskId) {
                $stmt = $this->db->prepare(
                    'UPDATE man_maintenance_tasks SET nome = ?, descricao = ?, km_intervalo = ?, dias_intervalo = ?, updated_at = NOW()
                     WHERE id = ? AND plan_id = ?'
                );
                $stmt->execute([
                    $nome,
                    $task['descricao'] ?? null,
                    $task['km_intervalo'] ?? null,
                    $task['dias_intervalo'] ?? null,
                    $taskId,
                    $planId,
                ]);
                $idsMantidos[] = $taskId;
            } else {
                $stmt = $this->db->prepare(
                    'INSERT INTO man_maintenance_tasks (empresa_id, filial_id, plan_id, nome, descricao, km_intervalo, dias_intervalo, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute([
                    $this->empresaId,
                    $this->filialId,
                    $planId,
                    $nome,
                    $task['descricao'] ?? null,
                    $task['km_intervalo'] ?? null,
                    $task['dias_intervalo'] ?? null,
                ]);
                $idsMantidos[] = (int)$this->db->lastInsertId();
            }
        }
        if ($idsMantidos) {
            $place = implode(',', array_fill(0, count($idsMantidos), '?'));
            $params = array_merge($idsMantidos, [$planId]);
            $this->db->prepare("DELETE FROM man_maintenance_tasks WHERE id NOT IN ($place) AND plan_id = ?")->execute($params);
        }
    }

    public function listarVencimentos(array $filters = []): array
    {
        $sql = 'SELECT d.*, t.nome AS tarefa_nome, p.nome AS plano_nome, v.plate AS vehicle_plate
                FROM man_maintenance_due d
                INNER JOIN man_maintenance_tasks t ON t.id = d.task_id
                INNER JOIN man_maintenance_plans p ON p.id = d.plan_id
                LEFT JOIN cad_veiculos v ON v.id = d.veiculo_id
                WHERE d.empresa_id = ?';
        $params = [$this->empresaId];
        if (!empty($filters['status'])) {
            $sql .= ' AND d.status = ?';
            $params[] = $filters['status'];
        }
        $sql .= ' ORDER BY d.updated_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function processarPreventiva(SolicitacoesServicoModel $ssModel, AuditoriaModel $audit, bool $criarSS = true): array
    {
        $colOdo = $this->detectarOdometroColuna();
        $sql = 'SELECT t.*, p.nome AS plano_nome, p.tipo, p.km_intervalo AS plano_km, p.dias_intervalo AS plano_dias, p.due_soon_km, p.due_soon_dias, p.veiculo_id, v.plate';
        if ($colOdo) {
            $sql .= ', v.`' . $colOdo . '` AS odometro_atual';
        }
        $sql .= ' FROM man_maintenance_tasks t
                 INNER JOIN man_maintenance_plans p ON p.id = t.plan_id
                 LEFT JOIN cad_veiculos v ON v.id = p.veiculo_id
                 WHERE p.empresa_id = ? AND p.ativo = 1';
        $params = [$this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere('p');
        $sql .= $filialSql;
        $params = array_merge($params, $filialParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $updated = 0;
        $ssCriadas = 0;

        foreach ($stmt as $row) {
            $intervaloKm = $row['km_intervalo'] ?? $row['plano_km'];
            $intervaloDias = $row['dias_intervalo'] ?? $row['plano_dias'];
            $dueSoonKm = (int)($row['due_soon_km'] ?? 0);
            $dueSoonDias = (int)($row['due_soon_dias'] ?? 0);
            $ultimoKm = $row['ultimo_odometro'] ?? 0;
            $odometroAtual = $colOdo ? ($row['odometro_atual'] ?? null) : null;
            $dueKm = $intervaloKm ? ($ultimoKm + (int)$intervaloKm) : null;

            $ultimaExecucao = $row['ultima_execucao_em'] ?: $row['created_at'];
            $dueDate = null;
            if ($intervaloDias) {
                $dt = new \DateTime($ultimaExecucao);
                $dt->modify('+' . (int)$intervaloDias . ' days');
                $dueDate = $dt->format('Y-m-d');
            }

            $status = 'ok';
            $hoje = new \DateTimeImmutable();
            if ($dueDate) {
                $dueDt = new \DateTimeImmutable($dueDate);
                $diffDias = (int)$hoje->diff($dueDt)->format('%r%a');
                if ($diffDias <= 0) {
                    $status = 'overdue';
                } elseif ($diffDias <= $dueSoonDias) {
                    $status = 'due_soon';
                }
            }
            if ($dueKm && $odometroAtual !== null) {
                $kmRest = $dueKm - (int)$odometroAtual;
                if ($kmRest <= 0) {
                    $status = 'overdue';
                } elseif ($kmRest <= $dueSoonKm && $status !== 'overdue') {
                    $status = 'due_soon';
                }
            }

            $dueRowId = $this->upsertDueRow([
                'plan_id' => (int)$row['plan_id'],
                'task_id' => (int)$row['id'],
                'veiculo_id' => (int)$row['veiculo_id'],
                'status' => $status,
                'due_date' => $dueDate,
                'due_km' => $dueKm,
            ]);
            $updated++;

            if ($status === 'overdue' && $criarSS) {
                $exists = $this->db->prepare('SELECT generated_ss_id FROM man_maintenance_due WHERE id = ?');
                $exists->execute([$dueRowId]);
                $ssId = $exists->fetchColumn();
                if (!$ssId) {
                    $ssId = $ssModel->criar([
                        'filial_id' => $this->filialId,
                        'source_type' => 'preventive_due',
                        'source_table' => 'man_maintenance_due',
                        'source_id' => $dueRowId,
                        'source_ref' => 'plan-' . $row['plan_id'] . '-task-' . $row['id'],
                        'source_payload_json' => json_encode(['plan_id' => $row['plan_id'], 'task_id' => $row['id']]),
                        'veiculo_id' => $row['veiculo_id'],
                        'prioridade' => 'media',
                        'titulo' => 'Preventiva: ' . $row['nome'],
                        'descricao' => 'Vencimento do plano ' . ($row['plano_nome'] ?? '') . ' para veiculo ' . ($row['plate'] ?? ''),
                        'status' => 'aberta',
                        'criada_por' => null,
                    ]);
                    $this->db->prepare('UPDATE man_maintenance_due SET generated_ss_id = ? WHERE id = ?')->execute([$ssId, $dueRowId]);
                    $audit->registrar('preventiva', $dueRowId, 'ss_created', null, ['ss_id' => $ssId], null);
                    $ssCriadas++;
                }
            }
        }
        return ['due_updated' => $updated, 'ss_criadas' => $ssCriadas];
    }

    private function upsertDueRow(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO man_maintenance_due (empresa_id, filial_id, plan_id, task_id, veiculo_id, status, due_date, due_km, last_check_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE status = VALUES(status), due_date = VALUES(due_date), due_km = VALUES(due_km), last_check_at = NOW(), updated_at = NOW()'
        );
        $stmt->execute([
            $this->empresaId,
            $this->filialId,
            $data['plan_id'],
            $data['task_id'],
            $data['veiculo_id'],
            $data['status'],
            $data['due_date'],
            $data['due_km'],
        ]);
        $id = (int)$this->db->lastInsertId();
        if (!$id) {
            $stmt2 = $this->db->prepare(
                'SELECT id FROM man_maintenance_due WHERE plan_id = ? AND task_id = ? AND veiculo_id = ?'
            );
            $stmt2->execute([$data['plan_id'], $data['task_id'], $data['veiculo_id']]);
            $id = (int)$stmt2->fetchColumn();
        }
        return $id;
    }
}
