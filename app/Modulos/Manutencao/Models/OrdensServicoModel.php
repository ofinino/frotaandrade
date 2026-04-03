<?php
namespace App\Modulos\Manutencao\Models;

class OrdensServicoModel
{
    private \PDO $db;
    private int $empresaId;
    private ?int $filialId;
    private array $filiais;
    private array $filialCols = [];
    private ?bool $scheduleTableReady = null;
    private ?bool $scheduleDateTimeReady = null;

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

    private function filialWhere(string $alias = 'o', string $table = 'man_work_orders'): array
    {
        $col = $this->detectarFilialColuna($table);
        if (!$col) {
            return ['', []];
        }
        $ids = $this->normalizarFiliais($this->filiais);
        $filialAtual = $this->normalizarFilialId($this->filialId);
        if (empty($ids) && $filialAtual !== null) {
            $ids = [$filialAtual];
        }
        if (!$ids) {
            return ['', []];
        }
        $place = implode(',', array_fill(0, count($ids), '?'));
        $prefix = $alias ? "{$alias}." : '';
        return [" AND ({$prefix}`$col` IS NULL OR {$prefix}`$col` = 0 OR {$prefix}`$col` IN ($place))", $ids];
    }

    private function normalizarFilialId($filialId): ?int
    {
        if ($filialId === null || $filialId === '' || !is_numeric($filialId)) {
            return null;
        }
        $id = (int)$filialId;
        return $id > 0 ? $id : null;
    }

    private function normalizarFiliais(array $filiais): array
    {
        $result = [];
        foreach ($filiais as $id) {
            $norm = $this->normalizarFilialId($id);
            if ($norm !== null) {
                $result[$norm] = $norm;
            }
        }
        return array_values($result);
    }

