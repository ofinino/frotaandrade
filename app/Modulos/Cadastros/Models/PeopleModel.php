<?php
namespace App\Modulos\Cadastros\Models;

class PeopleModel
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
        $sql = 'SELECT id, nome_completo, nome_abreviado, email_func, telefone_func, cpf, rg, sexo, funcao_id, created_at 
                FROM cad_pessoas WHERE empresa_id = ?';
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
        $sql = 'SELECT * FROM cad_pessoas WHERE id = ? AND empresa_id = ?';
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
        $this->db->prepare('INSERT INTO cad_pessoas (empresa_id, filial_id, nome_completo, nome_abreviado, email_func, telefone_func, cpf, rg, sexo, funcao_id, data_nascimento, criado_por, name, email, phone, document, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
            ->execute([
                $this->empresaId,
                $data['filial_id'],
                $data['nome_completo'],
                $data['nome_abreviado'],
                $data['email_func'],
                $data['telefone_func'],
                $data['cpf'],
                $data['rg'],
                $data['sexo'],
                $data['funcao_id'],
                $data['data_nascimento'],
                $data['criado_por'],
                // compatibilidade com campos antigos
                $data['nome_completo'],
                $data['email_func'],
                $data['telefone_func'],
                $data['cpf'],
            ]);
    }

    public function atualizar(int $id, array $data): void
    {
        $params = [
            $data['nome_completo'],
            $data['nome_abreviado'],
            $data['email_func'],
            $data['telefone_func'],
            $data['cpf'],
            $data['rg'],
            $data['sexo'],
            $data['funcao_id'],
            $data['data_nascimento'],
            // compatibilidade com campos antigos
            $data['nome_completo'],
            $data['email_func'],
            $data['telefone_func'],
            $data['cpf'],
            $id,
            $this->empresaId
        ];
        [$filialSql, $filialParams] = $this->filialWhere();
        $sql = 'UPDATE cad_pessoas SET nome_completo = ?, nome_abreviado = ?, email_func = ?, telefone_func = ?, cpf = ?, rg = ?, sexo = ?, funcao_id = ?, data_nascimento = ?, name = ?, email = ?, phone = ?, document = ? WHERE id = ? AND empresa_id = ?' . $filialSql;
        $params = array_merge($params, $filialParams);
        $this->db->prepare($sql)->execute($params);
    }

    public function excluir(int $id): void
    {
        $params = [$id, $this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere();
        $sql = 'DELETE FROM cad_pessoas WHERE id = ? AND empresa_id = ?' . $filialSql;
        $params = array_merge($params, $filialParams);
        $this->db->prepare($sql)->execute($params);
    }
}
