<?php
namespace App\Modulos\Combustivel\Models;

class FuelRecordsModel
{
    private \PDO $db;
    private int $empresaId;
    private ?int $filialId;

    public const AUTONOMIA_MINIMA_PLAUSIVEL = 1.0;

    public function __construct(\PDO $db, int $empresaId, ?int $filialId)
    {
        $this->db = $db;
        $this->empresaId = $empresaId;
        $this->filialId = $filialId;
    }

    /**
     * Busca todos os registros que batem com os filtros (sem paginacao), ja com
     * medida_percorrida/autonomia_media/inconsistente calculados. Paginacao e
     * resumo (totais) sao derivados disso em PHP - o volume de abastecimentos
     * de uma frota nao justifica replicar esse calculo em SQL agregado.
     */
    public function listarTudo(array $filters = []): array
    {
        $sql = "SELECT r.*, v.plate AS veiculo_plate, v.model AS veiculo_model,
                    f.nome AS fornecedor_nome, t.nome AS tank_nome, u.name AS criado_por_nome,
                    ft.nome AS combustivel_nome,
                    (SELECT r2.odometro FROM man_fuel_records r2
                     WHERE r2.veiculo_id = r.veiculo_id
                       AND (r2.data_hora < r.data_hora OR (r2.data_hora = r.data_hora AND r2.id < r.id))
                     ORDER BY r2.data_hora DESC, r2.id DESC LIMIT 1) AS odometro_anterior,
                    (SELECT COUNT(*) FROM man_attachments a WHERE a.owner_type = 'abastecimento' AND a.owner_id = r.id) AS anexos_count
                FROM man_fuel_records r
                LEFT JOIN cad_veiculos v ON v.id = r.veiculo_id
                LEFT JOIN cad_fornecedores f ON f.id = r.fornecedor_id
                LEFT JOIN man_fuel_tanks t ON t.id = r.tank_id
                LEFT JOIN man_fuel_types ft ON ft.id = r.combustivel_tipo_id
                LEFT JOIN seg_usuarios u ON u.id = r.criado_por
                WHERE r.empresa_id = ?";
        $params = [$this->empresaId];
        if (!empty($filters['veiculo_id'])) {
            $sql .= ' AND r.veiculo_id = ?';
            $params[] = $filters['veiculo_id'];
        }
        if (!empty($filters['de'])) {
            $sql .= ' AND r.data_hora >= ?';
            $params[] = $filters['de'] . ' 00:00:00';
        }
        if (!empty($filters['ate'])) {
            $sql .= ' AND r.data_hora <= ?';
            $params[] = $filters['ate'] . ' 23:59:59';
        }
        $sql .= ' ORDER BY r.data_hora DESC, r.id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['medida_percorrida'] = null;
            $row['autonomia_media'] = null;
            if ($row['odometro_anterior'] !== null) {
                $percorrida = (float)$row['odometro'] - (float)$row['odometro_anterior'];
                if ($percorrida > 0) {
                    $row['medida_percorrida'] = $percorrida;
                    if ((float)$row['quantidade'] > 0) {
                        $row['autonomia_media'] = $percorrida / (float)$row['quantidade'];
                    }
                }
            }
            $row['inconsistente'] = $row['autonomia_media'] !== null && $row['autonomia_media'] < self::AUTONOMIA_MINIMA_PLAUSIVEL;
        }
        unset($row);

        if (!empty($filters['apenas_inconsistentes'])) {
            $rows = array_values(array_filter($rows, fn($r) => $r['inconsistente']));
        }

