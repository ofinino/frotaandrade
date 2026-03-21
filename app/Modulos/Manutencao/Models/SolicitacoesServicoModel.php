<?php
namespace App\Modulos\Manutencao\Models;

class SolicitacoesServicoModel
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

    private function filialWhere(string $alias = 'sr', string $table = 'man_service_requests'): array
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

    public function listar(array $filters = []): array
    {
        $sql = 'SELECT sr.*, v.plate AS vehicle_plate, u.name AS created_by_name,
                       GROUP_CONCAT(DISTINCT os.codigo ORDER BY os.codigo SEPARATOR ", ") AS work_order_codes
                FROM man_service_requests sr
                LEFT JOIN cad_veiculos v ON v.id = sr.veiculo_id
                LEFT JOIN seg_usuarios u ON u.id = sr.criada_por
                LEFT JOIN man_service_request_work_order sso ON sso.service_request_id = sr.id
                LEFT JOIN man_work_orders os ON os.id = sso.work_order_id
                WHERE sr.empresa_id = ?';
        $params = [$this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere('sr');
        $sql .= $filialSql;
        $params = array_merge($params, $filialParams);

        if (!empty($filters['status'])) {
            $sql .= ' AND sr.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['prioridade'])) {
            $sql .= ' AND sr.prioridade = ?';
            $params[] = $filters['prioridade'];
        }
        if (!empty($filters['veiculo_id'])) {
            $sql .= ' AND sr.veiculo_id = ?';
            $params[] = (int)$filters['veiculo_id'];
        }
        if (!empty($filters['source_type'])) {
            $sql .= ' AND sr.source_type = ?';
            $params[] = $filters['source_type'];
        }
        if (!empty($filters['q'])) {
            $like = '%' . $filters['q'] . '%';
            $sql .= ' AND (sr.titulo LIKE ? OR sr.descricao LIKE ? OR v.plate LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filters['de'])) {
            $sql .= ' AND DATE(sr.created_at) >= ?';
            $params[] = $filters['de'];
        }
        if (!empty($filters['ate'])) {
            $sql .= ' AND DATE(sr.created_at) <= ?';
            $params[] = $filters['ate'];
        }

        $sql .= ' GROUP BY sr.id ORDER BY sr.created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obter(int $id): ?array
    {
        $sql = 'SELECT sr.*, v.plate AS vehicle_plate, u.name AS created_by_name
                FROM man_service_requests sr
                LEFT JOIN cad_veiculos v ON v.id = sr.veiculo_id
                LEFT JOIN seg_usuarios u ON u.id = sr.criada_por
                WHERE sr.id = ? AND sr.empresa_id = ?';
        $params = [$id, $this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere('sr');
        $sql .= $filialSql;
        $params = array_merge($params, $filialParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $pivot = $this->db->prepare(
            'SELECT os.id, os.codigo, os.status FROM man_service_request_work_order p
             INNER JOIN man_work_orders os ON os.id = p.work_order_id
             WHERE p.service_request_id = ?'
        );
        $pivot->execute([$id]);
        $row['work_orders'] = $pivot->fetchAll();
        return $row;
    }

    public function criar(array $payload): int
    {
        $cols = ['empresa_id','filial_id','source_type','source_table','source_id','source_ref','source_payload_json','veiculo_id','prioridade','titulo','descricao','status','rejeitada_motivo','criada_por','encerrada_em','created_at'];
        $values = [
            $this->empresaId,
            $payload['filial_id'] ?? $this->filialId,
            $payload['source_type'],
            $payload['source_table'] ?? null,
            $payload['source_id'] ?? null,
            $payload['source_ref'] ?? null,
            $payload['source_payload_json'] ?? null,
            $payload['veiculo_id'] ?? null,
            $payload['prioridade'] ?? 'media',
            $payload['titulo'],
            $payload['descricao'] ?? null,
            $payload['status'] ?? 'aberta',
            $payload['rejeitada_motivo'] ?? null,
            $payload['criada_por'] ?? null,
            $payload['encerrada_em'] ?? null,
            date('Y-m-d H:i:s'),
        ];
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $this->db->prepare(
            'INSERT INTO man_service_requests (' . implode(',', $cols) . ') VALUES (' . $placeholders . ')'
        )->execute($values);
        return (int)$this->db->lastInsertId();
    }

    public function atualizar(int $id, array $payload): int
    {
        $sql = 'UPDATE man_service_requests sr SET sr.titulo = ?, sr.descricao = ?, sr.prioridade = ?, sr.veiculo_id = ?, sr.updated_at = NOW() WHERE sr.id = ? AND sr.empresa_id = ?';
        [$filialSql, $filialParams] = $this->filialWhere('sr');
        $sql .= $filialSql;
        $params = [
            $payload['titulo'],
            $payload['descricao'] ?? null,
            $payload['prioridade'] ?? 'media',
            $payload['veiculo_id'] ?? null,
            $id,
            $this->empresaId,
        ];
        $params = array_merge($params, $filialParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function mudarStatus(int $id, string $status, ?string $motivo = null): int
    {
        $sql = 'UPDATE man_service_requests sr SET sr.status = ?, sr.rejeitada_motivo = ?, sr.encerrada_em = IF(?="encerrada", NOW(), sr.encerrada_em), sr.updated_at = NOW() WHERE sr.id = ? AND sr.empresa_id = ?';
        [$filialSql, $filialParams] = $this->filialWhere('sr');
        $sql .= $filialSql;
        $params = [$status, $motivo, $status, $id, $this->empresaId];
        $params = array_merge($params, $filialParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function converterParaOS(int $id, array $osData, OrdensServicoModel $osModel): int
    {
        $this->db->beginTransaction();
        $ss = $this->obter($id);
        if (!$ss) {
            throw new \RuntimeException('SS nao encontrada.');
        }
        $osId = $osModel->criar([
            'codigo' => $osData['codigo'] ?? null,
            'veiculo_id' => $ss['veiculo_id'],
            'status' => $osData['status'] ?? 'aprovada',
            'odometro_abertura' => $osData['odometro_abertura'] ?? null,
            'aberta_por' => $osData['aberta_por'] ?? ($osData['user_id'] ?? null),
            'aberta_em' => $osData['aberta_em'] ?? date('Y-m-d H:i:s'),
            'observacoes' => $osData['observacoes'] ?? $ss['descricao'],
            'filial_id' => $ss['filial_id'] ?? $this->filialId,
        ]);
        $this->vincularEmOS($id, $osId);
        $this->mudarStatus($id, 'convertida', null);
        $this->db->commit();
        return $osId;
    }

    public function vincularEmOS(int $ssId, int $osId): int
    {
        $exists = $this->db->prepare(
            'SELECT id FROM man_service_request_work_order WHERE service_request_id = ? AND work_order_id = ? LIMIT 1'
        );
        $exists->execute([$ssId, $osId]);
        if ($exists->fetchColumn()) {
            return 0;
        }
        $stmt = $this->db->prepare(
            'INSERT INTO man_service_request_work_order (empresa_id, filial_id, service_request_id, work_order_id, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$this->empresaId, $this->filialId, $ssId, $osId]);
        return (int)$this->db->lastInsertId();
    }

    public function existeParaOrigem(string $sourceType, string $sourceTable, int $sourceId, ?string $sourceRef = null): ?int
    {
        $sql = 'SELECT id FROM man_service_requests sr WHERE sr.empresa_id = ? AND sr.source_type = ? AND sr.source_table = ? AND sr.source_id = ?';
        $params = [$this->empresaId, $sourceType, $sourceTable, $sourceId];
        if ($sourceRef !== null) {
            $sql .= ' AND sr.source_ref = ?';
            $params[] = $sourceRef;
        }
        [$filialSql, $filialParams] = $this->filialWhere('sr');
        $sql .= $filialSql . ' LIMIT 1';
        $params = array_merge($params, $filialParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    public function criarSSNaoConformePorExecucao(int $execucaoId, ?int $actorId = null): int
    {
        $where = 'WHERE e.id = ? AND e.empresa_id = ?';
        $params = [$execucaoId, $this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere('e', 'man_checklist_execucoes');
        $where .= $filialSql;
        $params = array_merge($params, $filialParams);

        $execStmt = $this->db->prepare(
            "SELECT e.*, v.plate AS vehicle_plate FROM man_checklist_execucoes e
             LEFT JOIN cad_veiculos v ON v.id = e.veiculo_id $where"
        );
        $execStmt->execute($params);
        $exec = $execStmt->fetch();
        if (!$exec) {
            return 0;
        }

        $itemsStmt = $this->db->prepare(
            'SELECT id, label FROM man_checklist_versao_itens WHERE versao_id = ?'
        );
        $itemsStmt->execute([(int)$exec['versao_id']]);
        $labels = [];
        foreach ($itemsStmt as $row) {
            $labels[(int)$row['id']] = $row['label'];
        }

        $respStmt = $this->db->prepare(
            'SELECT r.id AS resposta_id, COALESCE(r.versao_item_id, r.checklist_item_id) AS item_id, r.answer
             FROM man_checklist_respostas r WHERE r.checklist_execucao_id = ?'
        );
        $respStmt->execute([$execucaoId]);
        $created = 0;
        foreach ($respStmt as $row) {
            $answer = json_decode($row['answer'] ?? '', true);
            if (($answer['status'] ?? '') !== 'nao_conforme') {
                continue;
            }
            $itemId = (int)$row['item_id'];
            $ref = $execucaoId . '-' . $itemId;
            if ($this->existeParaOrigem('checklist_nonconformity', 'man_checklist_execucoes', $execucaoId, $ref)) {
                continue;
            }
            $payloadJson = json_encode([
                'execucao_id' => $execucaoId,
                'resposta_id' => (int)$row['resposta_id'],
                'item_id' => $itemId,
                'plate' => $exec['vehicle_plate'] ?? null,
                'template_id' => $exec['checklist_id'] ?? null,
            ]);
            $titulo = 'NC: ' . ($labels[$itemId] ?? ('Item ' . $itemId));
            $descricao = $answer['obs'] ?? 'Nao conforme';
            $this->criar([
                'filial_id' => $exec['filial_id'] ?? $this->filialId,
                'source_type' => 'checklist_nonconformity',
                'source_table' => 'man_checklist_execucoes',
                'source_id' => $execucaoId,
                'source_ref' => $ref,
                'source_payload_json' => $payloadJson,
                'veiculo_id' => $exec['veiculo_id'] ?? null,
                'prioridade' => 'alta',
                'titulo' => $titulo,
                'descricao' => $descricao,
                'status' => 'aberta',
                'criada_por' => $actorId,
            ]);
            $created++;
        }
        return $created;
    }
}
