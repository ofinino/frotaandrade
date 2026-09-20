# Módulo de Combustível Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a new "Combustível" module from scratch — fuel purchase tracking (Abastecimentos, Comercial/Interno), internal fuel tanks (Meus Tanques) with purchase history, and a lightweight suppliers catalog — matching the two reference videos, and wire vehicle odometer tracking into the existing (currently dead) `cad_veiculos.nin_km_preventiva` field.

**Architecture:** New module `app/Modulos/Combustivel` (Models/Controllers/Views), following the exact structure of `app/Modulos/Manutencao`. Attachments reuse the existing generic `AnexosModel`/`man_attachments` table. All views in Tailwind matching `os/show.php`'s style.

**Tech Stack:** PHP 8, PDO/MySQL, vanilla JS, Tailwind CDN. No unit test framework — verification is `php -l` plus live HTTP checks against `http://localhost/frotaandrade` (credential available this session).

**Spec:** `docs/superpowers/specs/2026-09-19-modulo-combustivel-design.md`

## Global Constraints

- Only Tailwind is loaded (`layout.php:98`) — no Bootstrap classes in new views.
- `sanitize()` wraps all dynamic view output.
- Every controller action enforces `has_permission('combustivel.view')` (read) or `has_permission('combustivel.manage')` (write).
- Every query filters by `empresa_id` (and `filial_id` where the entity has one), matching the `filialWhere()` pattern already in `OrdensServicoModel`/`PlanosPreventivaModel`.
- One-off verification scripts use the `__` filename prefix and are deleted before each task's commit.
- `db/migrations/*.sql` files are gitignored in this repo (`*.sql` in `.gitignore`) — apply directly to the dev DB, don't fight the ignore rule.

---

## File Structure

| File | Responsibility |
|---|---|
| `db/migrations/2026-09-19-modulo-combustivel.sql` (new, gitignored) | DDL: `cad_fornecedores`, `man_fuel_tanks`, `man_fuel_tank_purchases`, `man_fuel_records`. |
| `app/Modulos/Manutencao/Models/AnexosModel.php` (modify) | Add `abastecimento` to the owner-type→subdir map. |
| `app/Modulos/Combustivel/Models/FornecedoresModel.php` (new) | `listar()`, `buscarOuCriar(string $nome): int`. |
| `app/Modulos/Combustivel/Models/FuelTanksModel.php` (new) | Tank CRUD + purchase history + stock adjustment. |
| `app/Modulos/Combustivel/Models/FuelRecordsModel.php` (new) | Abastecimento CRUD, odometer validation, medida/autonomia calc, `nin_km_preventiva` sync. |
| `app/Modulos/Combustivel/Controllers/TanquesController.php` (new) | `index`, `store`, `update`, `destroy`, `addPurchase`. |
| `app/Modulos/Combustivel/Controllers/AbastecimentosController.php` (new) | `index`, `create`, `store`, `edit`, `update`, `destroy`. |
| `app/Modulos/Combustivel/Views/tanques/index.php` (new) | Card grid + create panel + purchase panel + histórico table. |
| `app/Modulos/Combustivel/Views/abastecimentos/index.php` (new) | List + filters + summary. |
| `app/Modulos/Combustivel/Views/abastecimentos/form.php` (new) | Create/edit side panel (Comercial/Interno toggle). |
| `index.php` (modify) | New routes: `abastecimentos`, `meus_tanques`. |
| `layout.php` (modify) | New "Combustível" nav group with 2 items, in both nav-building sections. |

---

## Task 1: Database migration

**Files:**
- Create: `db/migrations/2026-09-19-modulo-combustivel.sql`

- [ ] **Step 1: Write the migration SQL**

```sql
-- db/migrations/2026-09-19-modulo-combustivel.sql

CREATE TABLE cad_fornecedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    filial_id INT NULL,
    nome VARCHAR(160) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fornecedores_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE man_fuel_tanks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    filial_id INT NULL,
    nome VARCHAR(160) NOT NULL,
    capacidade_maxima DECIMAL(10,2) NOT NULL DEFAULT 0,
    estoque_atual DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tanks_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE man_fuel_tank_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    filial_id INT NULL,
    tank_id INT NOT NULL,
    numero_nota VARCHAR(60) NULL,
    data DATE NOT NULL,
    valor_pago DECIMAL(10,2) NOT NULL DEFAULT 0,
    quantidade DECIMAL(10,2) NOT NULL,
    criado_por INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tank_purchases_tank (tank_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE man_fuel_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    filial_id INT NULL,
    veiculo_id INT NOT NULL,
    tipo ENUM('comercial','interno') NOT NULL DEFAULT 'comercial',
    fornecedor_id INT NULL,
    tank_id INT NULL,
    data_hora DATETIME NOT NULL,
    quantidade DECIMAL(10,2) NOT NULL,
    odometro DECIMAL(10,2) NOT NULL,
    combustivel_tipo ENUM('alcool','arla32','diesel','diesel_s10','gasolina','gasolina_aditivada') NOT NULL DEFAULT 'diesel',
    custo DECIMAL(10,2) NULL,
    valor_litro DECIMAL(10,4) NULL,
    tanque_cheio TINYINT(1) NOT NULL DEFAULT 0,
    observacoes TEXT NULL,
    criado_por INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fuel_records_veiculo (veiculo_id, data_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 2: Apply with a throwaway runner**

```php
<?php
// __migrate_combustivel.php
$cfg = require __DIR__ . '/config.php';
$dsn = "mysql:host={$cfg['db_host']};port={$cfg['db_port']};dbname={$cfg['db_name']};charset=utf8mb4";
$db = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$raw = file_get_contents(__DIR__ . '/db/migrations/2026-09-19-modulo-combustivel.sql');
$lines = array_filter(explode("\n", $raw), fn($l) => !preg_match('/^\s*--/', $l));
foreach (array_filter(array_map('trim', explode(';', implode("\n", $lines)))) as $stmt) {
    echo "Running: " . substr(preg_replace('/\s+/', ' ', $stmt), 0, 70) . "...\n";
    $db->exec($stmt);
}
echo "Migration applied.\n";
```

Run: `& "C:\xampp\php\php.exe" "C:\xampp\htdocs\frotaandrade\__migrate_combustivel.php"`. **Reminder from the last two migrations in this session:** strip full comment lines before splitting on `;` (a chunk that merges a `--` comment with real SQL must not be skipped wholesale), and never assign a value outside a column's current ENUM set without widening it first — not applicable here since every table is brand new, no pre-existing enum narrowing involved.

- [ ] **Step 3: Verify with a throwaway script**

```php
<?php
// __verify_combustivel_migration.php
require __DIR__ . '/bootstrap.php';
$db = db();
foreach (['cad_fornecedores','man_fuel_tanks','man_fuel_tank_purchases','man_fuel_records'] as $t) {
    echo "$t: " . ($db->query("SHOW TABLES LIKE '$t'")->fetch() ? 'OK' : 'MISSING') . "\n";
}
```

Delete both throwaway scripts after confirming all 4 print `OK`.

---

## Task 2: Extend `AnexosModel` for the new owner type

**Files:**
- Modify: `app/Modulos/Manutencao/Models/AnexosModel.php`

- [ ] **Step 1: Add the `abastecimento` subdir**

Change the `$subdir` line in `salvar()`:

```php
        $subdir = match ($ownerType) {
            'ss' => 'ss',
            'abastecimento' => 'abastecimento',
            default => 'os',
        };
