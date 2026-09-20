<?php
namespace App\Modulos\Combustivel\Models;

class FuelTypesModel
{
    private \PDO $db;
    private int $empresaId;
    private ?int $filialId;

    public function __construct(\PDO $db, int $empresaId, ?int $filialId)
    {
        $this->db = $db;
        $this->empresaId = $empresaId;
        $this->filialId = $filialId;
    }

    public function listar(bool $apenasAtivos = false): array
    {
        $sql = 'SELECT * FROM man_fuel_types WHERE empresa_id = ?';
        $params = [$this->empresaId];
        if ($apenasAtivos) {
            $sql .= ' AND ativo = 1';
        }
        $sql .= ' ORDER BY nome ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obter(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM man_fuel_types WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $this->empresaId]);
        return $stmt->fetch() ?: null;
    }

    public function criar(string $nome): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO man_fuel_types (empresa_id, filial_id, nome, ativo, created_at) VALUES (?, ?, ?, 1, NOW())'
        );
        $stmt->execute([$this->empresaId, $this->filialId, $nome]);
        return (int)$this->db->lastInsertId();
    }

    public function toggleAtivo(int $id, bool $ativo): bool
    {
        $stmt = $this->db->prepare('UPDATE man_fuel_types SET ativo = ?, updated_at = NOW() WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$ativo ? 1 : 0, $id, $this->empresaId]);
        return $stmt->rowCount() > 0;
    }
}
