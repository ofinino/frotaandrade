<?php
namespace App\Modulos\Manutencao\Models;

class PlanosPreventivaModel
{
    private \PDO $db;
    private int $empresaId;
    private ?int $filialId;
    private array $filiais;

    public function __construct(\PDO $db, int $empresaId, ?int $filialId, array $filiais = [])
    {
        $this->db = $db;
        $this->empresaId = $empresaId;
        $this->filialId = $filialId;
        $this->filiais = $filiais;
    }

    public function listarFiliais(): array
    {
        $stmt = $this->db->prepare('SELECT id, name FROM cad_filiais WHERE empresa_id = ? ORDER BY name ASC');
        $stmt->execute([$this->empresaId]);
        return $stmt->fetchAll();
    }

    public function listarVeiculos(): array
    {
        $stmt = $this->db->prepare('SELECT id, plate, model, filial_id FROM cad_veiculos WHERE empresa_id = ? ORDER BY plate ASC');
        $stmt->execute([$this->empresaId]);
        return $stmt->fetchAll();
    }

    public function listarPlanos(array $filters = []): array
    {
        $sql = "SELECT p.*,
                    (SELECT COUNT(*) FROM man_maintenance_plan_services ps WHERE ps.plan_id = p.id) AS servicos_count,
                    (CASE WHEN p.association_type = 'unidade'
                        THEN (SELECT COUNT(*) FROM cad_veiculos v WHERE v.empresa_id = p.empresa_id AND v.filial_id = p.association_filial_id)
                        ELSE (SELECT COUNT(*) FROM man_maintenance_plan_veiculos pv WHERE pv.plan_id = p.id)
                    END) AS veiculos_count
                FROM man_maintenance_plans p
                WHERE p.empresa_id = ?";
        $params = [$this->empresaId];
        if (!empty($filters['association_type'])) {
            $sql .= ' AND p.association_type = ?';
            $params[] = $filters['association_type'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND p.nome LIKE ?';
            $params[] = '%' . $filters['q'] . '%';
        }
        $sql .= ' ORDER BY p.created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obterPlano(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM man_maintenance_plans WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $this->empresaId]);
        $plan = $stmt->fetch();
        if (!$plan) {
            return null;
        }
        $plan['servicos'] = $this->listarServicosDoPlano($id);
        $plan['veiculos'] = $this->veiculosDoPlano($id, $plan);
        return $plan;
    }

    public function criarPlano(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO man_maintenance_plans (empresa_id, filial_id, nome, medicao_tipo, association_type, association_filial_id, status, criado_por, created_at)
             VALUES (?, ?, ?, ?, ?, ?, "rascunho", ?, NOW())'
        );
        $stmt->execute([
            $this->empresaId,
            $this->filialId,
            $data['nome'],
            $data['medicao_tipo'],
            $data['association_type'],
            $data['association_filial_id'] ?? null,
            $data['criado_por'] ?? null,
        ]);
        $planId = (int)$this->db->lastInsertId();
        if ($data['association_type'] === 'veiculos' && !empty($data['veiculo_ids'])) {
            foreach ($data['veiculo_ids'] as $vid) {
                $this->addVeiculo($planId, (int)$vid);
            }
        }
        return $planId;
    }

    public function publicar(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE man_maintenance_plans SET status = 'ativo', updated_at = NOW() WHERE id = ? AND empresa_id = ? AND status = 'rascunho'");
        $stmt->execute([$id, $this->empresaId]);
        return $stmt->rowCount() > 0;
    }

