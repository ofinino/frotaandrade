<?php
namespace App\Modulos\Combustivel\Models;

class FuelTanksModel
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
        $stmt = $this->db->prepare('SELECT * FROM man_fuel_tanks WHERE empresa_id = ? ORDER BY ativo DESC, nome ASC');
        $stmt->execute([$this->empresaId]);
        return $stmt->fetchAll();
    }

    public function listarAtivos(): array
    {
        $stmt = $this->db->prepare('SELECT * FROM man_fuel_tanks WHERE empresa_id = ? AND ativo = 1 ORDER BY nome ASC');
        $stmt->execute([$this->empresaId]);
        return $stmt->fetchAll();
    }

    public function obter(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM man_fuel_tanks WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $this->empresaId]);
        return $stmt->fetch() ?: null;
    }

    public function criar(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO man_fuel_tanks (empresa_id, filial_id, nome, capacidade_maxima, estoque_atual, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $this->empresaId,
            $this->filialId,
            $data['nome'],
            $data['capacidade_maxima'],
            $data['estoque_inicial'] ?? 0,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function atualizar(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE man_fuel_tanks SET nome = ?, capacidade_maxima = ?, updated_at = NOW() WHERE id = ? AND empresa_id = ?'
        );
        $stmt->execute([$data['nome'], $data['capacidade_maxima'], $id, $this->empresaId]);
        return $stmt->rowCount() > 0;
    }

    public function temHistorico(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT
            (SELECT COUNT(*) FROM man_fuel_tank_purchases WHERE tank_id = ?) +
            (SELECT COUNT(*) FROM man_fuel_records WHERE tank_id = ?)'
        );
        $stmt->execute([$id, $id]);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    public function excluir(int $id): bool
    {
        if ($this->temHistorico($id)) {
            throw new \RuntimeException('Este tanque tem compras ou abastecimentos registrados e nao pode ser excluido. Desative-o para manter o historico.');
        }
        $stmt = $this->db->prepare('DELETE FROM man_fuel_tanks WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $this->empresaId]);
        return $stmt->rowCount() > 0;
    }

    public function toggleAtivo(int $id, bool $ativo): bool
    {
        $stmt = $this->db->prepare('UPDATE man_fuel_tanks SET ativo = ?, updated_at = NOW() WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$ativo ? 1 : 0, $id, $this->empresaId]);
        return $stmt->rowCount() > 0;
    }

    public function ajustarEstoque(int $id, float $delta): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE man_fuel_tanks SET estoque_atual = estoque_atual + ?, updated_at = NOW()
             WHERE id = ? AND empresa_id = ? AND estoque_atual + ? >= 0 AND estoque_atual + ? <= capacidade_maxima'
        );
        $stmt->execute([$delta, $id, $this->empresaId, $delta, $delta]);
        return $stmt->rowCount() > 0;
    }

    public function precoPorLitroEm(int $tankId, string $dataHora): ?float
    {
        $data = substr($dataHora, 0, 10);
        $stmt = $this->db->prepare(
            'SELECT valor_pago, quantidade FROM man_fuel_tank_purchases
             WHERE tank_id = ? AND empresa_id = ? AND data <= ?
             ORDER BY data DESC, id DESC LIMIT 1'
        );
        $stmt->execute([$tankId, $this->empresaId, $data]);
        $purchase = $stmt->fetch();
        if (!$purchase || (float)$purchase['quantidade'] <= 0) {
            return null;
        }
        return round((float)$purchase['valor_pago'] / (float)$purchase['quantidade'], 4);
    }

    public function listarCompras(array $filters = []): array
    {
        $sql = 'SELECT p.*, t.nome AS tank_nome FROM man_fuel_tank_purchases p
                INNER JOIN man_fuel_tanks t ON t.id = p.tank_id
                WHERE p.empresa_id = ?';
        $params = [$this->empresaId];
        if (!empty($filters['tank_id'])) {
            $sql .= ' AND p.tank_id = ?';
            $params[] = $filters['tank_id'];
        }
        if (!empty($filters['de'])) {
            $sql .= ' AND p.data >= ?';
            $params[] = $filters['de'];
        }
        if (!empty($filters['ate'])) {
            $sql .= ' AND p.data <= ?';
            $params[] = $filters['ate'];
        }
        $sql .= ' ORDER BY p.data DESC, p.id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function registrarCompra(array $data): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO man_fuel_tank_purchases (empresa_id, filial_id, tank_id, numero_nota, data, valor_pago, quantidade, criado_por, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $this->empresaId,
                $this->filialId,
                $data['tank_id'],
                $data['numero_nota'] ?? null,
                $data['data'],
                $data['valor_pago'] ?? 0,
                $data['quantidade'],
                $data['criado_por'] ?? null,
            ]);
            $id = (int)$this->db->lastInsertId();
            if (!$this->ajustarEstoque((int)$data['tank_id'], (float)$data['quantidade'])) {
                throw new \RuntimeException('Essa compra faria o tanque ultrapassar a capacidade maxima. Reduza a quantidade ou aumente a capacidade do tanque.');
            }
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function obterCompra(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM man_fuel_tank_purchases WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $this->empresaId]);
        return $stmt->fetch() ?: null;
    }

    public function atualizarCompra(int $id, array $data): bool
    {
        $compra = $this->obterCompra($id);
        if (!$compra) {
            return false;
        }
        $novaQuantidade = (float)$data['quantidade'];
        $delta = $novaQuantidade - (float)$compra['quantidade'];

        $this->db->beginTransaction();
        try {
            if ($delta !== 0.0 && !$this->ajustarEstoque((int)$compra['tank_id'], $delta)) {
                $msg = $delta < 0
                    ? 'Nao e possivel reduzir essa compra: parte do combustivel ja foi consumida do tanque.'
                    : 'Nao e possivel aumentar essa compra: o tanque ultrapassaria a capacidade maxima.';
                throw new \RuntimeException($msg);
            }
            $stmt = $this->db->prepare(
                'UPDATE man_fuel_tank_purchases SET numero_nota = ?, data = ?, valor_pago = ?, quantidade = ? WHERE id = ? AND empresa_id = ?'
            );
            $stmt->execute([
                $data['numero_nota'] ?? null,
                $data['data'],
                $data['valor_pago'] ?? 0,
                $novaQuantidade,
                $id,
                $this->empresaId,
            ]);
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function excluirCompra(int $id): bool
    {
        $compra = $this->obterCompra($id);
        if (!$compra) {
            return false;
        }
        $this->db->beginTransaction();
        try {
            if (!$this->ajustarEstoque((int)$compra['tank_id'], -(float)$compra['quantidade'])) {
                throw new \RuntimeException('Nao e possivel excluir essa compra: parte do combustivel ja foi consumida do tanque.');
            }
            $stmt = $this->db->prepare('DELETE FROM man_fuel_tank_purchases WHERE id = ? AND empresa_id = ?');
            $stmt->execute([$id, $this->empresaId]);
            $ok = $stmt->rowCount() > 0;
            $this->db->commit();
            return $ok;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