```

- [ ] **Step 2: Syntax check**

Run: `& "C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Manutencao\Models\AnexosModel.php"`

- [ ] **Step 3: Commit**

```bash
cd "C:/xampp/htdocs/frotaandrade"
git add app/Modulos/Manutencao/Models/AnexosModel.php
git commit -m "Add abastecimento owner type to AnexosModel"
```

---

## Task 3: Models

**Files:**
- Create: `app/Modulos/Combustivel/Models/FornecedoresModel.php`
- Create: `app/Modulos/Combustivel/Models/FuelTanksModel.php`
- Create: `app/Modulos/Combustivel/Models/FuelRecordsModel.php`

**Interfaces:**
- `FornecedoresModel::listar(): array`, `buscarOuCriar(string $nome): int` (case-insensitive match on `nome`, creates if not found).
- `FuelTanksModel::listar(): array`, `obter(int $id): ?array`, `criar(array $data): int`, `atualizar(int $id, array $data): bool`, `excluir(int $id): bool`, `ajustarEstoque(int $id, float $delta): bool` (adds `$delta`, can be negative; fails — returns false — if it would go negative), `listarCompras(array $filters): array`, `registrarCompra(array $data): int` (inserts purchase row + calls `ajustarEstoque` in a transaction).
- `FuelRecordsModel::listar(array $filters): array` (each row includes computed `medida_percorrida`/`autonomia_media`/`valor_litro` and joined `veiculo_plate`, `fornecedor_nome`/`tank_nome`), `obter(int $id): ?array`, `ultimoOdometro(int $veiculoId, ?int $excludeRecordId = null): ?float`, `criar(array $data): int` (validates odometer, computes `valor_litro`, adjusts tank stock for `interno`, syncs `nin_km_preventiva`), `atualizar(int $id, array $data): bool` (reverts old tank delta before applying new), `excluir(int $id): bool` (reverts tank delta, re-syncs `nin_km_preventiva`).

- [ ] **Step 1: Write `FornecedoresModel`**

```php
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
```

- [ ] **Step 2: Write `FuelTanksModel`**

```php
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
        $stmt = $this->db->prepare('SELECT * FROM man_fuel_tanks WHERE empresa_id = ? ORDER BY nome ASC');
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

    public function excluir(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM man_fuel_tanks WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $this->empresaId]);
        return $stmt->rowCount() > 0;
    }

    public function ajustarEstoque(int $id, float $delta): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE man_fuel_tanks SET estoque_atual = estoque_atual + ?, updated_at = NOW()
             WHERE id = ? AND empresa_id = ? AND estoque_atual + ? >= 0'
        );
        $stmt->execute([$delta, $id, $this->empresaId, $delta]);
        return $stmt->rowCount() > 0;
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
            $this->ajustarEstoque((int)$data['tank_id'], (float)$data['quantidade']);
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
```

- [ ] **Step 3: Write `FuelRecordsModel`**

```php
<?php
namespace App\Modulos\Combustivel\Models;