    public function toggleStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            return false;
        }
        $stmt = $this->db->prepare("UPDATE man_maintenance_plans SET status = ?, updated_at = NOW() WHERE id = ? AND empresa_id = ? AND status IN ('ativo','inativo')");
        $stmt->execute([$status, $id, $this->empresaId]);
        return $stmt->rowCount() > 0;
    }

    public function addVeiculo(int $planId, int $veiculoId): void
    {
        $this->db->prepare(
            'INSERT IGNORE INTO man_maintenance_plan_veiculos (plan_id, veiculo_id) VALUES (?, ?)'
        )->execute([$planId, $veiculoId]);
    }

    public function removeVeiculo(int $planId, int $veiculoId): void
    {
        $this->db->prepare(
            'DELETE FROM man_maintenance_plan_veiculos WHERE plan_id = ? AND veiculo_id = ?'
        )->execute([$planId, $veiculoId]);
    }

    public function veiculosDoPlano(int $planId, ?array $plan = null): array
    {
        $plan = $plan ?? $this->obterPlanoRaw($planId);
        if (!$plan) {
            return [];
        }
        if ($plan['association_type'] === 'unidade') {
            $stmt = $this->db->prepare('SELECT id, plate, model FROM cad_veiculos WHERE empresa_id = ? AND filial_id = ? ORDER BY plate ASC');
            $stmt->execute([$this->empresaId, $plan['association_filial_id']]);
            return $stmt->fetchAll();
        }
        $stmt = $this->db->prepare(
            'SELECT v.id, v.plate, v.model FROM man_maintenance_plan_veiculos pv
             INNER JOIN cad_veiculos v ON v.id = pv.veiculo_id
             WHERE pv.plan_id = ? ORDER BY v.plate ASC'
        );
        $stmt->execute([$planId]);
        return $stmt->fetchAll();
    }

    private function obterPlanoRaw(int $planId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM man_maintenance_plans WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$planId, $this->empresaId]);
        return $stmt->fetch() ?: null;
    }

    public function listarServicosDoPlano(int $planId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ps.*, s.nome AS service_nome
             FROM man_maintenance_plan_services ps
             INNER JOIN man_maintenance_services s ON s.id = ps.service_id
             WHERE ps.plan_id = ? ORDER BY ps.created_at ASC'
        );
        $stmt->execute([$planId]);
        return $stmt->fetchAll();
    }

    public function addServicos(int $planId, array $serviceIds, array $recorrencia): void
    {
        $filialId = $this->filialId;
        foreach ($serviceIds as $serviceId) {
            $stmt = $this->db->prepare(
                'INSERT INTO man_maintenance_plan_services
                    (empresa_id, filial_id, plan_id, service_id, tipo, intervalo_tempo_valor, intervalo_tempo_unidade, alerta_tempo_dias, intervalo_medicao, alerta_medicao, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $this->empresaId,
                $filialId,
                $planId,
                (int)$serviceId,
                $recorrencia['tipo'] ?? 'recorrente',
                $recorrencia['intervalo_tempo_valor'] ?? null,
                $recorrencia['intervalo_tempo_unidade'] ?? null,
                $recorrencia['alerta_tempo_dias'] ?? null,
                $recorrencia['intervalo_medicao'] ?? null,
                $recorrencia['alerta_medicao'] ?? null,
            ]);
        }
    }

    public function removeServico(int $planServiceId, int $planId): void
    {
        $this->db->prepare(
            'DELETE FROM man_maintenance_plan_services WHERE id = ? AND plan_id = ?'
        )->execute([$planServiceId, $planId]);
    }

    public function contarVencimentos(): array
    {
        $sql = 'SELECT status, COUNT(*) c FROM man_maintenance_due WHERE empresa_id = ? GROUP BY status';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->empresaId]);
        $counts = ['ok' => 0, 'due_soon' => 0, 'overdue' => 0];
        foreach ($stmt as $row) {
            $counts[$row['status']] = (int)$row['c'];
        }
        $counts['todos'] = $counts['ok'] + $counts['due_soon'] + $counts['overdue'];
        return $counts;
    }

    public function listarVencimentos(array $filters = []): array
    {
        $sql = 'SELECT d.*, s.nome AS tarefa_nome, p.nome AS plano_nome, v.plate AS vehicle_plate
                FROM man_maintenance_due d
                INNER JOIN man_maintenance_plan_services ps ON ps.id = d.plan_service_id
                INNER JOIN man_maintenance_services s ON s.id = ps.service_id
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

    private function normalizarDiasIntervalo(?int $valor, ?string $unidade): ?int
    {
        if (!$valor) {
            return null;
        }
        return match ($unidade) {
            'anos' => $valor * 365,
            'meses' => $valor * 30,
            default => $valor,
        };
    }

    public function processarPreventiva(SolicitacoesServicoModel $ssModel, AuditoriaModel $audit, bool $criarSS = true): array
    {
        $stmt = $this->db->prepare("SELECT * FROM man_maintenance_plans WHERE empresa_id = ? AND status = 'ativo'");
        $stmt->execute([$this->empresaId]);
        $planos = $stmt->fetchAll();

        $updated = 0;
        $ssCriadas = 0;

        foreach ($planos as $plano) {
            $veiculos = $this->veiculosDoPlano((int)$plano['id'], $plano);
            $servicos = $this->listarServicosDoPlano((int)$plano['id']);

            foreach ($veiculos as $veiculo) {
                $veiculoRow = $this->db->prepare('SELECT nin_km_preventiva, plate FROM cad_veiculos WHERE id = ?');
                $veiculoRow->execute([$veiculo['id']]);
                $vRow = $veiculoRow->fetch();
                $odometroAtual = $vRow['nin_km_preventiva'] ?? null;
                $placa = $vRow['plate'] ?? '';

                foreach ($servicos as $servico) {
                    if ($servico['tipo'] === 'unico') {
                        continue;
                    }

                    $dueDate = null;
                    $dueMedicao = null;
                    $status = 'ok';
                    $hoje = new \DateTimeImmutable('today');

                    $intervaloDias = $this->normalizarDiasIntervalo(
                        $servico['intervalo_tempo_valor'] !== null ? (int)$servico['intervalo_tempo_valor'] : null,
                        $servico['intervalo_tempo_unidade']
                    );
                    if ($intervaloDias) {
                        $ultima = $servico['ultima_execucao_em'] ?: $servico['created_at'];
                        $dt = new \DateTime($ultima);
                        $dt->modify('+' . $intervaloDias . ' days');
                        $dueDate = $dt->format('Y-m-d');
                        $dueDt = new \DateTimeImmutable($dueDate);
                        $diffDias = (int)$hoje->diff($dueDt)->format('%r%a');
                        $alertaDias = (int)($servico['alerta_tempo_dias'] ?? 0);
                        if ($diffDias <= 0) {
                            $status = 'overdue';
                        } elseif ($diffDias <= $alertaDias) {
                            $status = 'due_soon';
                        }
                    }

                    if ($servico['intervalo_medicao'] && $odometroAtual !== null) {
                        $ultimoValor = (int)($servico['ultimo_valor_medicao'] ?? 0);
                        $dueMedicao = $ultimoValor + (int)$servico['intervalo_medicao'];
                        $restante = $dueMedicao - (int)$odometroAtual;
                        $alertaMedicao = (int)($servico['alerta_medicao'] ?? 0);
                        if ($restante <= 0) {
                            $status = 'overdue';
                        } elseif ($restante <= $alertaMedicao && $status !== 'overdue') {
                            $status = 'due_soon';
                        }
                    }

                    $dueRowId = $this->upsertDueRow([
                        'plan_id' => (int)$plano['id'],
                        'plan_service_id' => (int)$servico['id'],
                        'veiculo_id' => (int)$veiculo['id'],
                        'status' => $status,
                        'due_date' => $dueDate,
                        'due_medicao' => $dueMedicao,
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
                                'source_ref' => 'plan-' . $plano['id'] . '-service-' . $servico['id'] . '-veiculo-' . $veiculo['id'],
                                'source_payload_json' => json_encode(['plan_id' => $plano['id'], 'plan_service_id' => $servico['id']]),
                                'veiculo_id' => $veiculo['id'],
                                'prioridade' => 'media',
                                'titulo' => 'Preventiva: ' . $servico['service_nome'],
                                'descricao' => 'Vencimento do plano ' . $plano['nome'] . ' para veiculo ' . $placa,
                                'status' => 'aberta',
                                'criada_por' => null,
                            ]);
                            $this->db->prepare('UPDATE man_maintenance_due SET generated_ss_id = ? WHERE id = ?')->execute([$ssId, $dueRowId]);
                            $audit->registrar('preventiva', $dueRowId, 'ss_created', null, ['ss_id' => $ssId], null);
                            $ssCriadas++;
                        }
                    }
                }
            }
        }

        return ['due_updated' => $updated, 'ss_criadas' => $ssCriadas];
    }

    private function upsertDueRow(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO man_maintenance_due (empresa_id, filial_id, plan_id, plan_service_id, veiculo_id, status, due_date, due_medicao, last_check_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE status = VALUES(status), due_date = VALUES(due_date), due_medicao = VALUES(due_medicao), last_check_at = NOW(), updated_at = NOW()'
        );
        $stmt->execute([
            $this->empresaId,
            $this->filialId,
            $data['plan_id'],
            $data['plan_service_id'],
            $data['veiculo_id'],
            $data['status'],
            $data['due_date'],
            $data['due_medicao'],
        ]);
        $id = (int)$this->db->lastInsertId();
        if (!$id) {
            $stmt2 = $this->db->prepare(
                'SELECT id FROM man_maintenance_due WHERE plan_service_id = ? AND veiculo_id = ?'
            );
            $stmt2->execute([$data['plan_service_id'], $data['veiculo_id']]);
            $id = (int)$stmt2->fetchColumn();
        }
        return $id;
    }
}
