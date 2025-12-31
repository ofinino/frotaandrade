<?php
namespace App\Modulos\Cadastros\Models;

class GroupsModel
{
    private \PDO $db;
    private int $empresaId;
    private ?int $filialId;
    private array $filiais;

    public function __construct(\PDO $db, int $empresaId, ?int $filialId = null, array $filiais = [])
    {
        $this->db = $db;
        $this->empresaId = $empresaId;
        $this->filialId = $filialId;
        $this->filiais = $filiais;
    }

    private function filialWhere(?string $alias = null): array
    {
        $where = '';
        $params = [];
        $ids = $this->filiais;
        if (empty($ids) && $this->filialId) {
            $ids = [$this->filialId];
        }
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $prefix = $alias ? "{$alias}." : '';
            $where = " AND ({$prefix}filial_id IS NULL OR {$prefix}filial_id IN ($placeholders))";
            $params = $ids;
        }
        return [$where, $params];
    }

    public function listar(): array
    {
        $sql = 'SELECT id, nome, tipo, filial_id, created_at FROM cad_grupos WHERE empresa_id = ?';
        $params = [$this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere();
        $sql .= $filialSql;
        $params = array_merge($params, $filialParams);
        $sql .= ' ORDER BY nome ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function criar(array $data): void
    {
        $this->db->prepare('INSERT INTO cad_grupos (empresa_id, filial_id, nome, tipo, created_at) VALUES (?, ?, ?, ?, NOW())')
            ->execute([
                $this->empresaId,
                $data['filial_id'],
                $data['nome'],
                $data['tipo']
            ]);
    }

    public function obter(int $id): ?array
    {
        $sql = 'SELECT * FROM cad_grupos WHERE id = ? AND empresa_id = ?';
        $params = [$id, $this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere();
        $sql .= $filialSql;
        $params = array_merge($params, $filialParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function atualizar(int $id, array $data): int
    {
        $sql = 'UPDATE cad_grupos SET nome = ?, tipo = ? WHERE id = ? AND empresa_id = ?';
        $params = [$data['nome'], $data['tipo'], $id, $this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere();
        $sql .= $filialSql;
        $params = array_merge($params, $filialParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function excluir(int $id): int
    {
        $sql = 'DELETE FROM cad_grupos WHERE id = ? AND empresa_id = ?';
        $params = [$id, $this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere();
        $sql .= $filialSql;
        $params = array_merge($params, $filialParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}