        return $rows;
    }

    public function resumo(array $rows): array
    {
        $custosValidos = array_values(array_filter($rows, fn($r) => $r['custo'] !== null));
        $totalCusto = array_sum(array_map(fn($r) => (float)$r['custo'], $custosValidos));
        $totalQuantidadeComCusto = array_sum(array_map(fn($r) => (float)$r['quantidade'], $custosValidos));
        return [
            'total_abastecimentos' => count($rows),
            'custo_total' => $totalCusto,
            'preco_medio' => $totalQuantidadeComCusto > 0 ? $totalCusto / $totalQuantidadeComCusto : 0.0,
            'quantidade_total' => array_sum(array_map(fn($r) => (float)$r['quantidade'], $rows)),
            'total_inconsistentes' => count(array_filter($rows, fn($r) => $r['inconsistente'])),
        ];
    }

    public function listarAnexosPorRegistros(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $place = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT owner_id, file_path, original_name, mime_type FROM man_attachments
             WHERE owner_type = 'abastecimento' AND owner_id IN ($place) ORDER BY uploaded_at ASC"
        );
        $stmt->execute($ids);
        $byRecord = [];
        foreach ($stmt as $row) {
            $byRecord[(int)$row['owner_id']][] = $row;
        }
        return $byRecord;
    }

    public function obter(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM man_fuel_records WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $this->empresaId]);
        return $stmt->fetch() ?: null;
    }

    public function ultimoOdometro(int $veiculoId, ?int $excludeRecordId = null): ?float
    {
        $sql = 'SELECT MAX(odometro) FROM man_fuel_records WHERE veiculo_id = ? AND empresa_id = ?';
        $params = [$veiculoId, $this->empresaId];
        if ($excludeRecordId) {
            $sql .= ' AND id != ?';
            $params[] = $excludeRecordId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $max = $stmt->fetchColumn();
        if ($max !== null && $max !== false) {
            return (float)$max;
        }
        $stmt2 = $this->db->prepare('SELECT nin_km_preventiva FROM cad_veiculos WHERE id = ?');
        $stmt2->execute([$veiculoId]);
        $fallback = $stmt2->fetchColumn();
        return $fallback !== null && $fallback !== false ? (float)$fallback : null;
    }

    private function sincronizarOdometroVeiculo(int $veiculoId): void
    {
        $stmt = $this->db->prepare(
            'SELECT MAX(odometro) FROM man_fuel_records WHERE veiculo_id = ? AND empresa_id = ? AND atualizar_odometro = 1'
        );
        $stmt->execute([$veiculoId, $this->empresaId]);
        $max = $stmt->fetchColumn();
        if ($max !== null && $max !== false) {
            $this->db->prepare('UPDATE cad_veiculos SET nin_km_preventiva = ? WHERE id = ?')->execute([$max, $veiculoId]);
        }
    }

    public function criar(array $data, FuelTanksModel $tanksModel): int
    {
        $quantidade = (float)$data['quantidade'];
        $custo = null;
        $valorLitro = null;

        if ($data['tipo'] === 'comercial') {
            $custo = (float)($data['custo'] ?? 0);
            $valorLitro = $quantidade > 0 ? round($custo / $quantidade, 4) : null;
        }

        $this->db->beginTransaction();
        try {
            if ($data['tipo'] === 'interno') {
                if (!$tanksModel->ajustarEstoque((int)$data['tank_id'], -$quantidade)) {
                    throw new \RuntimeException('Estoque insuficiente no tanque selecionado.');
                }
                $valorLitro = $tanksModel->precoPorLitroEm((int)$data['tank_id'], $data['data_hora']);
                $custo = $valorLitro !== null ? round($valorLitro * $quantidade, 2) : null;
            }
            $stmt = $this->db->prepare(
                'INSERT INTO man_fuel_records (empresa_id, filial_id, veiculo_id, tipo, fornecedor_id, tank_id, data_hora, quantidade, odometro, combustivel_tipo_id, custo, valor_litro, tanque_cheio, atualizar_odometro, observacoes, criado_por, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $this->empresaId,
                $this->filialId,
                $data['veiculo_id'],
                $data['tipo'],
                $data['fornecedor_id'] ?? null,
                $data['tank_id'] ?? null,
                $data['data_hora'],
                $quantidade,
                $data['odometro'],
                $data['combustivel_tipo_id'],
                $custo,
                $valorLitro,
                !empty($data['tanque_cheio']) ? 1 : 0,
                !empty($data['atualizar_odometro']) ? 1 : 0,
                $data['observacoes'] ?? null,
                $data['criado_por'] ?? null,
            ]);
            $id = (int)$this->db->lastInsertId();
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $this->sincronizarOdometroVeiculo((int)$data['veiculo_id']);
        return $id;
    }

    public function excluir(int $id, FuelTanksModel $tanksModel): bool
    {
        $record = $this->obter($id);
        if (!$record) {
            return false;
        }
        $this->db->beginTransaction();
        try {
            if ($record['tipo'] === 'interno' && $record['tank_id']) {
                if (!$tanksModel->ajustarEstoque((int)$record['tank_id'], (float)$record['quantidade'])) {
                    throw new \RuntimeException('Nao e possivel excluir: devolver esse combustivel faria o tanque ultrapassar a capacidade maxima. Aumente a capacidade do tanque antes de excluir.');
                }
            }
            $stmt = $this->db->prepare('DELETE FROM man_fuel_records WHERE id = ? AND empresa_id = ?');
            $stmt->execute([$id, $this->empresaId]);
            $ok = $stmt->rowCount() > 0;
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
        $this->sincronizarOdometroVeiculo((int)$record['veiculo_id']);
        return $ok;
    }
}
