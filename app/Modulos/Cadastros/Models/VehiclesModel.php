<?php
namespace App\Modulos\Cadastros\Models;

class VehiclesModel
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
        $sql = 'SELECT * FROM cad_veiculos WHERE empresa_id = ?';
        $params = [$this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere();
        $sql .= $filialSql;
        $params = array_merge($params, $filialParams);
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obter(int $id): ?array
    {
        $sql = 'SELECT * FROM cad_veiculos WHERE id = ? AND empresa_id = ?';
        $params = [$id, $this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere();
        $sql .= $filialSql;
        $params = array_merge($params, $filialParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function criar(array $data): void
    {
        $this->db->prepare('INSERT INTO cad_veiculos (empresa_id, filial_id, plate, model, year, notes, criado_por, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())')
            ->execute([
                $this->empresaId,
                $data['filial_id'],
                $data['plate'],
                $data['model'],
                $data['year'],
                $data['notes'],
                $data['criado_por'],
            ]);
    }

    public function atualizar(int $id, array $data): void
    {
        $params = [$data['plate'], $data['model'], $data['year'], $data['notes'], $id, $this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere();
        $sql = 'UPDATE cad_veiculos SET plate = ?, model = ?, year = ?, notes = ? WHERE id = ? AND empresa_id = ?' . $filialSql;
        $params = array_merge($params, $filialParams);
        $this->db->prepare($sql)->execute($params);
    }

    public function excluir(int $id): void
    {
        $params = [$id, $this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere('filial');
        $sql = 'DELETE FROM cad_veiculos WHERE id = ? AND empresa_id = ?' . $filialSql;
        $params = array_merge($params, $filialParams);
        $this->db->prepare($sql)->execute($params);
    }
}
