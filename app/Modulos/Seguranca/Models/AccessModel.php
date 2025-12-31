<?php
namespace App\Modulos\Seguranca\Models;

class AccessModel
{
    private \PDO $db;
    private int $empresaId;

    public function __construct(\PDO $db, int $empresaId)
    {
        $this->db = $db;
        $this->empresaId = $empresaId;
    }

    public function criarPapel(string $nome, string $descricao = ''): int
    {
        $stmt = $this->db->prepare('INSERT INTO seg_papeis (nome, descricao) VALUES (?, ?)');
        $stmt->execute([$nome, $descricao]);
        return (int)$this->db->lastInsertId();
    }

    public function removerPapel(int $papelId): void
    {
        // protege papel admin
        $stmt = $this->db->prepare('DELETE FROM seg_papeis WHERE id = ? AND nome <> "admin"');
        $stmt->execute([$papelId]);
    }

    public function salvarPermissoes(int $papelId, array $features): void
    {
        $features = array_values(array_unique($features));
        $this->db->beginTransaction();
        $this->db->prepare('DELETE FROM seg_papel_permissoes WHERE papel_id = ?')->execute([$papelId]);
        if ($features) {
            $stmt = $this->db->prepare('INSERT INTO seg_papel_permissoes (papel_id, feature, allow) VALUES (?, ?, 1)');
            foreach ($features as $feat) {
                $stmt->execute([$papelId, $feat]);
            }
        }
        $this->db->commit();
    }

    public function atribuirPapel(int $userId, int $papelId): void
    {
        $this->db->prepare('INSERT IGNORE INTO seg_usuario_papel (user_id, papel_id) VALUES (?, ?)')->execute([$userId, $papelId]);
    }

    public function removerPapelUsuario(int $userId, int $papelId): void
    {
        $this->db->prepare('DELETE FROM seg_usuario_papel WHERE user_id = ? AND papel_id = ?')->execute([$userId, $papelId]);
    }

    public function atribuirFilial(int $userId, int $filialId): void
    {
        $this->db->prepare('INSERT IGNORE INTO seg_usuario_filiais (user_id, empresa_id, filial_id) VALUES (?, ?, ?)')->execute([$userId, $this->empresaId, $filialId]);
    }

    public function removerFilial(int $userId, int $filialId): void
    {
        $this->db->prepare('DELETE FROM seg_usuario_filiais WHERE user_id = ? AND empresa_id = ? AND filial_id = ?')->execute([$userId, $this->empresaId, $filialId]);
    }

    public function listarUsuarios(): array
    {
        $stmt = $this->db->prepare('SELECT id, name, email FROM seg_usuarios WHERE empresa_id = ? ORDER BY name ASC');
        $stmt->execute([$this->empresaId]);
        return $stmt->fetchAll();
    }

    public function listarPapeis(): array
    {
        return $this->db->query('SELECT id, nome, descricao FROM seg_papeis ORDER BY nome ASC')->fetchAll();
    }

    public function listarFiliais(): array
    {
        $stmt = $this->db->prepare('SELECT id, name FROM cad_filiais WHERE empresa_id = ? ORDER BY name ASC');
        $stmt->execute([$this->empresaId]);
        return $stmt->fetchAll();
    }

    public function papeisPorUsuario(): array
    {
        $rows = $this->db->query('SELECT up.user_id, r.id AS papel_id, r.nome AS papel_nome FROM seg_usuario_papel up INNER JOIN seg_papeis r ON r.id = up.papel_id')->fetchAll();
        $byUser = [];
        foreach ($rows as $row) {
            $byUser[$row['user_id']][] = ['id' => (int)$row['papel_id'], 'name' => $row['papel_nome']];
        }
        return $byUser;
    }

    public function permissoesPorPapel(): array
    {
        $rows = $this->db->query('SELECT papel_id, feature FROM seg_papel_permissoes WHERE allow = 1')->fetchAll();
        $byRole = [];
        foreach ($rows as $row) {
            $byRole[$row['papel_id']][] = $row['feature'];
        }
        return $byRole;
    }

    public function filiaisPorUsuario(): array
    {
        $stmt = $this->db->prepare('SELECT uf.user_id, b.id AS branch_id, b.name FROM seg_usuario_filiais uf INNER JOIN cad_filiais b ON b.id = uf.filial_id WHERE uf.empresa_id = ?');
        $stmt->execute([$this->empresaId]);
        $rows = $stmt->fetchAll();
        $byUser = [];
        foreach ($rows as $row) {
            $byUser[$row['user_id']][] = ['id' => (int)$row['branch_id'], 'name' => $row['name']];
        }
        return $byUser;
    }
}
