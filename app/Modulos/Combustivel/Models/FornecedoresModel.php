<?php
namespace App\Modulos\Combustivel\Models;

class FornecedoresModel
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

    public function listar(): array
    {
        $stmt = $this->db->prepare('SELECT * FROM cad_fornecedores WHERE empresa_id = ? ORDER BY nome ASC');
        $stmt->execute([$this->empresaId]);
        return $stmt->fetchAll();
    }

    public function buscarOuCriar(string $nome): int
    {
        $nome = trim($nome);
        $stmt = $this->db->prepare('SELECT id FROM cad_fornecedores WHERE empresa_id = ? AND LOWER(nome) = LOWER(?) LIMIT 1');
        $stmt->execute([$this->empresaId, $nome]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }
        $insert = $this->db->prepare('INSERT INTO cad_fornecedores (empresa_id, filial_id, nome, created_at) VALUES (?, ?, ?, NOW())');
        $insert->execute([$this->empresaId, $this->filialId, $nome]);
        return (int)$this->db->lastInsertId();
    }
}
