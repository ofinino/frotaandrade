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

    public function listar(array $filters = []): array
    {
        $sql = 'SELECT * FROM cad_veiculos WHERE empresa_id = ?';
        $params = [$this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere();
        $sql .= $filialSql;
        $params = array_merge($params, $filialParams);
        if ($filters) {
            if ($filters['ativo'] === '1') {
                $sql .= " AND (csn_ativo IS NULL OR csn_ativo NOT IN (0,'0'))";
            } elseif ($filters['ativo'] === '0') {
                $sql .= " AND (csn_ativo IN (0,'0'))";
            }
            if ($filters['ano_de'] !== '') {
                $sql .= ' AND year >= ?';
                $params[] = $filters['ano_de'];
            }
            if ($filters['ano_ate'] !== '') {
                $sql .= ' AND year <= ?';
                $params[] = $filters['ano_ate'];
            }
            if ($filters['modelo'] !== '') {
                $sql .= ' AND model LIKE ?';
                $params[] = '%' . $filters['modelo'] . '%';
            }
            if ($filters['frota'] !== '') {
                $sql .= ' AND (plate LIKE ? OR txt_placa_veiculo LIKE ?)';
                $params[] = '%' . $filters['frota'] . '%';
                $params[] = '%' . $filters['frota'] . '%';
            }
            if ($filters['tipo'] !== '') {
                $sql .= ' AND txt_tipo_veiculo LIKE ?';
                $params[] = '%' . $filters['tipo'] . '%';
            }
        }
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
        $this->db->prepare('INSERT INTO cad_veiculos (empresa_id, filial_id, txt_placa_veiculo, txt_chassis, nin_lotacao_sentado, model, year, notes, csn_ativo, criado_por, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
            ->execute([
                $this->empresaId,
                $data['filial_id'] ?? null,
                $data['txt_placa_veiculo'] ?? '',
                $data['txt_chassis'] ?? '',
                $data['nin_lotacao_sentado'] ?? null,
                $data['model'] ?? '',
                $data['year'] ?? '',
                $data['notes'] ?? '',
                $data['csn_ativo'] ?? 1,
                $data['criado_por'] ?? null,
            ]);
    }

    public function atualizar(int $id, array $data): void
    {
        $params = [
            $data['txt_placa_veiculo'] ?? '',
            $data['txt_chassis'] ?? '',
            $data['nin_lotacao_sentado'] ?? null,
            $data['model'] ?? '',
            $data['year'] ?? '',
            $data['notes'] ?? '',
            $data['csn_ativo'] ?? 1,
            $id,
            $this->empresaId
        ];
        [$filialSql, $filialParams] = $this->filialWhere();
        $sql = 'UPDATE cad_veiculos SET txt_placa_veiculo = ?, txt_chassis = ?, nin_lotacao_sentado = ?, model = ?, year = ?, notes = ?, csn_ativo = ? WHERE id = ? AND empresa_id = ?' . $filialSql;
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