    private function gerarCodigo(): string
    {
        $year = date('Y');
        $prefix = "OS-$year-";
        $stmt = $this->db->prepare('SELECT codigo FROM man_work_orders WHERE empresa_id = ? AND codigo LIKE ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$this->empresaId, $prefix . '%']);
        $last = $stmt->fetchColumn();
        $num = 1;
        if ($last && preg_match('/^OS-\d{4}-(\d{6})$/', $last, $m)) {
            $num = (int)$m[1] + 1;
        }
        return $prefix . str_pad((string)$num, 6, '0', STR_PAD_LEFT);
    }

    private function ensureScheduleTable(): bool
    {
        if ($this->scheduleTableReady !== null) {
            return $this->scheduleTableReady;
        }

        try {
            $existsStmt = $this->db->query("SHOW TABLES LIKE 'man_work_order_schedule'");
            if ($existsStmt && $existsStmt->fetchColumn()) {
                $this->scheduleTableReady = true;
                $this->ensureScheduleDateTimeColumn();
                return true;
            }

            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS man_work_order_schedule (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    empresa_id INT NOT NULL,
                    filial_id INT NULL,
                    work_order_id INT NOT NULL,
                    executor_id INT NULL,
                    programada_para DATETIME NULL,
                    updated_by INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_wo_schedule (work_order_id),
                    INDEX idx_wo_schedule_exec (empresa_id, filial_id, executor_id),
                    INDEX idx_wo_schedule_date (programada_para)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $this->scheduleTableReady = true;
            $this->scheduleDateTimeReady = true;
            return true;
        } catch (\Throwable $e) {
            $this->scheduleTableReady = false;
            return false;
        }
    }

    private function ensureScheduleDateTimeColumn(): void
    {
        if ($this->scheduleDateTimeReady !== null) {
            return;
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT DATA_TYPE
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'man_work_order_schedule'
                   AND COLUMN_NAME = 'programada_para'
                 LIMIT 1"
            );
            $stmt->execute();
            $type = strtolower((string)($stmt->fetchColumn() ?? ''));
            if ($type === 'date') {
                $this->db->exec('ALTER TABLE man_work_order_schedule MODIFY COLUMN programada_para DATETIME NULL');
            }
            $this->scheduleDateTimeReady = true;
        } catch (\Throwable $e) {
            $this->scheduleDateTimeReady = false;
        }
    }

    private function normalizarProgramadaPara(?string $programadaPara): ?string
    {
        $value = trim((string)$programadaPara);
        if ($value === '') {
            return null;
        }

        $formats = [
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd/m/Y',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i',
            'Y-m-d',
        ];

        foreach ($formats as $format) {
            $dt = \DateTime::createFromFormat($format, $value);
            if (!$dt) {
                continue;
            }

            $errors = \DateTime::getLastErrors();
            $warningCount = is_array($errors) ? (int)($errors['warning_count'] ?? 0) : 0;
            $errorCount = is_array($errors) ? (int)($errors['error_count'] ?? 0) : 0;
            if ($warningCount > 0 || $errorCount > 0) {
                continue;
            }

            if ($dt->format($format) !== $value) {
                continue;
            }

            if (in_array($format, ['Y-m-d', 'd/m/Y'], true)) {
                $dt->setTime(0, 0, 0);
            } elseif (in_array($format, ['Y-m-d\TH:i', 'Y-m-d H:i', 'd/m/Y H:i'], true)) {
                $dt->setTime((int)$dt->format('H'), (int)$dt->format('i'), 0);
            }

            return $dt->format('Y-m-d H:i:s');
        }

        return null;
    }
    public function listar(array $filters = []): array
    {
        $scheduleReady = $this->ensureScheduleTable();

        $sql = 'SELECT o.*, v.plate AS vehicle_plate, u.name AS aberta_por_nome,
                       COUNT(DISTINCT p.service_request_id) AS total_ss';
        if ($scheduleReady) {
            $sql .= ', ws.executor_id, ws.programada_para, ue.name AS executor_nome';
        } else {
            $sql .= ', NULL AS executor_id, NULL AS programada_para, NULL AS executor_nome';
        }

        $sql .= '
                FROM man_work_orders o
                LEFT JOIN cad_veiculos v ON v.id = o.veiculo_id
                LEFT JOIN seg_usuarios u ON u.id = o.aberta_por
                LEFT JOIN man_service_request_work_order p ON p.work_order_id = o.id';
        if ($scheduleReady) {
            $sql .= '
                LEFT JOIN (
                    SELECT s1.*
                    FROM man_work_order_schedule s1
                    INNER JOIN (
                        SELECT empresa_id, work_order_id, MAX(id) AS max_id
                        FROM man_work_order_schedule
                        GROUP BY empresa_id, work_order_id
                    ) s2 ON s2.max_id = s1.id
                ) ws ON ws.work_order_id = o.id AND ws.empresa_id = o.empresa_id
                LEFT JOIN seg_usuarios ue ON ue.id = ws.executor_id';
        }
        $sql .= '
                WHERE o.empresa_id = ?';

        $params = [$this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere('o');
        $sql .= $filialSql;
        $params = array_merge($params, $filialParams);

        if (!empty($filters['status'])) {
            $sql .= ' AND o.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['veiculo_id'])) {
            $sql .= ' AND o.veiculo_id = ?';
            $params[] = (int)$filters['veiculo_id'];
        }
        if (!empty($filters['q'])) {
            $like = '%' . $filters['q'] . '%';
            $sql .= ' AND (o.codigo LIKE ? OR o.observacoes LIKE ? OR v.plate LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' GROUP BY o.id ORDER BY (programada_para IS NULL) ASC, programada_para ASC, o.created_at ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function listarExecutantes(): array
    {
        $sql = "SELECT id, name, role
                FROM seg_usuarios
                WHERE empresa_id = ? AND role IN ('executante','lider')";
        $params = [$this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere('', 'seg_usuarios');
        $sql .= $filialSql . ' ORDER BY name ASC';
        $params = array_merge($params, $filialParams);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        if ($rows) {
            return $rows;
        }

        $fallback = $this->db->prepare(
            'SELECT id, name, role FROM seg_usuarios WHERE empresa_id = ? ORDER BY name ASC'
        );
        $fallback->execute([$this->empresaId]);
        return $fallback->fetchAll();
    }

    public function agendarExecutor(int $osId, ?int $executorId, ?string $programadaPara, ?int $actorUserId): bool
    {
        if (!$this->ensureScheduleTable()) {
            return false;
        }

        $date = $this->normalizarProgramadaPara($programadaPara);

        $filialNorm = $this->normalizarFilialId($this->filialId);

        try {
            $this->db->beginTransaction();

            $delete = $this->db->prepare(
                'DELETE FROM man_work_order_schedule WHERE empresa_id = ? AND work_order_id = ?'
            );
            $delete->execute([$this->empresaId, $osId]);

            if ($executorId !== null || $date !== null) {
                $insert = $this->db->prepare(
                    'INSERT INTO man_work_order_schedule (empresa_id, filial_id, work_order_id, executor_id, programada_para, updated_by, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
                );
                $insert->execute([
                    $this->empresaId,
                    $filialNorm,
                    $osId,
                    $executorId ?: null,
                    $date,
                    $actorUserId,
                ]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
        if ($executorId) {
            $statusStmt = $this->db->prepare(
                "UPDATE man_work_orders
                 SET status = CASE
                     WHEN status IN ('rascunho','aprovada') THEN 'programada'
                     ELSE status
                 END,
                 updated_at = NOW()
                 WHERE id = ? AND empresa_id = ?"
            );
            $statusStmt->execute([$osId, $this->empresaId]);
        }

        return true;
    }

    public function obterAgendamento(int $osId): ?array
    {
        if (!$this->ensureScheduleTable()) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT s.executor_id, s.programada_para, u.name AS executor_nome
             FROM man_work_order_schedule s
             LEFT JOIN seg_usuarios u ON u.id = s.executor_id
             WHERE s.empresa_id = ? AND s.work_order_id = ?
             ORDER BY s.id DESC
             LIMIT 1'
        );
        $stmt->execute([$this->empresaId, $osId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
    public function obter(int $id): ?array
    {
        $sql = 'SELECT o.*, v.plate AS vehicle_plate, v.model AS vehicle_model, u.name AS aberta_por_nome
                FROM man_work_orders o
                LEFT JOIN cad_veiculos v ON v.id = o.veiculo_id
                LEFT JOIN seg_usuarios u ON u.id = o.aberta_por
                WHERE o.id = ? AND o.empresa_id = ?';
        $params = [$id, $this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere('o');
        $sql .= $filialSql;
        $params = array_merge($params, $filialParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['items'] = $this->listarItens($id);
        $row['labor'] = $this->listarMaoDeObra($id);
        $row['parts'] = $this->listarPecas($id);
        $row['service_requests'] = $this->listarServiceRequests($id);
        return $row;
    }

    public function criar(array $payload): int
    {
        $codigo = $payload['codigo'] ?? $this->gerarCodigo();
        $filialDestino = $this->normalizarFilialId($payload['filial_id'] ?? null) ?? $this->normalizarFilialId($this->filialId);
        $stmt = $this->db->prepare(
            'INSERT INTO man_work_orders (empresa_id, filial_id, codigo, veiculo_id, status, odometro_abertura, odometro_fechamento, aberta_por, aberta_em, iniciada_em, concluida_em, encerrada_em, observacoes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $this->empresaId,
            $filialDestino,
            $codigo,
            $payload['veiculo_id'] ?? null,
            $payload['status'] ?? 'rascunho',
            $payload['odometro_abertura'] ?? null,
            $payload['odometro_fechamento'] ?? null,
            $payload['aberta_por'] ?? null,
            $payload['aberta_em'] ?? date('Y-m-d H:i:s'),
            $payload['iniciada_em'] ?? null,
            $payload['concluida_em'] ?? null,
            $payload['encerrada_em'] ?? null,
            $payload['observacoes'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function atualizar(int $id, array $payload): int
    {
        $sql = 'UPDATE man_work_orders o SET o.veiculo_id = ?, o.status = ?, o.odometro_abertura = ?, o.odometro_fechamento = ?, o.observacoes = ?, o.updated_at = NOW() WHERE o.id = ? AND o.empresa_id = ?';
        [$filialSql, $filialParams] = $this->filialWhere('o');
        $sql .= $filialSql;
        $params = [
            $payload['veiculo_id'] ?? null,
            $payload['status'] ?? 'rascunho',
            $payload['odometro_abertura'] ?? null,
            $payload['odometro_fechamento'] ?? null,
            $payload['observacoes'] ?? null,
            $id,
            $this->empresaId,
        ];
        $params = array_merge($params, $filialParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function mudarStatus(int $id, string $status): int
    {
        $dates = [
            'iniciada_em' => null,
            'concluida_em' => null,
            'encerrada_em' => null,
        ];
        if ($status === 'em_execucao') {
            $dates['iniciada_em'] = date('Y-m-d H:i:s');
        } elseif ($status === 'concluida') {
            $dates['concluida_em'] = date('Y-m-d H:i:s');
        } elseif ($status === 'encerrada') {
            $dates['encerrada_em'] = date('Y-m-d H:i:s');
        }
        $stmt = $this->db->prepare(
            'UPDATE man_work_orders SET status = ?, iniciada_em = COALESCE(?, iniciada_em), concluida_em = COALESCE(?, concluida_em), encerrada_em = COALESCE(?, encerrada_em), updated_at = NOW()
             WHERE id = ? AND empresa_id = ?'
        );
        $stmt->execute([
            $status,
            $dates['iniciada_em'],
            $dates['concluida_em'],
            $dates['encerrada_em'],
            $id,
            $this->empresaId,
        ]);
        return $stmt->rowCount();
    }

    public function addItem(int $osId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO man_work_order_items (empresa_id, filial_id, work_order_id, titulo, descricao, status, prioridade, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $this->empresaId,
            $this->normalizarFilialId($this->filialId),
            $osId,
            $data['titulo'],
            $data['descricao'] ?? null,
            $data['status'] ?? 'pendente',
            $data['prioridade'] ?? 'media',
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function addMaoDeObra(int $osId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO man_work_order_labor (empresa_id, filial_id, work_order_id, executor_id, descricao, horas, valor_hora, total, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $horas = (float)($data['horas'] ?? 0);
        $valorHora = (float)($data['valor_hora'] ?? 0);
        $total = $data['total'] ?? ($horas * $valorHora);
        $stmt->execute([
            $this->empresaId,
            $this->normalizarFilialId($this->filialId),
            $osId,
            $data['executor_id'] ?? null,
            $data['descricao'],
            $horas,
            $valorHora,
            $total,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function addPeca(int $osId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO man_work_order_parts (empresa_id, filial_id, work_order_id, part_number, descricao, unidade, quantidade, custo_unit, total, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $qtd = (float)($data['quantidade'] ?? 1);
        $custo = (float)($data['custo_unit'] ?? 0);
        $total = $data['total'] ?? ($qtd * $custo);
        $stmt->execute([
            $this->empresaId,
            $this->normalizarFilialId($this->filialId),
            $osId,
            $data['part_number'] ?? null,
            $data['descricao'],
            $data['unidade'] ?? 'un',
            $qtd,
            $custo,
            $total,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function listarItens(int $osId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM man_work_order_items WHERE work_order_id = ? ORDER BY created_at DESC');
        $stmt->execute([$osId]);
        return $stmt->fetchAll();
    }

    public function listarMaoDeObra(int $osId): array
    {
        $stmt = $this->db->prepare('SELECT l.*, u.name AS executor_nome FROM man_work_order_labor l LEFT JOIN seg_usuarios u ON u.id = l.executor_id WHERE l.work_order_id = ? ORDER BY l.created_at DESC');
        $stmt->execute([$osId]);
        return $stmt->fetchAll();
    }

    public function listarPecas(int $osId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM man_work_order_parts WHERE work_order_id = ? ORDER BY created_at DESC');
        $stmt->execute([$osId]);
        return $stmt->fetchAll();
    }

    public function listarServiceRequests(int $osId): array
    {
        $stmt = $this->db->prepare(
            'SELECT sr.* FROM man_service_request_work_order p
             INNER JOIN man_service_requests sr ON sr.id = p.service_request_id
             WHERE p.work_order_id = ?'
        );
        $stmt->execute([$osId]);
        return $stmt->fetchAll();
    }

    public function listarVeiculos(): array
    {
        $sql = 'SELECT id, plate, model FROM cad_veiculos WHERE empresa_id = ?';
        $params = [$this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere('', 'cad_veiculos');
        $sql .= $filialSql . ' ORDER BY plate ASC';
        $params = array_merge($params, $filialParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function vincularServiceRequests(int $osId, array $ssIds): void
    {
        foreach ($ssIds as $sid) {
            $sid = (int)$sid;
            if (!$sid) {
                continue;
            }
            $exists = $this->db->prepare('SELECT id FROM man_service_request_work_order WHERE service_request_id = ? AND work_order_id = ? LIMIT 1');
            $exists->execute([$sid, $osId]);
            if ($exists->fetchColumn()) {
                continue;
            }
            $this->db->prepare(
                'INSERT INTO man_service_request_work_order (empresa_id, filial_id, service_request_id, work_order_id, created_at)
                 VALUES (?, ?, ?, ?, NOW())'
            )->execute([$this->empresaId, $this->normalizarFilialId($this->filialId), $sid, $osId]);
        }
    }
}


