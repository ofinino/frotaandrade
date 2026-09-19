<?php
namespace App\Modulos\Manutencao\Models;

class ManutencaoServicosModel
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
        $sql = 'SELECT * FROM man_maintenance_services WHERE empresa_id = ?';
        $params = [$this->empresaId];
        if ($apenasAtivos) {
            $sql .= ' AND ativo = 1';
        }
        $sql .= ' ORDER BY nome ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function criar(string $nome): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO man_maintenance_services (empresa_id, filial_id, nome, ativo, created_at) VALUES (?, ?, ?, 1, NOW())'
        );
        $stmt->execute([$this->empresaId, $this->filialId, $nome]);
        return (int)$this->db->lastInsertId();
    }

    public function toggleAtivo(int $id, bool $ativo): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE man_maintenance_services SET ativo = ?, updated_at = NOW() WHERE id = ? AND empresa_id = ?'
        );
        $stmt->execute([$ativo ? 1 : 0, $id, $this->empresaId]);
        return $stmt->rowCount() > 0;
    }
}
