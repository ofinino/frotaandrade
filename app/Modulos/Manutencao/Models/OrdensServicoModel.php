<?php
namespace App\Modulos\Manutencao\Models;

class OrdensServicoModel
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

    private function filialWhere(string $alias = 'o', string $table = 'man_work_orders'): array
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

    public function listar(array $filters = []): array
    {
        $sql = 'SELECT o.*, v.plate AS vehicle_plate, u.name AS aberta_por_nome,
                       COUNT(DISTINCT p.service_request_id) AS total_ss
                FROM man_work_orders o
                LEFT JOIN cad_veiculos v ON v.id = o.veiculo_id
                LEFT JOIN seg_usuarios u ON u.id = o.aberta_por
                LEFT JOIN man_service_request_work_order p ON p.work_order_id = o.id
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

        $sql .= ' GROUP BY o.id ORDER BY o.created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
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
        $stmt = $this->db->prepare(
            'INSERT INTO man_work_orders (empresa_id, filial_id, codigo, veiculo_id, status, odometro_abertura, odometro_fechamento, aberta_por, aberta_em, iniciada_em, concluida_em, encerrada_em, observacoes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $this->empresaId,
            $payload['filial_id'] ?? $this->filialId,
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
            $this->filialId,
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
            $this->filialId,
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
            $this->filialId,
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
            )->execute([$this->empresaId, $this->filialId, $sid, $osId]);
        }
    }
}
