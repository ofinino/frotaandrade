<?php
namespace App\Modulos\Manutencao\Models;

class ExecucoesModel
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
            "SELECT COLUMN_NAME 
             FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
               AND TABLE_NAME = ? 
               AND COLUMN_NAME IN ('filial_id','branch_id','filial') 
             LIMIT 1"
        );
        $stmt->execute([$table]);
        $col = $stmt->fetchColumn() ?: null;
        $this->filialCols[$table] = $col;
        return $col;
    }

    private function filialWhere(string $alias = 'r', string $table = 'man_checklist_execucoes'): array
    {
        $col = $this->detectarFilialColuna($table);
        if (!$col) {
            return ['', []];
        }
        $where = '';
        $params = [];
        $ids = $this->filiais;
        if (empty($ids) && $this->filialId) {
            $ids = [$this->filialId];
        }
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $prefix = $alias !== '' ? "{$alias}." : '';
            $where = " AND ({$prefix}`$col` IS NULL OR {$prefix}`$col` IN ($placeholders))";
            $params = $ids;
        }
        return [$where, $params];
    }

    public function obterExecucao(int $id): ?array
    {
        $where = 'WHERE r.id = ? AND r.empresa_id = ?';
        $params = [$id, $this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere('r');
        $where .= $filialSql;
        $params = array_merge($params, $filialParams);

        $stmt = $this->db->prepare("SELECT r.*, t.name AS template_name, t.empresa_id AS tpl_empresa_id, t.grupo_id, t.versao_atual, v.plate AS vehicle_plate, u.name AS performer, a.name AS assigned_name
            FROM man_checklist_execucoes r
            LEFT JOIN man_checklists t ON t.id = r.checklist_id
            LEFT JOIN cad_veiculos v ON v.id = r.veiculo_id
            LEFT JOIN seg_usuarios u ON u.id = r.executado_por
            LEFT JOIN seg_usuarios a ON a.id = r.atribuido_para
            $where");
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function obterCamposVersao(int $versaoId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM man_checklist_versao_itens WHERE versao_id = ? ORDER BY position ASC, id ASC');
        $stmt->execute([$versaoId]);
        return $stmt->fetchAll();
    }

    public function listarGrupos(): array
    {
        $sql = 'SELECT id, nome, tipo FROM cad_grupos WHERE empresa_id = ?';
        $params = [$this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere('', 'cad_grupos');
        $sql .= $filialSql;
        $params = array_merge($params, $filialParams);
        $sql .= ' ORDER BY nome ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obterRespostas(int $execucaoId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM man_checklist_respostas WHERE checklist_execucao_id = ?');
        $stmt->execute([$execucaoId]);
        $answers = [];
        foreach ($stmt as $row) {
            $key = $row['versao_item_id'] ?: $row['checklist_item_id'];
            $answers[$key] = $row;
        }
        return $answers;
    }

    public function obterMidiasPorCampo(int $execucaoId): array
    {
        $stmt = $this->db->prepare('SELECT m.*, COALESCE(a.versao_item_id, a.checklist_item_id) AS checklist_item_id FROM man_checklist_midias m INNER JOIN man_checklist_respostas a ON a.id = m.checklist_resposta_id WHERE a.checklist_execucao_id = ?');
        $stmt->execute([$execucaoId]);
        $mediaByField = [];
        foreach ($stmt as $media) {
            $mediaByField[$media['checklist_item_id']][] = $media;
        }
        return $mediaByField;
    }

    public function contarMidiasPorCampo(int $execucaoId, ?string $tipo = null): array
    {
        $sql = 'SELECT COALESCE(a.versao_item_id, a.checklist_item_id) AS checklist_item_id, COUNT(*) AS total
                FROM man_checklist_midias m
                INNER JOIN man_checklist_respostas a ON a.id = m.checklist_resposta_id
                WHERE a.checklist_execucao_id = ?';
        $params = [$execucaoId];
        if ($tipo) {
            $sql .= ' AND m.media_type = ?';
            $params[] = $tipo;
        }
        $sql .= ' GROUP BY COALESCE(a.versao_item_id, a.checklist_item_id)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt as $row) {
            $out[(int)$row['checklist_item_id']] = (int)$row['total'];
        }
        return $out;
    }

    public function listarExecutoes(int $userId, string $role, bool $somenteNaoConcluidoParaExec = false, array $filters = []): array
    {
        $where = 'WHERE r.empresa_id = ?';
        $params = [$this->empresaId];

        [$filialSql, $filialParams] = $this->filialWhere('r', 'man_checklist_execucoes');
        $where .= $filialSql;
        $params = array_merge($params, $filialParams);

        if ($role === 'executante') {
            $where .= ' AND (r.atribuido_para = ? OR r.executado_por = ?)';
            $params[] = $userId;
            $params[] = $userId;
            if ($somenteNaoConcluidoParaExec) {
                $where .= " AND r.status != 'concluido'";
            }
        }

        if (!empty($filters['modelo'])) {
            $where .= ' AND r.checklist_id = ?';
            $params[] = (int) $filters['modelo'];
        }
        if (!empty($filters['veiculo'])) {
            $where .= ' AND r.veiculo_id = ?';
            $params[] = (int) $filters['veiculo'];
        }
        if (!empty($filters['designado'])) {
            $where .= ' AND r.atribuido_para = ?';
            $params[] = (int) $filters['designado'];
        }
        if (!empty($filters['status'])) {
            $where .= ' AND r.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $like = '%' . $filters['q'] . '%';
            $where .= ' AND (t.name LIKE ? OR v.plate LIKE ? OR a.name LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($filters['prazo_mode'])) {
            switch ($filters['prazo_mode']) {
                case 'hoje':
                    $where .= ' AND DATE(r.prazo_em) = CURDATE()';
                    break;
                case 'semana':
                    $where .= ' AND YEARWEEK(r.prazo_em, 1) = YEARWEEK(CURDATE(), 1)';
                    break;
                case 'atrasados':
                    $where .= " AND r.prazo_em IS NOT NULL AND r.prazo_em < NOW() AND r.status <> 'concluido'";
                    break;
                case 'intervalo':
                    if (!empty($filters['prazo_de'])) {
                        $where .= ' AND DATE(r.prazo_em) >= ?';
                        $params[] = $filters['prazo_de'];
                    }
                    if (!empty($filters['prazo_ate'])) {
                        $where .= ' AND DATE(r.prazo_em) <= ?';
                        $params[] = $filters['prazo_ate'];
                    }
                    break;
            }
        } else {
            if (!empty($filters['prazo_de'])) {
                $where .= ' AND DATE(r.prazo_em) >= ?';
                $params[] = $filters['prazo_de'];
            }
            if (!empty($filters['prazo_ate'])) {
                $where .= ' AND DATE(r.prazo_em) <= ?';
                $params[] = $filters['prazo_ate'];
            }
        }

        $sql = 'SELECT r.*, t.name AS template_name, t.grupo_id, v.plate AS vehicle_plate, u.name AS performer, a.name AS assigned_name
                FROM man_checklist_execucoes r
                LEFT JOIN man_checklists t ON t.id = r.checklist_id
                LEFT JOIN cad_veiculos v ON v.id = r.veiculo_id
                LEFT JOIN seg_usuarios u ON u.id = r.executado_por
                LEFT JOIN seg_usuarios a ON a.id = r.atribuido_para
                ' . $where . '
                ORDER BY r.created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function criarExecucao(array $data): int
    {
        $colFilial = $this->detectarFilialColuna('man_checklist_execucoes');
        $cols = ['grupo_id','checklist_id','versao_id','empresa_id','veiculo_id','executado_por','atribuido_para','prazo_em','status','title','notes','iniciado_em','finalizado_em','created_at'];
        $placeholders = ['?','?','?','?','?','NULL','?','?','"pendente"','?','?','?','?','NOW()'];
        $values = [
            $data['grupo_id'],
            $data['checklist_id'],
            $data['versao_id'],
            $this->empresaId,
            $data['veiculo_id'],
            $data['atribuido_para'],
            $data['prazo_em'],
            $data['title'],
            $data['notes'],
            $data['iniciado_em'],
            $data['finalizado_em'],
        ];
        if ($colFilial) {
            array_splice($cols, 4, 0, [$colFilial]);
            array_splice($placeholders, 4, 0, ['?']);
            array_splice($values, 4, 0, [$data['filial_id']]);
        }
        $sql = 'INSERT INTO man_checklist_execucoes (' . implode(',', $cols) . ') VALUES (' . implode(',', $placeholders) . ')';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
        return (int) $this->db->lastInsertId();
    }

    public function atualizarExecucaoPendente(int $id, array $data): int
    {
        $sql = 'UPDATE man_checklist_execucoes r
                SET r.grupo_id = ?, r.checklist_id = ?, r.versao_id = ?, r.veiculo_id = ?, r.atribuido_para = ?, r.prazo_em = ?, r.title = ?, r.notes = ?, r.iniciado_em = ?, r.finalizado_em = ?, r.status = "pendente", r.executado_por = NULL, r.executando_desde = NULL, r.tempo_execucao_segundos = 0
                WHERE r.id = ? AND r.empresa_id = ? AND (r.status IS NULL OR r.status = ?) AND r.executado_por IS NULL';
        [$filialSql, $filialParams] = $this->filialWhere('r', 'man_checklist_execucoes');
        $sql .= $filialSql;
        $params = [
            $data['grupo_id'],
            $data['checklist_id'],
            $data['versao_id'],
            $data['veiculo_id'],
            $data['atribuido_para'],
            $data['prazo_em'],
            $data['title'],
            $data['notes'],
            $data['iniciado_em'],
            $data['finalizado_em'],
            $id,
            $this->empresaId,
            'pendente'
        ];
        $params = array_merge($params, $filialParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function excluirExecucaoPendente(int $id): int
    {
        $where = 'WHERE r.id = ? AND r.empresa_id = ? AND r.status = ? AND r.executado_por IS NULL';
        $params = [$id, $this->empresaId, 'pendente'];
        [$filialSql, $filialParams] = $this->filialWhere('r', 'man_checklist_execucoes');
        $where .= $filialSql;
        $params = array_merge($params, $filialParams);
        $stmt = $this->db->prepare("DELETE FROM man_checklist_execucoes r $where");
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function buscarExecucao(int $id): ?array
    {
        $where = 'WHERE r.id = ? AND r.empresa_id = ?';
        $params = [$id, $this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere('r', 'man_checklist_execucoes');
        $where .= $filialSql;
        $params = array_merge($params, $filialParams);

        $stmt = $this->db->prepare("SELECT r.*, t.name AS template_name, t.empresa_id AS tpl_empresa_id, v.plate AS vehicle_plate, u.name AS performer, a.name AS assigned_name
            FROM man_checklist_execucoes r
            LEFT JOIN man_checklists t ON t.id = r.checklist_id
            LEFT JOIN cad_veiculos v ON v.id = r.veiculo_id
            LEFT JOIN seg_usuarios u ON u.id = r.executado_por
            LEFT JOIN seg_usuarios a ON a.id = r.atribuido_para
            $where");
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listarTemplates(): array
    {
        $sql = "SELECT id, name, grupo_id FROM man_checklists WHERE empresa_id = ? AND (status IS NULL OR status = 'ativo')";
        $params = [$this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere('', 'man_checklists');
        $sql .= $filialSql;
        $params = array_merge($params, $filialParams);

        $sql .= ' ORDER BY name ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function listarVeiculos(): array
    {
        $sql = 'SELECT id, plate, model FROM cad_veiculos WHERE empresa_id = ?';
        $params = [$this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere('', 'cad_veiculos');
        $sql .= $filialSql;
        $params = array_merge($params, $filialParams);

        $sql .= ' ORDER BY plate ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function listarExecutantes(): array
    {
        $stmt = $this->db->prepare("SELECT id, name FROM seg_usuarios WHERE role = 'executante' AND empresa_id = ? ORDER BY name ASC");
        $stmt->execute([$this->empresaId]);
        return $stmt->fetchAll();
    }

    public function obterChecklistInfo(int $id): ?array
    {
        $sql = 'SELECT id, grupo_id, versao_atual, status FROM man_checklists WHERE empresa_id = ? AND id = ?';
        $params = [$this->empresaId, $id];
        [$filialSql, $filialParams] = $this->filialWhere('', 'man_checklists');
        $sql .= $filialSql;
        $params = array_merge($params, $filialParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function upsertResposta(int $execucaoId, int $itemId, string $value, array $existingAnswers): int
    {
        if (isset($existingAnswers[$itemId])) {
            $this->db->prepare('UPDATE man_checklist_respostas SET answer = ? WHERE id = ?')->execute([$value, $existingAnswers[$itemId]['id']]);
            return (int)$existingAnswers[$itemId]['id'];
        }
        $this->db->prepare('INSERT INTO man_checklist_respostas (checklist_execucao_id, checklist_item_id, versao_item_id, answer) VALUES (?, ?, ?, ?)')
            ->execute([$execucaoId, $itemId, $itemId, $value]);
        return (int)$this->db->lastInsertId();
    }

    public function inserirMidia(int $respostaId, string $filePath, string $mediaType, string $originalName): void
    {
        $this->db->prepare('INSERT INTO man_checklist_midias (checklist_resposta_id, file_path, media_type, original_name, created_at) VALUES (?, ?, ?, ?, NOW())')
            ->execute([$respostaId, $filePath, $mediaType, $originalName]);
    }

    public function removerMidias(array $ids, int $execucaoId): void
    {
        if (empty($ids)) {
            return;
        }
        $place = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT m.id, m.file_path 
                FROM man_checklist_midias m 
                INNER JOIN man_checklist_respostas r ON r.id = m.checklist_resposta_id 
                INNER JOIN man_checklist_execucoes e ON e.id = r.checklist_execucao_id 
                WHERE m.id IN ($place) AND e.id = ? AND e.empresa_id = ?";
        $params = array_merge($ids, [$execucaoId, $this->empresaId]);
        [$filialSql, $filialParams] = $this->filialWhere('e', 'man_checklist_execucoes');
        $sql .= $filialSql;
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($params, $filialParams));
        $rows = $stmt->fetchAll();
        if (!$rows) {
            return;
        }
        $idsValid = array_column($rows, 'id');
        if ($idsValid) {
            $delPlace = implode(',', array_fill(0, count($idsValid), '?'));
            $delSql = "DELETE m FROM man_checklist_midias m WHERE m.id IN ($delPlace)";
            $delStmt = $this->db->prepare($delSql);
            $delStmt->execute($idsValid);
            $base = \upload_base_dir();
            foreach ($rows as $row) {
                $pathRel = ltrim($row['file_path'], '/\\');
                $path = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pathRel);
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    public function atualizarExecucao(int $execucaoId, array $data): void
    {
        $sql = 'UPDATE man_checklist_execucoes SET status = ?, executado_por = ?, iniciado_em = ?, finalizado_em = ?, tempo_execucao_segundos = ?, executando_desde = ? WHERE id = ? AND empresa_id = ?';
        $params = [
            $data['status'],
            $data['executado_por'],
            $data['iniciado_em'],
            $data['finalizado_em'],
            $data['tempo_execucao_segundos'],
            $data['executando_desde'],
            $execucaoId,
            $this->empresaId
        ];
        $this->db->prepare($sql)->execute($params);
    }
}
