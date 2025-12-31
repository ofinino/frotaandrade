<?php
namespace App\Modulos\Seguranca\Models;

class UsersModel
{
    private \PDO $db;
    private int $empresaId;
    private ?int $filialId;

    public function __construct(\PDO $db, int $empresaId, ?int $filialId = null)
    {
        $this->db = $db;
        $this->empresaId = $empresaId;
        $this->filialId = $filialId;
    }

    public function listarUsuarios(?int $filialFilter = null, bool $isAdmin = false): array
    {
        $sql = 'SELECT * FROM seg_usuarios WHERE empresa_id = ?';
        $params = [$this->empresaId];
        if (!$isAdmin && $filialFilter) {
            $sql .= ' AND (filial_id IS NULL OR filial_id = ?)';
            $params[] = $filialFilter;
        }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obterUsuario(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM seg_usuarios WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $this->empresaId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function emailExiste(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM seg_usuarios WHERE email = ? AND empresa_id = ?');
        $stmt->execute([$email, $this->empresaId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function criar(array $data): int
    {
        $this->db->prepare('INSERT INTO seg_usuarios (empresa_id, filial_id, name, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())')
            ->execute([
                $this->empresaId,
                $data['filial_id'],
                $data['name'],
                $data['email'],
                $data['password_hash'],
                $data['role'],
            ]);
        return (int)$this->db->lastInsertId();
    }

    public function atualizar(int $id, array $data): void
    {
        $params = [$data['name'], $data['email'], $data['role'], $id, $this->empresaId];
        $sql = 'UPDATE seg_usuarios SET name = ?, email = ?, role = ? WHERE id = ? AND empresa_id = ?';
        if (!empty($data['password_hash'])) {
            $sql = 'UPDATE seg_usuarios SET name = ?, email = ?, role = ?, password_hash = ? WHERE id = ? AND empresa_id = ?';
            $params = [$data['name'], $data['email'], $data['role'], $data['password_hash'], $id, $this->empresaId];
        }
        $this->db->prepare($sql)->execute($params);
    }

    public function excluir(int $id): void
    {
        $this->db->prepare('DELETE FROM seg_usuarios WHERE id = ? AND empresa_id = ?')->execute([$id, $this->empresaId]);
    }
}