class FuelRecordsModel
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

    public function listar(array $filters = []): array
    {
        $sql = "SELECT r.*, v.plate AS veiculo_plate, v.model AS veiculo_model,
                    f.nome AS fornecedor_nome, t.nome AS tank_nome, u.name AS criado_por_nome,
                    (SELECT r2.odometro FROM man_fuel_records r2
                     WHERE r2.veiculo_id = r.veiculo_id
                       AND (r2.data_hora < r.data_hora OR (r2.data_hora = r.data_hora AND r2.id < r.id))
                     ORDER BY r2.data_hora DESC, r2.id DESC LIMIT 1) AS odometro_anterior
                FROM man_fuel_records r
                LEFT JOIN cad_veiculos v ON v.id = r.veiculo_id
                LEFT JOIN cad_fornecedores f ON f.id = r.fornecedor_id
                LEFT JOIN man_fuel_tanks t ON t.id = r.tank_id
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
        }
        unset($row);
        return $rows;
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
        $stmt = $this->db->prepare('SELECT MAX(odometro) FROM man_fuel_records WHERE veiculo_id = ? AND empresa_id = ?');
        $stmt->execute([$veiculoId, $this->empresaId]);
        $max = $stmt->fetchColumn();
        if ($max !== null && $max !== false) {
            $this->db->prepare('UPDATE cad_veiculos SET nin_km_preventiva = ? WHERE id = ?')->execute([$max, $veiculoId]);
        }
    }

    public function criar(array $data, FuelTanksModel $tanksModel): int
    {
        $quantidade = (float)$data['quantidade'];
        $custo = $data['tipo'] === 'comercial' ? (float)($data['custo'] ?? 0) : null;
        $valorLitro = ($custo !== null && $quantidade > 0) ? round($custo / $quantidade, 4) : null;

        $this->db->beginTransaction();
        try {
            if ($data['tipo'] === 'interno') {
                if (!$tanksModel->ajustarEstoque((int)$data['tank_id'], -$quantidade)) {
                    throw new \RuntimeException('Estoque insuficiente no tanque selecionado.');
                }
            }
            $stmt = $this->db->prepare(
                'INSERT INTO man_fuel_records (empresa_id, filial_id, veiculo_id, tipo, fornecedor_id, tank_id, data_hora, quantidade, odometro, combustivel_tipo, custo, valor_litro, tanque_cheio, observacoes, criado_por, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
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
                $data['combustivel_tipo'],
                $custo,
                $valorLitro,
                !empty($data['tanque_cheio']) ? 1 : 0,
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
                $tanksModel->ajustarEstoque((int)$record['tank_id'], (float)$record['quantidade']);
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
```

(No `atualizar()`/edit endpoint in this first pass — the spec's manual test plan doesn't require editing an existing record's core fields, only create/delete. Cutting it is a deliberate YAGNI call: the row-menu "Editar" from the video only needs to exist once someone asks for it; add it as a follow-up if the user wants it after trying the create/delete flow. Note this explicitly in the final report.)

- [ ] **Step 4: Syntax check all three**

```bash
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Combustivel\Models\FornecedoresModel.php"
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Combustivel\Models\FuelTanksModel.php"
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Combustivel\Models\FuelRecordsModel.php"
```

- [ ] **Step 5: Verify against the real DB with a throwaway script**

```php
<?php
// __verify_combustivel_models.php
require __DIR__ . '/bootstrap.php';
$db = db();
$tanks = new App\Modulos\Combustivel\Models\FuelTanksModel($db, 1, null);
$records = new App\Modulos\Combustivel\Models\FuelRecordsModel($db, 1, null);
$fornecedores = new App\Modulos\Combustivel\Models\FornecedoresModel($db, 1, null);

$tankId = $tanks->criar(['nome' => 'Tanque Teste', 'capacidade_maxima' => 1000, 'estoque_inicial' => 500]);
echo "Tanque criado: $tankId, estoque inicial 500\n";
$tanks->registrarCompra(['tank_id' => $tankId, 'data' => date('Y-m-d'), 'valor_pago' => 100, 'quantidade' => 200, 'numero_nota' => 'NF1']);
$tank = $tanks->obter($tankId);
echo "Estoque apos compra (esperado 700): " . $tank['estoque_atual'] . "\n";

$fornecedorId = $fornecedores->buscarOuCriar('Posto Teste');
$veiculoId = (int)$db->query('SELECT id FROM cad_veiculos LIMIT 1')->fetchColumn();

$rec1 = $records->criar([
    'veiculo_id' => $veiculoId, 'tipo' => 'comercial', 'fornecedor_id' => $fornecedorId,
    'data_hora' => date('Y-m-d H:i:s', strtotime('-1 day')), 'quantidade' => 50, 'odometro' => 1000,
    'combustivel_tipo' => 'diesel', 'custo' => 300,
], $tanks);
echo "Registro 1 criado: $rec1 (valor_litro esperado 6.0000)\n";

$rec2 = $records->criar([
    'veiculo_id' => $veiculoId, 'tipo' => 'interno', 'tank_id' => $tankId,
    'data_hora' => date('Y-m-d H:i:s'), 'quantidade' => 30, 'odometro' => 1100,
    'combustivel_tipo' => 'diesel',
], $tanks);
echo "Registro 2 criado (interno): $rec2\n";
$tank = $tanks->obter($tankId);
echo "Estoque apos abastecimento interno (esperado 670): " . $tank['estoque_atual'] . "\n";

$rows = $records->listar(['veiculo_id' => $veiculoId]);
echo "Medida percorrida do registro mais recente (esperado 100): " . $rows[0]['medida_percorrida'] . "\n";
echo "Autonomia media (esperado 3.3333): " . $rows[0]['autonomia_media'] . "\n";

$veiculo = $db->query("SELECT nin_km_preventiva FROM cad_veiculos WHERE id = $veiculoId")->fetch();
echo "nin_km_preventiva sincronizado (esperado 1100): " . $veiculo['nin_km_preventiva'] . "\n";

try {
    $records->criar([
        'veiculo_id' => $veiculoId, 'tipo' => 'interno', 'tank_id' => $tankId,
        'data_hora' => date('Y-m-d H:i:s'), 'quantidade' => 999999, 'odometro' => 1200,
        'combustivel_tipo' => 'diesel',
    ], $tanks);
    echo "ERRO: deveria ter bloqueado estoque insuficiente\n";
} catch (\RuntimeException $e) {
    echo "Bloqueio de estoque insuficiente OK: " . $e->getMessage() . "\n";
}

$records->excluir($rec2, $tanks);
$tank = $tanks->obter($tankId);
echo "Estoque apos excluir registro interno (esperado 700): " . $tank['estoque_atual'] . "\n";

$db->prepare('DELETE FROM man_fuel_records WHERE id = ?')->execute([$rec1]);
$db->prepare('DELETE FROM man_fuel_tank_purchases WHERE tank_id = ?')->execute([$tankId]);
$db->prepare('DELETE FROM man_fuel_tanks WHERE id = ?')->execute([$tankId]);
$db->prepare('DELETE FROM cad_fornecedores WHERE id = ?')->execute([$fornecedorId]);
echo "Limpo.\n";
```

Run and expect every "(esperado X)" line to match. Delete the script afterwards.

- [ ] **Step 6: Commit**

```bash
cd "C:/xampp/htdocs/frotaandrade"
git add app/Modulos/Combustivel/Models/
git commit -m "Add Combustivel module models: fornecedores, tanques, abastecimentos"
```

---

## Task 4: Controllers

**Files:**
- Create: `app/Modulos/Combustivel/Controllers/TanquesController.php`
- Create: `app/Modulos/Combustivel/Controllers/AbastecimentosController.php`

- [ ] **Step 1: Write `TanquesController`**

```php
<?php
namespace App\Modulos\Combustivel\Controllers;

use App\Core\View;
use App\Modulos\Combustivel\Models\FuelTanksModel;

class TanquesController
{
    private FuelTanksModel $model;

    public function __construct()
    {
        $this->model = new FuelTanksModel(db(), current_company_id(), current_branch_id());
    }

    public function index(): void
    {
        if (!has_permission('combustivel.view')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php');
            return;
        }
        $filters = [
            'tank_id' => $_GET['tank_id'] ?? null,
            'de' => $_GET['de'] ?? null,
            'ate' => $_GET['ate'] ?? null,
        ];
        View::render('Combustivel', 'tanques/index', [
            'title' => 'Meus Tanques',
            'tanks' => $this->model->listar(),
            'compras' => $this->model->listarCompras($filters),
            'filters' => $filters,
        ]);
    }

    public function store(): void
    {
        if (!has_permission('combustivel.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=meus_tanques');
            return;
        }
        $nome = trim($_POST['nome'] ?? '');
        $capacidade = $_POST['capacidade_maxima'] ?? 0;
        $estoque = $_POST['estoque_inicial'] ?? 0;
        if ($nome === '') {
            flash('error', 'Informe o nome do tanque.');
        } else {
            $this->model->criar(['nome' => $nome, 'capacidade_maxima' => $capacidade, 'estoque_inicial' => $estoque]);
            flash('success', 'Tanque criado.');
        }
        header('Location: index.php?page=meus_tanques');
    }

    public function update(): void
    {
        if (!has_permission('combustivel.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=meus_tanques');
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $capacidade = $_POST['capacidade_maxima'] ?? 0;
        if ($id && $nome !== '' && $this->model->atualizar($id, ['nome' => $nome, 'capacidade_maxima' => $capacidade])) {
            flash('success', 'Tanque atualizado.');
        } else {
            flash('error', 'Nao foi possivel atualizar o tanque.');
        }
        header('Location: index.php?page=meus_tanques');
    }

    public function destroy(): void
    {
        if (!has_permission('combustivel.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=meus_tanques');
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($this->model->excluir($id)) {
            flash('success', 'Tanque excluido.');
        } else {
            flash('error', 'Nao foi possivel excluir o tanque.');
        }
        header('Location: index.php?page=meus_tanques');
    }

    public function addPurchase(): void
    {
        if (!has_permission('combustivel.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=meus_tanques');
            return;
        }
        $tankId = (int)($_POST['tank_id'] ?? 0);
        $quantidade = $_POST['quantidade'] ?? 0;
        if (!$tankId || !is_numeric($quantidade) || (float)$quantidade <= 0) {
            flash('error', 'Selecione um tanque e informe uma quantidade valida.');
            header('Location: index.php?page=meus_tanques');
            return;
        }
        $this->model->registrarCompra([
            'tank_id' => $tankId,
            'numero_nota' => $_POST['numero_nota'] ?? null,
            'data' => $_POST['data'] ?? date('Y-m-d'),
            'valor_pago' => $_POST['valor_pago'] ?? 0,
            'quantidade' => $quantidade,
            'criado_por' => current_user()['id'] ?? null,
        ]);
        flash('success', 'Compra de combustivel registrada.');
        header('Location: index.php?page=meus_tanques');
    }
}
```

- [ ] **Step 2: Write `AbastecimentosController`**

```php
<?php
namespace App\Modulos\Combustivel\Controllers;

use App\Core\View;
use App\Modulos\Combustivel\Models\FuelRecordsModel;
use App\Modulos\Combustivel\Models\FuelTanksModel;
use App\Modulos\Combustivel\Models\FornecedoresModel;
use App\Modulos\Manutencao\Models\AnexosModel;

class AbastecimentosController
{
    private FuelRecordsModel $model;
    private FuelTanksModel $tanksModel;
    private FornecedoresModel $fornecedoresModel;
    private AnexosModel $anexosModel;
    private \PDO $db;

    public function __construct()
    {
        $this->db = db();
        $empresaId = current_company_id();
        $filialId = current_branch_id();
        $filiais = current_branch_ids();
        $this->model = new FuelRecordsModel($this->db, $empresaId, $filialId);
        $this->tanksModel = new FuelTanksModel($this->db, $empresaId, $filialId);
        $this->fornecedoresModel = new FornecedoresModel($this->db, $empresaId, $filialId);
        $this->anexosModel = new AnexosModel($this->db, $empresaId, $filialId, $filiais);
    }

    private function listarVeiculos(): array
    {
        $stmt = $this->db->prepare('SELECT id, plate, model, txt_tipo_combustivel FROM cad_veiculos WHERE empresa_id = ? ORDER BY plate ASC');
        $stmt->execute([current_company_id()]);
        return $stmt->fetchAll();
    }

    public function index(): void
    {
        if (!has_permission('combustivel.view')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php');
            return;
        }
        $filters = [
            'veiculo_id' => $_GET['veiculo_id'] ?? null,
            'de' => $_GET['de'] ?? null,
            'ate' => $_GET['ate'] ?? null,
        ];
        View::render('Combustivel', 'abastecimentos/index', [
            'title' => 'Abastecimentos',
            'records' => $this->model->listar($filters),
            'veiculos' => $this->listarVeiculos(),
            'filters' => $filters,
        ]);
    }

    public function create(): void
    {
        if (!has_permission('combustivel.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=abastecimentos');
            return;
        }
        View::render('Combustivel', 'abastecimentos/form', [
            'title' => 'Novo Abastecimento',
            'veiculos' => $this->listarVeiculos(),
            'fornecedores' => $this->fornecedoresModel->listar(),
            'tanks' => $this->tanksModel->listar(),
        ]);
    }

    public function store(): void
    {
        if (!has_permission('combustivel.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=abastecimentos');
            return;
        }

        $tipo = ($_POST['tipo'] ?? 'comercial') === 'interno' ? 'interno' : 'comercial';
        $veiculoId = (int)($_POST['veiculo_id'] ?? 0);
        $dataHora = trim($_POST['data_hora'] ?? '');
        $quantidade = $_POST['quantidade'] ?? null;
        $odometro = $_POST['odometro'] ?? null;
        $combustivelTipo = $_POST['combustivel_tipo'] ?? 'diesel';
        $allowedCombustivel = ['alcool', 'arla32', 'diesel', 'diesel_s10', 'gasolina', 'gasolina_aditivada'];

        if (!$veiculoId || $dataHora === '' || !is_numeric($quantidade) || (float)$quantidade <= 0 || !is_numeric($odometro)) {
            flash('error', 'Preencha veiculo, data, quantidade e odometro corretamente.');
            header('Location: index.php?mod=combustivel&ctrl=Abastecimentos&action=create');
            return;
        }
        if (!in_array($combustivelTipo, $allowedCombustivel, true)) {
            flash('error', 'Tipo de combustivel invalido.');
            header('Location: index.php?mod=combustivel&ctrl=Abastecimentos&action=create');
            return;
        }

        $ultimoOdometro = $this->model->ultimoOdometro($veiculoId);
        if ($ultimoOdometro !== null && (float)$odometro < $ultimoOdometro) {
            flash('error', 'Insira um odometro valido: nao pode ser menor que o ultimo registrado (' . $ultimoOdometro . ' km).');
            header('Location: index.php?mod=combustivel&ctrl=Abastecimentos&action=create');
            return;
        }

        $payload = [
            'veiculo_id' => $veiculoId,
            'tipo' => $tipo,
            'data_hora' => str_replace('T', ' ', $dataHora) . (strlen($dataHora) === 16 ? ':00' : ''),
            'quantidade' => $quantidade,
            'odometro' => $odometro,
            'combustivel_tipo' => $combustivelTipo,
            'tanque_cheio' => !empty($_POST['tanque_cheio']),
            'observacoes' => $_POST['observacoes'] ?? null,
            'criado_por' => current_user()['id'] ?? null,
        ];

        if ($tipo === 'comercial') {
            $fornecedorNome = trim($_POST['fornecedor_nome'] ?? '');
            if ($fornecedorNome === '') {
                flash('error', 'Informe o fornecedor.');
                header('Location: index.php?mod=combustivel&ctrl=Abastecimentos&action=create');
                return;
            }
            $payload['fornecedor_id'] = $this->fornecedoresModel->buscarOuCriar($fornecedorNome);
            $payload['custo'] = $_POST['custo'] ?? 0;
        } else {
            $tankId = (int)($_POST['tank_id'] ?? 0);
            if (!$tankId) {
                flash('error', 'Selecione o tanque de origem.');
                header('Location: index.php?mod=combustivel&ctrl=Abastecimentos&action=create');
                return;
            }
            $payload['tank_id'] = $tankId;
        }

        try {
            $recordId = $this->model->criar($payload, $this->tanksModel);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            header('Location: index.php?mod=combustivel&ctrl=Abastecimentos&action=create');
            return;
        }

        if (!empty($_FILES['anexos']['name'][0] ?? '')) {
            $this->anexosModel->salvar('abastecimento', $recordId, $_FILES['anexos'], (int)(current_user()['id'] ?? 0));
        }

        flash('success', 'Abastecimento registrado.');
        header('Location: index.php?page=abastecimentos');
    }

    public function destroy(): void
    {
        if (!has_permission('combustivel.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=abastecimentos');
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($this->model->excluir($id, $this->tanksModel)) {
            flash('success', 'Abastecimento excluido.');
        } else {
            flash('error', 'Abastecimento nao encontrado.');
        }
        header('Location: index.php?page=abastecimentos');
    }
}
```

- [ ] **Step 3: Syntax check**

```bash
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Combustivel\Controllers\TanquesController.php"
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Combustivel\Controllers\AbastecimentosController.php"
```

- [ ] **Step 4: Commit**

```bash
cd "C:/xampp/htdocs/frotaandrade"
git add app/Modulos/Combustivel/Controllers/
git commit -m "Add Combustivel module controllers: tanques and abastecimentos"
```

---

## Task 5: Views

**Files:**
- Create: `app/Modulos/Combustivel/Views/tanques/index.php`
- Create: `app/Modulos/Combustivel/Views/abastecimentos/index.php`
- Create: `app/Modulos/Combustivel/Views/abastecimentos/form.php`

- [ ] **Step 1: Write `tanques/index.php`**

```php
<?php
$tanks = $tanks ?? [];
$compras = $compras ?? [];
$filters = $filters ?? [];
?>
<div class="max-w-6xl mx-auto px-4 py-6 space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-semibold text-slate-900">Meus Tanques</h2>
        <?php if (has_permission('combustivel.manage')): ?>
            <div class="flex gap-2">
                <button type="button" onclick="document.getElementById('new-tank-panel').classList.toggle('hidden')" class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Adicionar novo tanque</button>
                <button type="button" onclick="document.getElementById('purchase-panel').classList.toggle('hidden')" class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Compra de combustível</button>
            </div>
        <?php endif; ?>
    </div>

    <div id="new-tank-panel" class="hidden bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
        <h3 class="text-base font-semibold text-slate-900 mb-3">Novo tanque</h3>
        <form method="post" action="index.php?mod=combustivel&ctrl=Tanques&action=store" class="grid grid-cols-1 md:grid-cols-3 gap-3">
<?= csrf_field() ?>
            <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="nome" placeholder="Nome do tanque" required>
            <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="capacidade_maxima" type="number" step="0.01" placeholder="Capacidade máxima (L)" required>
            <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="estoque_inicial" type="number" step="0.01" placeholder="Estoque inicial (L)">
            <div class="md:col-span-3">
                <button class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Adicionar</button>
            </div>
        </form>
    </div>

    <div id="purchase-panel" class="hidden bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
        <h3 class="text-base font-semibold text-slate-900 mb-3">Compra de combustível</h3>
        <form method="post" action="index.php?mod=combustivel&ctrl=Tanques&action=addPurchase" class="space-y-3">
<?= csrf_field() ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="numero_nota" placeholder="Número da nota">
                <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="data" type="date" value="<?= date('Y-m-d') ?>" required>
                <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="valor_pago" type="number" step="0.01" placeholder="Valor pago (R$)">
                <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="quantidade" type="number" step="0.01" placeholder="Quantidade (L)" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Em que tanque deseja adicionar combustível?</label>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                    <?php foreach ($tanks as $t): ?>
                        <label class="flex items-center gap-2 text-sm rounded-lg border border-slate-200 px-3 py-2">
                            <input type="radio" name="tank_id" value="<?= sanitize($t['id']) ?>" required>
                            <?= sanitize($t['nome']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Adicionar</button>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <?php foreach ($tanks as $t): ?>
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
                <div class="flex items-start justify-between">
                    <div class="font-semibold text-slate-800"><?= sanitize($t['nome']) ?></div>
                    <?php if (has_permission('combustivel.manage')): ?>
                        <form method="post" action="index.php?mod=combustivel&ctrl=Tanques&action=destroy" onsubmit="return confirm('Excluir este tanque?');">
<?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= sanitize($t['id']) ?>">
                            <button class="text-xs text-red-500 hover:underline">Excluir</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="text-xs text-slate-500 mt-2">Estoque atual</div>
                <div class="text-lg font-semibold text-slate-900"><?= number_format((float)$t['estoque_atual'], 1, '.', '') ?>/<?= number_format((float)$t['capacidade_maxima'], 2, ',', '.') ?> L</div>
                <div class="text-xs text-slate-500 mt-2">Capacidade máxima</div>
                <div class="text-sm text-slate-700"><?= number_format((float)$t['capacidade_maxima'], 2, ',', '.') ?> L</div>
            </div>
        <?php endforeach; ?>
        <?php if (!$tanks): ?>
            <div class="col-span-full text-center text-slate-500 py-10">Nenhum tanque cadastrado.</div>
        <?php endif; ?>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm">
        <div class="p-4 border-b border-slate-100">
            <h3 class="text-base font-semibold text-slate-900">Histórico de compra</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium">Data</th>
                    <th class="px-4 py-3 font-medium">Nº da nota</th>
                    <th class="px-4 py-3 font-medium">Quantidade</th>
                    <th class="px-4 py-3 font-medium">Valor pago</th>
                    <th class="px-4 py-3 font-medium">Tanque</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($compras as $c): ?>
                    <tr>
                        <td class="px-4 py-3"><?= sanitize(date('d/m/Y', strtotime($c['data']))) ?></td>
                        <td class="px-4 py-3"><?= sanitize($c['numero_nota'] ?? '-') ?></td>
                        <td class="px-4 py-3"><?= number_format((float)$c['quantidade'], 2, ',', '.') ?> L</td>
                        <td class="px-4 py-3">R$ <?= number_format((float)$c['valor_pago'], 2, ',', '.') ?></td>
                        <td class="px-4 py-3"><?= sanitize($c['tank_nome']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$compras): ?>
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Nenhuma compra registrada.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if ($compras): ?>
            <?php
                $totalLitros = array_sum(array_column($compras, 'quantidade'));
                $totalValor = array_sum(array_column($compras, 'valor_pago'));
            ?>
            <div class="flex items-center gap-6 px-4 py-3 border-t border-slate-100 text-sm">
                <div><span class="text-slate-500">Total de litros comprados</span> <span class="font-semibold text-slate-900"><?= number_format($totalLitros, 2, ',', '.') ?> L</span></div>
                <div><span class="text-slate-500">Valor total</span> <span class="font-semibold text-slate-900">R$ <?= number_format($totalValor, 2, ',', '.') ?></span></div>
            </div>
        <?php endif; ?>
    </div>
</div>
```

- [ ] **Step 2: Write `abastecimentos/index.php`**

```php
<?php
$records = $records ?? [];
$veiculos = $veiculos ?? [];
$filters = $filters ?? [];
$combustivelLabels = [
    'alcool' => 'Álcool', 'arla32' => 'Arla 32', 'diesel' => 'Diesel',
    'diesel_s10' => 'Diesel S10', 'gasolina' => 'Gasolina', 'gasolina_aditivada' => 'Gasolina aditivada',
];
?>
<div class="max-w-7xl mx-auto px-4 py-6 space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-semibold text-slate-900">Abastecimentos</h2>
        <?php if (has_permission('combustivel.manage')): ?>
            <a class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800" href="index.php?mod=combustivel&ctrl=Abastecimentos&action=create">Novo Abastecimento</a>
        <?php endif; ?>
    </div>

    <form method="get" action="index.php" class="flex flex-wrap items-center gap-2">
        <input type="hidden" name="page" value="abastecimentos">
        <select class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="veiculo_id">
            <option value="">Todos os veículos</option>
            <?php foreach ($veiculos as $v): ?>
                <option value="<?= sanitize($v['id']) ?>" <?= ($filters['veiculo_id'] ?? '') == $v['id'] ? 'selected' : '' ?>><?= sanitize($v['plate']) ?></option>
            <?php endforeach; ?>
        </select>
        <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" type="date" name="de" value="<?= sanitize($filters['de'] ?? '') ?>">
        <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" type="date" name="ate" value="<?= sanitize($filters['ate'] ?? '') ?>">
        <button class="inline-flex items-center rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrar</button>
        <a class="text-sm text-slate-500 hover:underline" href="index.php?page=abastecimentos">Limpar</a>
    </form>

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Data</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Veículo</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Quantidade</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Medição</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Fornecedor/Tanque</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Criado por</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Valor do litro</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Custo total</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Medida percorrida</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Autonomia média</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($records as $r): ?>
                    <tr>
                        <td class="px-3 py-3 whitespace-nowrap"><?= sanitize(date('d/m/y \à\s H:i', strtotime($r['data_hora']))) ?></td>
                        <td class="px-3 py-3 whitespace-nowrap"><?= sanitize($r['veiculo_plate'] ?? '-') ?></td>
                        <td class="px-3 py-3 whitespace-nowrap"><?= number_format((float)$r['quantidade'], 2, ',', '.') ?> L</td>
                        <td class="px-3 py-3 whitespace-nowrap"><?= number_format((float)$r['odometro'], 1, ',', '.') ?> km</td>
                        <td class="px-3 py-3 whitespace-nowrap"><?= sanitize($r['tipo'] === 'interno' ? ($r['tank_nome'] ?? '-') : ($r['fornecedor_nome'] ?? '-')) ?></td>
                        <td class="px-3 py-3 whitespace-nowrap"><?= sanitize($r['criado_por_nome'] ?? '-') ?></td>
                        <td class="px-3 py-3 whitespace-nowrap"><?= $r['valor_litro'] !== null ? number_format((float)$r['valor_litro'], 2, ',', '.') . ' R$/L' : '-,--' ?></td>
                        <td class="px-3 py-3 whitespace-nowrap"><?= $r['custo'] !== null ? 'R$ ' . number_format((float)$r['custo'], 2, ',', '.') : '-' ?></td>
                        <td class="px-3 py-3 whitespace-nowrap"><?= $r['medida_percorrida'] !== null ? number_format((float)$r['medida_percorrida'], 1, ',', '.') . ' km' : '-,-- km' ?></td>
                        <td class="px-3 py-3 whitespace-nowrap"><?= $r['autonomia_media'] !== null ? number_format((float)$r['autonomia_media'], 2, ',', '.') . ' km/L' : '-,-- km/L' ?></td>
                        <td class="px-3 py-3 whitespace-nowrap">
                            <?php if (has_permission('combustivel.manage')): ?>
                                <form method="post" action="index.php?mod=combustivel&ctrl=Abastecimentos&action=destroy" onsubmit="return confirm('Excluir este abastecimento?');">
<?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= sanitize($r['id']) ?>">
                                    <button class="text-xs text-red-500 hover:underline">Excluir</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$records): ?>
                    <tr><td colspan="11" class="px-4 py-8 text-center text-slate-500">Nenhum abastecimento encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if ($records): ?>
            <?php
                $totalCusto = array_sum(array_column($records, 'custo'));
                $custosValidos = array_values(array_filter($records, fn($r) => $r['custo'] !== null && (float)$r['quantidade'] > 0));
                $precoMedio = $custosValidos ? array_sum(array_map(fn($r) => (float)$r['custo'], $custosValidos)) / array_sum(array_map(fn($r) => (float)$r['quantidade'], $custosValidos)) : 0;
            ?>
            <div class="flex flex-wrap items-center gap-6 px-4 py-3 border-t border-slate-100 text-sm">
                <div><span class="text-slate-500">Total de abastecimentos</span> <span class="font-semibold text-slate-900"><?= count($records) ?></span></div>
                <div><span class="text-slate-500">Custo total</span> <span class="font-semibold text-slate-900">R$ <?= number_format((float)$totalCusto, 2, ',', '.') ?></span></div>
                <div><span class="text-slate-500">Preço médio por litro</span> <span class="font-semibold text-slate-900">R$ <?= number_format($precoMedio, 2, ',', '.') ?>/L</span></div>
            </div>
        <?php endif; ?>
    </div>
</div>
```

- [ ] **Step 3: Write `abastecimentos/form.php`**

```php
<?php
$veiculos = $veiculos ?? [];
$fornecedores = $fornecedores ?? [];
$tanks = $tanks ?? [];
?>
<div class="max-w-xl mx-auto px-4 py-6">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 space-y-5">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-900">Novo Abastecimento</h2>
            <a class="text-slate-400 hover:text-slate-600" href="index.php?page=abastecimentos">✕</a>
        </div>

        <form method="post" action="index.php?mod=combustivel&ctrl=Abastecimentos&action=store" enctype="multipart/form-data" class="space-y-4">
<?= csrf_field() ?>
            <div class="inline-flex rounded-lg border border-slate-200 overflow-hidden text-sm" id="tipo-toggle">
                <button type="button" data-tipo="comercial" class="px-4 py-2 font-semibold bg-slate-900 text-white">Comercial</button>
                <button type="button" data-tipo="interno" class="px-4 py-2 font-semibold bg-white text-slate-700">Interno</button>
            </div>
            <input type="hidden" name="tipo" id="tipo" value="comercial">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Data e hora *</label>
                    <input class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="data_hora" type="datetime-local" value="<?= date('Y-m-d\TH:i') ?>" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Veículo *</label>
                    <select class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="veiculo_id" required>
                        <option value="">Selecione um veículo</option>
                        <?php foreach ($veiculos as $v): ?>
                            <option value="<?= sanitize($v['id']) ?>"><?= sanitize($v['plate']) ?> <?= sanitize($v['model']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="campo-fornecedor" class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Fornecedor *</label>
                    <input class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="fornecedor_nome" list="fornecedores-list" placeholder="Digite ou selecione um fornecedor">
                    <datalist id="fornecedores-list">
                        <?php foreach ($fornecedores as $f): ?>
                            <option value="<?= sanitize($f['nome']) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div id="campo-tanque" class="md:col-span-2 hidden">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tanque de origem *</label>
                    <select class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="tank_id">
                        <option value="">Selecione um tanque</option>
                        <?php foreach ($tanks as $t): ?>
                            <option value="<?= sanitize($t['id']) ?>"><?= sanitize($t['nome']) ?> (<?= number_format((float)$t['estoque_atual'], 1, ',', '.') ?> L disponíveis)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Quantidade (L) *</label>
                    <input class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="quantidade" type="number" step="0.01" min="0.01" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Odômetro (km) *</label>
                    <input class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="odometro" type="number" step="0.1" min="0" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Combustível</label>
                    <select class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="combustivel_tipo">
                        <option value="alcool">Álcool</option>
                        <option value="arla32">Arla 32</option>
                        <option value="diesel" selected>Diesel</option>
                        <option value="diesel_s10">Diesel S10</option>
                        <option value="gasolina">Gasolina</option>
                        <option value="gasolina_aditivada">Gasolina aditivada</option>
                    </select>
                </div>
                <div id="campo-custo">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Custo (R$) *</label>
                    <input class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="custo" type="number" step="0.01" min="0">
                </div>

                <div class="md:col-span-2 flex items-center gap-2">
                    <input type="checkbox" id="tanque_cheio" name="tanque_cheio" value="1" class="rounded border-slate-300">
                    <label for="tanque_cheio" class="text-sm text-slate-700">Tanque cheio</label>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Observações</label>
                    <textarea class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="observacoes" rows="2"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Foto da nota fiscal</label>
                    <input type="file" name="anexos[]" accept=".pdf,.png,.jpg,.jpeg" class="w-full text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Foto do odômetro</label>
                    <input type="file" name="anexos[]" accept=".png,.jpg,.jpeg" class="w-full text-sm">
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                <a class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" href="index.php?page=abastecimentos">Cancelar</a>
                <button class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Adicionar</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const toggle = document.getElementById('tipo-toggle');
    const input = document.getElementById('tipo');
    const campoFornecedor = document.getElementById('campo-fornecedor');
    const campoTanque = document.getElementById('campo-tanque');
    const campoCusto = document.getElementById('campo-custo');

    toggle.querySelectorAll('button').forEach((btn) => {
        btn.addEventListener('click', function() {
            const tipo = this.dataset.tipo;
            input.value = tipo;
            toggle.querySelectorAll('button').forEach((b) => {
                b.classList.toggle('bg-slate-900', b === this);
                b.classList.toggle('text-white', b === this);
                b.classList.toggle('bg-white', b !== this);
                b.classList.toggle('text-slate-700', b !== this);
            });
            campoFornecedor.classList.toggle('hidden', tipo !== 'comercial');
            campoCusto.classList.toggle('hidden', tipo !== 'comercial');
            campoTanque.classList.toggle('hidden', tipo !== 'interno');
        });
    });
})();
</script>
```

- [ ] **Step 4: Syntax check**

```bash
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Combustivel\Views\tanques\index.php"
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Combustivel\Views\abastecimentos\index.php"
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Combustivel\Views\abastecimentos\form.php"
```

- [ ] **Step 5: Commit**

```bash
cd "C:/xampp/htdocs/frotaandrade"
git add app/Modulos/Combustivel/Views/
git commit -m "Add Combustivel module views: tanques and abastecimentos"
```

---

## Task 6: Routes and navigation

**Files:**
- Modify: `index.php`
- Modify: `layout.php`

- [ ] **Step 1: Add routes to `index.php`**

Add next to the other module route entries:

```php
            'abastecimentos' => ['mod' => 'combustivel', 'ctrl' => 'Abastecimentos', 'action' => 'index', 'actions' => ['index', 'create', 'store', 'destroy']],
            'meus_tanques' => ['mod' => 'combustivel', 'ctrl' => 'Tanques', 'action' => 'index', 'actions' => ['index', 'store', 'update', 'destroy', 'addPurchase']],
```

Confirm the router's module-name-to-folder resolution matches `combustivel` → `Combustivel` (check how `mod=manutencao` resolves to the `Manutencao` folder/controller namespace elsewhere in `index.php`'s dispatch code, and mirror it exactly — likely a `ucfirst()` or explicit map).

- [ ] **Step 2: Add nav items to `layout.php` (first array, ~line 26)**

```php
            if ((is_admin() || has_permission('combustivel.view'))) {
                $navItems[] = ['page' => 'abastecimentos', 'label' => 'Abastecimentos', 'href' => 'index.php?page=abastecimentos', 'perm' => true, 'icon' => 'list'];
                $navItems[] = ['page' => 'meus_tanques', 'label' => 'Meus tanques', 'href' => 'index.php?page=meus_tanques', 'perm' => true, 'icon' => 'truck'];
            }
```

- [ ] **Step 3: Add the "Combustível" context group to the second nav array (~line 495, after the `manutencao` context)**

```php
                    [
                        'id' => 'combustivel',
                        'label' => 'Combustivel',
                        'icon' => 'truck',
                        'items' => [
                            ['page' => 'abastecimentos', 'label' => 'Abastecimentos', 'icon' => 'list'],
                            ['page' => 'meus_tanques', 'label' => 'Meus tanques', 'icon' => 'truck'],
                        ],
                    ],
```

- [ ] **Step 4: Syntax check**

```bash
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\index.php"
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\layout.php"
```

- [ ] **Step 5: Commit**

```bash
cd "C:/xampp/htdocs/frotaandrade"
git add index.php layout.php
git commit -m "Wire up routes and nav for the Combustivel module"
```

---

## Task 7: Live verification and cleanup

**Files:** none (verification only)

- [ ] **Step 1:** Using the session's login credential and the curl cookie-jar pattern used throughout this session: login → `index.php?page=meus_tanques` create a tank → register a tank purchase, confirm stock increases → `index.php?mod=combustivel&ctrl=Abastecimentos&action=create` create a Comercial record with a new fornecedor name → create an Interno record drawing from the tank created above → confirm the tank's stock decreased by the right amount → attempt an Interno record exceeding stock, confirm it's blocked → attempt an odometer lower than the last recorded one, confirm it's blocked → `index.php?page=abastecimentos` confirm medida percorrida/autonomia média show correctly for the second record of the same vehicle → confirm `cad_veiculos.nin_km_preventiva` was updated → delete the Interno record, confirm tank stock is restored.
- [ ] **Step 2:** Delete every test row created during verification (tanks, purchases, fuel records, test fornecedor, and any uploaded attachment files) with a throwaway script, then delete the script.
- [ ] **Step 3:** Re-run `php -l` across every file touched/created in this plan in one pass.
- [ ] **Step 4:** Report pass/fail against the spec's 10-point manual test plan to the user before calling this done. Explicitly flag that "editar abastecimento" was cut from this pass (create/delete only) as a deliberate scope decision, in case the user wants it added.
