<?php
namespace App\Modulos\Manutencao\Models;

class ReportsModel
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

    private function filialWhere(string $alias = 'r'): array
    {
        $where = '';
        $params = [];
        $ids = $this->filiais;
        if (empty($ids) && $this->filialId) {
            $ids = [$this->filialId];
        }
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $where = " AND ({$alias}.filial_id IS NULL OR {$alias}.filial_id IN ($placeholders))";
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

        $stmt = $this->db->prepare("SELECT r.*, t.name AS template_name, t.empresa_id AS tpl_empresa_id, v.plate AS vehicle_plate, v.model AS vehicle_model,
            u.name AS performer_name, a.name AS assigned_name,
            ver.numero AS versao_numero, ver.created_at AS versao_data
            FROM man_checklist_execucoes r
            LEFT JOIN man_checklists t ON t.id = r.checklist_id
            LEFT JOIN man_checklist_versoes ver ON ver.id = r.versao_id
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

    public function obterRespostas(int $execucaoId): array
    {
        $answersStmt = $this->db->prepare('SELECT * FROM man_checklist_respostas WHERE checklist_execucao_id = ?');
        $answersStmt->execute([$execucaoId]);
        $answers = [];
        foreach ($answersStmt as $row) {
            $key = $row['versao_item_id'] ?: $row['checklist_item_id'];
            $answers[$key] = $row;
        }
        return $answers;
    }

    public function obterMidiasPorCampo(int $execucaoId): array
    {
        $mediaStmt = $this->db->prepare('SELECT m.*, COALESCE(a.versao_item_id, a.checklist_item_id) AS checklist_item_id FROM man_checklist_midias m INNER JOIN man_checklist_respostas a ON a.id = m.checklist_resposta_id WHERE a.checklist_execucao_id = ?');
        $mediaStmt->execute([$execucaoId]);
        $mediaByField = [];
        foreach ($mediaStmt as $media) {
            $mediaByField[$media['checklist_item_id']][] = $media;
        }
        return $mediaByField;
    }
}
