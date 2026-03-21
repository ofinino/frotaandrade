<?php
namespace App\Modulos\Manutencao\Models;

class AuditoriaModel
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

    private function filialWhere(string $alias = 'a'): array
    {
        $where = '';
        $params = [];
        $ids = $this->filiais;
        if (empty($ids) && $this->filialId) {
            $ids = [$this->filialId];
        }
        if ($ids) {
            $place = implode(',', array_fill(0, count($ids), '?'));
            $prefix = $alias ? "{$alias}." : '';
            $where = " AND ({$prefix}filial_id IS NULL OR {$prefix}filial_id IN ($place))";
            $params = $ids;
        }
        return [$where, $params];
    }

    public function registrar(string $entityType, int $entityId, string $action, $before, $after, ?int $actorId = null): void
    {
        $this->db->prepare(
            'INSERT INTO man_audit_log (empresa_id, filial_id, actor_user_id, entity_type, entity_id, action, before_json, after_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        )->execute([
            $this->empresaId,
            $this->filialId,
            $actorId,
            $entityType,
            $entityId,
            $action,
            $before !== null ? json_encode($before) : null,
            $after !== null ? json_encode($after) : null,
        ]);
    }

    public function listar(string $entityType, int $entityId): array
    {
        $sql = 'SELECT * FROM man_audit_log a WHERE a.empresa_id = ? AND a.entity_type = ? AND a.entity_id = ?';
        $params = [$this->empresaId, $entityType, $entityId];
        [$filialSql, $filialParams] = $this->filialWhere('a');
        $sql .= $filialSql;
        $params = array_merge($params, $filialParams);
        $sql .= ' ORDER BY a.created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
