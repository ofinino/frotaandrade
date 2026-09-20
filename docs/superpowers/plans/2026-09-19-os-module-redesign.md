# OS Module Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the Ordens de Serviço (OS) module — new status pipeline, a status-based Kanban view, a richer creation form (medição, disponibilidade, prioridade, fornecedor, responsável, pendências, serviços com valor, anexos), and rebrand the existing "Vencimentos Preventiva" screen as "Lembretes".

**Architecture:** Extend the existing MVC-ish structure under `app/Modulos/Manutencao` (`OrdensServicoModel`/`Controller`, views in `app/Modulos/Manutencao/Views/os/`). No new framework, no new tables beyond one (`man_work_order_pendencias`). All new/rewritten HTML uses Tailwind utility classes (the only CSS framework actually loaded — see `layout.php:98`), matching the style already used in `os/show.php` (`bg-white border border-slate-200 rounded-2xl shadow-sm`, slate palette).

**Tech Stack:** PHP 8 (no framework), PDO/MySQL, vanilla JS, Tailwind CDN. **No unit test framework exists in this repo** (no PHPUnit/composer). Verification per task therefore uses: `php -l` for syntax, and small one-off PHP scripts run with `C:\xampp\php\php.exe` against the real dev database (`C:\xampp\htdocs\frotaandrade\config.php` — DB is **not** production, disposable/test data only) to prove model/controller logic against real data. The final task is a manual browser smoke test.

**Spec:** `docs/superpowers/specs/2026-09-19-os-module-redesign-design.md`

## Global Constraints

- DB is not production — schema changes may be applied directly, no backward-compat shims needed.
- No Bootstrap CSS is loaded anywhere in this app; only Tailwind (CDN, `layout.php:98`). All new/rewritten markup must use Tailwind utility classes, not Bootstrap classes (`btn`, `card`, `form-select`, `row`, etc.).
- `sanitize()` must wrap all dynamic output in views (existing project convention, seen throughout `os/show.php`).
- All new POST-handling controller actions must call `has_permission('os.manage')` (or `os.view` for read) exactly like existing actions in `OrdensServicoController`, and must accept the existing CSRF pattern (`csrf_field()` in forms, `csrf_token()` checked by the framework — confirm via `index.php` router, do not bypass it).
- Multi-tenant scoping: every new query must filter by `empresa_id` (and branch via `filialWhere()`/`detectarFilialColuna()`) exactly like existing methods in `OrdensServicoModel`.
- One-off verification scripts written during this plan must be created under the project root with an `__` prefix (matching the convention already used in this session, e.g. `__inspect_os.php`) and deleted before the final commit of each task — never left in the repo.

---

## File Structure

| File | Responsibility |
|---|---|
| `db/migrations/2026-09-19-os-module-redesign.sql` (new) | DDL: new status enum, new `man_work_orders` columns, `man_work_order_items` enum+valor, new `man_work_order_pendencias` table. Historical record only (no migration runner exists or is being added). |
| `app/Modulos/Manutencao/Models/OrdensServicoModel.php` (modify) | New fields in `criar()`/`atualizar()`, `responsavelValido()`, pendencias CRUD, `valor` support in `addItem()`. |
| `app/Modulos/Manutencao/Controllers/OrdensServicoController.php` (modify) | Updated `allowedStatus`, new field validation in `store()`, pendencias/serviços/anexos processing on create, new `addPendencia()`/`resolvePendencia()`/`changeStatusDrag()` actions, anexo upload in `show()`. |
| `app/Modulos/Manutencao/Models/AnexosModel.php` (modify) | Allow `application/pdf`. |
| `app/Modulos/Manutencao/Views/os/create.php` (rewrite) | New Tailwind creation form with all new fields, dynamic pendências/serviços lists, anexos input. |
| `app/Modulos/Manutencao/Views/os/show.php` (modify) | New status labels/colors, "Serviços" (was "Itens da OS") with valor + total, Pendências section, anexo upload form. |
| `app/Modulos/Manutencao/Views/os/index.php` (modify) | New status labels/colors, new "Kanban" view mode (3-way toggle Kanban/Agenda/Lista), drag-and-drop status board. |
| `index.php` (modify) | Register new controller actions (`addPendencia`, `resolvePendencia`, `changeStatusDrag`) in the `os` route's allowed actions list. |
| `app/Modulos/Manutencao/Models/PlanosPreventivaModel.php` (modify) | `listarVencimentos()` returns counts per status alongside rows. |
| `app/Modulos/Manutencao/Controllers/PlanosPreventivaController.php` (modify) | Pass counts to view. |
| `app/Modulos/Manutencao/Views/preventiva/vencimentos.php` (rewrite) | "Lembretes" heading, tabs with counts, "há X dias"/"restam Y km" formatting. |
| `layout.php` (modify) | Menu label "Vencimentos" → "Lembretes" (both nav locations, `layout.php:31` and `layout.php:493`). |

---

## Task 1: Database migration

**Files:**
- Create: `db/migrations/2026-09-19-os-module-redesign.sql`
- Verify with (throwaway, delete after): `__migrate_os.php`, `__verify_migration.php`

**Interfaces:**
- Produces: new `man_work_orders` columns `medicao_tipo`, `disponibilidade`, `prioridade`, `motivo_abertura`, `tipo_fornecedor`, `fornecedor`, `responsavel_id`; new status enum values `solicitacao,aguardando_agendamento,em_execucao,analise_aprovacao,encerrada,cancelada`; `man_work_order_items.status` enum widened to `pendente,em_andamento,concluido,bloqueado,cancelado`; new `man_work_order_items.valor` column; new table `man_work_order_pendencias(id, empresa_id, filial_id, work_order_id, titulo, resolvida, created_at, updated_at)`.

- [ ] **Step 1: Write the migration SQL file**

```sql
-- db/migrations/2026-09-19-os-module-redesign.sql

-- 1. Remap existing status values while the old enum members still exist.
UPDATE man_work_orders SET status = CASE status
    WHEN 'rascunho' THEN 'solicitacao'
    WHEN 'aprovada' THEN 'aguardando_agendamento'
    WHEN 'programada' THEN 'aguardando_agendamento'
    WHEN 'aguardando_pecas' THEN 'em_execucao'
    WHEN 'concluida' THEN 'analise_aprovacao'
    ELSE status
END;

-- 2. Narrow the enum to the new pipeline.
ALTER TABLE man_work_orders
    MODIFY COLUMN status ENUM('solicitacao','aguardando_agendamento','em_execucao','analise_aprovacao','encerrada','cancelada') NOT NULL DEFAULT 'solicitacao';

-- 3. New OS fields.
ALTER TABLE man_work_orders
    ADD COLUMN medicao_tipo ENUM('odometro','horimetro') NOT NULL DEFAULT 'odometro' AFTER veiculo_id,
    ADD COLUMN disponibilidade ENUM('disponivel','indisponivel') NULL AFTER medicao_tipo,
    ADD COLUMN prioridade ENUM('baixa','media','alta','urgente') NOT NULL DEFAULT 'media' AFTER disponibilidade,
    ADD COLUMN motivo_abertura VARCHAR(255) NULL AFTER prioridade,
    ADD COLUMN tipo_fornecedor ENUM('interno','externo') NOT NULL DEFAULT 'externo' AFTER motivo_abertura,
    ADD COLUMN fornecedor VARCHAR(160) NULL AFTER tipo_fornecedor,
    ADD COLUMN responsavel_id INT NULL AFTER fornecedor;

-- 4. Align item status enum with values the code already uses, add valor.
ALTER TABLE man_work_order_items
    MODIFY COLUMN status ENUM('pendente','em_andamento','concluido','bloqueado','cancelado') NOT NULL DEFAULT 'pendente',
    ADD COLUMN valor DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER prioridade;

-- 5. Pendencias table.
CREATE TABLE man_work_order_pendencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    filial_id INT NULL,
    work_order_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    resolvida TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_wo_pendencias (work_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 2: Write a throwaway PHP runner and apply it**

```php
<?php
// __migrate_os.php
$cfg = require __DIR__ . '/config.php';
$dsn = "mysql:host={$cfg['db_host']};port={$cfg['db_port']};dbname={$cfg['db_name']};charset=utf8mb4";
$db = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$sql = file_get_contents(__DIR__ . '/db/migrations/2026-09-19-os-module-redesign.sql');
foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    if ($stmt === '' || str_starts_with($stmt, '--')) continue;
    echo "Running: " . substr($stmt, 0, 60) . "...\n";
    $db->exec($stmt);
}
echo "Migration applied.\n";
```

Run: `& "C:\xampp\php\php.exe" "C:\xampp\htdocs\frotaandrade\__migrate_os.php"`
Expected: prints each statement prefix and "Migration applied." with no exceptions.

- [ ] **Step 3: Verify the schema and data with a throwaway check script**

```php
<?php
// __verify_migration.php
$cfg = require __DIR__ . '/config.php';
$dsn = "mysql:host={$cfg['db_host']};port={$cfg['db_port']};dbname={$cfg['db_name']};charset=utf8mb4";
$db = new PDO($dsn, $cfg['db_user'], $cfg['db_pass']);
$stmt = $db->query("SELECT status, COUNT(*) c FROM man_work_orders GROUP BY status");
foreach ($stmt as $r) { echo $r['status'] . ' => ' . $r['c'] . "\n"; }
$stmt = $db->query("SHOW COLUMNS FROM man_work_orders LIKE 'responsavel_id'");
echo "responsavel_id exists: " . ($stmt->fetch() ? 'yes' : 'NO') . "\n";
$stmt = $db->query("SHOW TABLES LIKE 'man_work_order_pendencias'");
echo "pendencias table exists: " . ($stmt->fetch() ? 'yes' : 'NO') . "\n";
```

Run: `& "C:\xampp\php\php.exe" "C:\xampp\htdocs\frotaandrade\__verify_migration.php"`
Expected: only new status values printed (`solicitacao`, `aguardando_agendamento`, `analise_aprovacao`, etc. — no `rascunho`/`aprovada`/`programada`/`concluida`), both existence checks print "yes".

- [ ] **Step 4: Delete the throwaway scripts**

```bash
rm "C:/xampp/htdocs/frotaandrade/__migrate_os.php" "C:/xampp/htdocs/frotaandrade/__verify_migration.php"
```

- [ ] **Step 5: Commit**

```bash
cd "C:/xampp/htdocs/frotaandrade"
git add db/migrations/2026-09-19-os-module-redesign.sql
git commit -m "Add DB migration for OS module redesign (status pipeline, new fields, pendencias table)"
```

---

## Task 2: Model layer — `OrdensServicoModel`

**Files:**
- Modify: `app/Modulos/Manutencao/Models/OrdensServicoModel.php`

**Interfaces:**
- Consumes: existing `$this->db`, `$this->empresaId`, `$this->filialId`, `$this->filialWhere()`, `$this->normalizarFilialId()` (all already defined in this class).
- Produces: `criar(array $payload): int` now persists `medicao_tipo, disponibilidade, prioridade, motivo_abertura, tipo_fornecedor, fornecedor, responsavel_id`. `atualizar(int $id, array $payload): int` same fields. `responsavelValido(int $userId): bool`. `addItem(int $osId, array $data): int` accepts `valor`. `addPendencia(int $osId, string $titulo): int`. `resolverPendencia(int $pendenciaId, int $osId, bool $resolvida): bool`. `listarPendencias(int $osId): array`. `obter(int $id): ?array` includes `$row['pendencias']`.

- [ ] **Step 1: Extend `criar()` to persist the new fields**

Replace the `criar()` method body (lines 386-431) with:

```php
    public function criar(array $payload): int
    {
        $filialDestino = $this->normalizarFilialId($payload['filial_id'] ?? null) ?? $this->normalizarFilialId($this->filialId);
        $maxAttempts = 3;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $codigo = $payload['codigo'] ?? null;
            $this->db->beginTransaction();
            try {
                if (!$codigo) {
                    $codigo = $this->gerarCodigo();
                }
                $stmt = $this->db->prepare(
                    'INSERT INTO man_work_orders (empresa_id, filial_id, codigo, veiculo_id, status, odometro_abertura, odometro_fechamento, aberta_por, aberta_em, iniciada_em, concluida_em, encerrada_em, observacoes, medicao_tipo, disponibilidade, prioridade, motivo_abertura, tipo_fornecedor, fornecedor, responsavel_id, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute([
                    $this->empresaId,
                    $filialDestino,
                    $codigo,
                    $payload['veiculo_id'] ?? null,
                    $payload['status'] ?? 'solicitacao',
                    $payload['odometro_abertura'] ?? null,
                    $payload['odometro_fechamento'] ?? null,
                    $payload['aberta_por'] ?? null,
                    $payload['aberta_em'] ?? date('Y-m-d H:i:s'),
                    $payload['iniciada_em'] ?? null,
                    $payload['concluida_em'] ?? null,
                    $payload['encerrada_em'] ?? null,
                    $payload['observacoes'] ?? null,
                    $payload['medicao_tipo'] ?? 'odometro',
                    $payload['disponibilidade'] ?? null,
                    $payload['prioridade'] ?? 'media',
                    $payload['motivo_abertura'] ?? null,
                    $payload['tipo_fornecedor'] ?? 'externo',
                    $payload['fornecedor'] ?? null,
                    $payload['responsavel_id'] ?? null,
                ]);
                $id = (int)$this->db->lastInsertId();
                $this->db->commit();
                return $id;
            } catch (\PDOException $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                $isDuplicate = $e->getCode() === '23000';
                if (!$isDuplicate || $attempt >= $maxAttempts || isset($payload['codigo'])) {
                    throw $e;
                }
            }
        }
        throw new \RuntimeException('Nao foi possivel gerar um codigo de OS unico.');
    }
```

- [ ] **Step 2: Extend `atualizar()` to persist the new fields**

Replace `atualizar()` (lines 433-451) with:

```php
    public function atualizar(int $id, array $payload): int
    {
        $sql = 'UPDATE man_work_orders o SET o.veiculo_id = ?, o.status = ?, o.odometro_abertura = ?, o.odometro_fechamento = ?, o.observacoes = ?,
                    o.medicao_tipo = ?, o.disponibilidade = ?, o.prioridade = ?, o.motivo_abertura = ?, o.tipo_fornecedor = ?, o.fornecedor = ?, o.responsavel_id = ?,
                    o.updated_at = NOW() WHERE o.id = ? AND o.empresa_id = ?';
        [$filialSql, $filialParams] = $this->filialWhere('o');
        $sql .= $filialSql;
        $params = [
            $payload['veiculo_id'] ?? null,
            $payload['status'] ?? 'solicitacao',
            $payload['odometro_abertura'] ?? null,
            $payload['odometro_fechamento'] ?? null,
            $payload['observacoes'] ?? null,
            $payload['medicao_tipo'] ?? 'odometro',
            $payload['disponibilidade'] ?? null,
            $payload['prioridade'] ?? 'media',
            $payload['motivo_abertura'] ?? null,
            $payload['tipo_fornecedor'] ?? 'externo',
            $payload['fornecedor'] ?? null,
            $payload['responsavel_id'] ?? null,
            $id,
            $this->empresaId,
        ];
        $params = array_merge($params, $filialParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
```

- [ ] **Step 3: Add `responsavelValido()` next to `executorValido()`**

Insert immediately after the existing `executorValido()` method (after line 646, before `vincularServiceRequests()`):

```php
    public function responsavelValido(int $userId): bool
    {
        $sql = 'SELECT 1 FROM seg_usuarios WHERE id = ? AND empresa_id = ?';
        $params = [$userId, $this->empresaId];
        [$filialSql, $filialParams] = $this->filialWhere('', 'seg_usuarios');
        $sql .= $filialSql;
        $params = array_merge($params, $filialParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }
```

- [ ] **Step 4: Add `valor` support to `addItem()`**

Replace `addItem()` (lines 507-523) with:

```php
    public function addItem(int $osId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO man_work_order_items (empresa_id, filial_id, work_order_id, titulo, descricao, status, prioridade, valor, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $this->empresaId,
            $this->normalizarFilialId($this->filialId),
            $osId,
            $data['titulo'],
            $data['descricao'] ?? null,
            $data['status'] ?? 'pendente',
            $data['prioridade'] ?? 'media',
            (float)($data['valor'] ?? 0),
        ]);
        return (int)$this->db->lastInsertId();
    }
```

- [ ] **Step 5: Add pendencias CRUD methods**

Insert after `listarItens()` (after line 585):

```php
    public function addPendencia(int $osId, string $titulo): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO man_work_order_pendencias (empresa_id, filial_id, work_order_id, titulo, resolvida, created_at)
             VALUES (?, ?, ?, ?, 0, NOW())'
        );
        $stmt->execute([
            $this->empresaId,
            $this->normalizarFilialId($this->filialId),
            $osId,
            $titulo,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function resolverPendencia(int $pendenciaId, int $osId, bool $resolvida): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE man_work_order_pendencias SET resolvida = ?, updated_at = NOW()
             WHERE id = ? AND work_order_id = ? AND empresa_id = ?'
        );
        $stmt->execute([$resolvida ? 1 : 0, $pendenciaId, $osId, $this->empresaId]);
        return $stmt->rowCount() > 0;
    }

    public function listarPendencias(int $osId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM man_work_order_pendencias WHERE work_order_id = ? ORDER BY created_at ASC');
        $stmt->execute([$osId]);
        return $stmt->fetchAll();
    }
```

- [ ] **Step 6: Include pendencias in `obter()`**

In `obter()` (around line 379-383), add after the `service_requests` line:

```php
        $row['pendencias'] = $this->listarPendencias($id);
```

- [ ] **Step 7: Syntax check**

Run: `& "C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Manutencao\Models\OrdensServicoModel.php"`
Expected: `No syntax errors detected`

- [ ] **Step 8: Verify against the real DB with a throwaway script**

```php
<?php
// __verify_model.php
require __DIR__ . '/bootstrap.php';
$db = db();
$model = new App\Modulos\Manutencao\Models\OrdensServicoModel($db, 1, null, []);
$osId = $model->criar([
    'veiculo_id' => (int)$db->query('SELECT id FROM cad_veiculos LIMIT 1')->fetchColumn(),
    'status' => 'solicitacao',
    'motivo_abertura' => 'Teste de migracao',
    'tipo_fornecedor' => 'interno',
    'prioridade' => 'alta',
]);
echo "OS criada: $osId\n";
$itemId = $model->addItem($osId, ['titulo' => 'Servico teste', 'valor' => 150.50]);
echo "Item criado: $itemId\n";
$pendId = $model->addPendencia($osId, 'Pendencia teste');
echo "Pendencia criada: $pendId\n";
$os = $model->obter($osId);
echo "Valor do item lido: " . $os['items'][0]['valor'] . "\n";
echo "Pendencias: " . count($os['pendencias']) . "\n";
$db->prepare('DELETE FROM man_work_order_pendencias WHERE work_order_id = ?')->execute([$osId]);
$db->prepare('DELETE FROM man_work_order_items WHERE work_order_id = ?')->execute([$osId]);
$db->prepare('DELETE FROM man_work_orders WHERE id = ?')->execute([$osId]);
echo "Limpo.\n";
```

Run: `& "C:\xampp\php\php.exe" "C:\xampp\htdocs\frotaandrade\__verify_model.php"`
Expected: prints created ids, `Valor do item lido: 150.5`, `Pendencias: 1`, `Limpo.` with no errors. (Check `bootstrap.php` for the exact `db()` helper signature first — if it requires a session/login context, adapt the script to call whatever bootstraps `db()` in `bin/run_preventiva.php`, which already runs standalone DB code outside a web request.)

- [ ] **Step 9: Delete the throwaway script**

```bash
rm "C:/xampp/htdocs/frotaandrade/__verify_model.php"
```

- [ ] **Step 10: Commit**

```bash
cd "C:/xampp/htdocs/frotaandrade"
git add app/Modulos/Manutencao/Models/OrdensServicoModel.php
git commit -m "Extend OrdensServicoModel with new OS fields, pendencias CRUD, and item valor"
```

---

## Task 3: Controller layer — `OrdensServicoController`

**Files:**
- Modify: `app/Modulos/Manutencao/Controllers/OrdensServicoController.php`
- Modify: `index.php` (route action allow-list)

**Interfaces:**
- Consumes: `OrdensServicoModel::criar/atualizar/addItem/addPendencia/resolverPendencia/responsavelValido` from Task 2; `AnexosModel::salvar('os', $osId, $_FILES['anexos'], $userId)` (already exists, extended for PDF in Task 4).
- Produces: updated `store()` (new fields + pendencias + serviços + anexos), updated `changeStatus()` (new `$allowedStatus`), new `addPendencia()`, `resolvePendencia()`, `changeStatusDrag()` actions (JSON, same shape as `assignExecutor()`).

- [ ] **Step 1: Update `store()` with new fields, pendencias, serviços, anexos**

Replace `store()` (lines 89-139) with:

```php
    public function store(): void
    {
        if (!has_permission('os.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=os');
            return;
        }
        $veiculoId = (int)($_POST['veiculo_id'] ?? 0);
        $status = $_POST['status'] ?? 'solicitacao';
        $allowedStatus = ['solicitacao', 'aguardando_agendamento', 'em_execucao', 'analise_aprovacao'];
        $odometro = $_POST['odometro_abertura'] ?? null;
        $prioridade = $_POST['prioridade'] ?? 'media';
        $allowedPrioridade = ['baixa', 'media', 'alta', 'urgente'];
        $tipoFornecedor = $_POST['tipo_fornecedor'] ?? 'externo';
        $fornecedor = trim($_POST['fornecedor'] ?? '');
        $motivoAbertura = trim($_POST['motivo_abertura'] ?? '');
        $responsavelId = (int)($_POST['responsavel_id'] ?? 0);
        $medicaoTipo = ($_POST['medicao_tipo'] ?? 'odometro') === 'horimetro' ? 'horimetro' : 'odometro';
        $disponibilidade = in_array($_POST['disponibilidade'] ?? '', ['disponivel', 'indisponivel'], true) ? $_POST['disponibilidade'] : null;

        if (!$veiculoId || !$this->osModel->veiculoValido($veiculoId)) {
            flash('error', 'Veiculo invalido.');
            header('Location: index.php?page=os&action=create');
            return;
        }
        if (!in_array($status, $allowedStatus, true)) {
            flash('error', 'Status invalido.');
            header('Location: index.php?page=os&action=create');
            return;
        }
        if ($odometro !== null && $odometro !== '' && !is_numeric($odometro)) {
            flash('error', 'Odometro invalido.');
            header('Location: index.php?page=os&action=create');
            return;
        }
        if (!in_array($prioridade, $allowedPrioridade, true)) {
            flash('error', 'Prioridade invalida.');
            header('Location: index.php?page=os&action=create');
            return;
        }
        if ($motivoAbertura === '') {
            flash('error', 'Informe o motivo da abertura.');
            header('Location: index.php?page=os&action=create');
            return;
        }
        if (!in_array($tipoFornecedor, ['interno', 'externo'], true)) {
            flash('error', 'Tipo de fornecedor invalido.');
            header('Location: index.php?page=os&action=create');
            return;
        }
        if ($tipoFornecedor === 'externo' && $fornecedor === '') {
            flash('error', 'Informe o fornecedor externo.');
            header('Location: index.php?page=os&action=create');
            return;
        }
        if ($responsavelId && !$this->osModel->responsavelValido($responsavelId)) {
            flash('error', 'Responsavel invalido.');
            header('Location: index.php?page=os&action=create');
            return;
        }

        $userId = current_user()['id'] ?? null;
        $payload = [
            'veiculo_id' => $veiculoId,
            'status' => $status,
            'odometro_abertura' => $odometro !== '' ? $odometro : null,
            'observacoes' => $_POST['observacoes'] ?? null,
            'aberta_por' => $userId,
            'aberta_em' => $this->osModel->normalizarProgramadaPara($_POST['aberta_em'] ?? '') ?? date('Y-m-d H:i:s'),
            'medicao_tipo' => $medicaoTipo,
            'disponibilidade' => $disponibilidade,
            'prioridade' => $prioridade,
            'motivo_abertura' => $motivoAbertura,
            'tipo_fornecedor' => $tipoFornecedor,
            'fornecedor' => $tipoFornecedor === 'externo' ? $fornecedor : null,
            'responsavel_id' => $responsavelId ?: null,
        ];
        if (trim($_POST['codigo'] ?? '') !== '') {
            $payload['codigo'] = trim($_POST['codigo']);
        }
        $osId = $this->osModel->criar($payload);

        $ssIds = $_POST['ss_ids'] ?? [];
        if (!is_array($ssIds)) {
            $ssIds = [];
        }
        $this->osModel->vincularServiceRequests($osId, $ssIds);
        foreach ($ssIds as $sid) {
            $this->ssModel->mudarStatus((int)$sid, 'convertida', null);
            $this->auditoria->registrar('ss', (int)$sid, 'linked_os', null, ['os_id' => $osId], $userId);
        }

        $servicoTitulos = $_POST['servico_titulo'] ?? [];
        $servicoValores = $_POST['servico_valor'] ?? [];
        foreach ((array)$servicoTitulos as $i => $titulo) {
            $titulo = trim((string)$titulo);
            if ($titulo === '') {
                continue;
            }
            $this->osModel->addItem($osId, [
                'titulo' => $titulo,
                'valor' => $servicoValores[$i] ?? 0,
            ]);
        }

        $pendenciaTitulos = $_POST['pendencia_titulo'] ?? [];
        foreach ((array)$pendenciaTitulos as $titulo) {
            $titulo = trim((string)$titulo);
            if ($titulo === '') {
                continue;
            }
            $this->osModel->addPendencia($osId, $titulo);
        }

        if (!empty($_FILES['anexos']['name'][0] ?? '')) {
            $this->anexosModel->salvar('os', $osId, $_FILES['anexos'], (int)($userId ?? 0));
        }

        $this->auditoria->registrar('os', $osId, 'create', null, $payload, $userId);
        flash('success', 'OS criada.');
        header('Location: index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=' . $osId);
    }
```

- [ ] **Step 2: Update `changeStatus()` allowed list**

In `changeStatus()`, replace the `$allowedStatus` line (line 248):

```php
        $allowedStatus = ['solicitacao', 'aguardando_agendamento', 'em_execucao', 'analise_aprovacao', 'encerrada', 'cancelada'];
```

- [ ] **Step 3: Add `changeStatusDrag()` AJAX action (Kanban drag-and-drop)**

Insert after `assignExecutor()` (after line 236):

```php
    public function changeStatusDrag(): void
    {
        if (!has_permission('os.manage')) {
            $this->json(['ok' => false, 'message' => 'Sem permissao.'], 403);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['ok' => false, 'message' => 'Metodo invalido.'], 405);
        }

        $osId = (int)($_POST['os_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $draggableStatus = ['solicitacao', 'aguardando_agendamento', 'em_execucao', 'analise_aprovacao'];

        if ($osId <= 0 || !$this->osModel->obter($osId)) {
            $this->json(['ok' => false, 'message' => 'OS invalida.'], 422);
        }
        if (!in_array($status, $draggableStatus, true)) {
            $this->json(['ok' => false, 'message' => 'Status invalido para o quadro.'], 422);
        }

        $this->osModel->mudarStatus($osId, $status);
        $this->auditoria->registrar('os', $osId, 'status_drag', null, ['status' => $status], current_user()['id'] ?? null);
        $this->json(['ok' => true, 'status' => $status]);
    }
```

- [ ] **Step 4: Add `addPendencia()` and `resolvePendencia()` actions**

Insert after `addItem()` (after line 294):

```php
    public function addPendencia(): void
    {
        if (!has_permission('os.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=os');
            return;
        }
        $osId = (int)($_POST['os_id'] ?? 0);
        $titulo = trim($_POST['titulo'] ?? '');
        if ($osId && $titulo !== '' && $this->osModel->obter($osId)) {
            $this->osModel->addPendencia($osId, $titulo);
            $this->auditoria->registrar('os', $osId, 'add_pendencia', null, ['titulo' => $titulo], current_user()['id'] ?? null);
            flash('success', 'Pendencia adicionada.');
        } else {
            flash('error', 'Informe um titulo para a pendencia.');
        }
        header('Location: index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=' . $osId);
    }

    public function resolvePendencia(): void
    {
        if (!has_permission('os.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=os');
            return;
        }
        $osId = (int)($_POST['os_id'] ?? 0);
        $pendenciaId = (int)($_POST['pendencia_id'] ?? 0);
        $resolvida = !empty($_POST['resolvida']);
        if ($osId && $pendenciaId && $this->osModel->resolverPendencia($pendenciaId, $osId, $resolvida)) {
            $this->auditoria->registrar('os', $osId, 'pendencia_status', null, ['pendencia_id' => $pendenciaId, 'resolvida' => $resolvida], current_user()['id'] ?? null);
            flash('success', 'Pendencia atualizada.');
        } else {
            flash('error', 'Pendencia nao encontrada.');
        }
        header('Location: index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=' . $osId);
    }
```

- [ ] **Step 5: Add anexo upload handling to `show()`**

In `show()`, right after the permission check and before `$id = ...` (i.e. at the top of the method body, after line 147), add:

```php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_permission('os.manage') && !empty($_FILES['anexos']['name'][0] ?? '')) {
            $osIdUpload = (int)($_POST['os_id'] ?? $_GET['id'] ?? 0);
            $userId = current_user()['id'] ?? null;
            $this->anexosModel->salvar('os', $osIdUpload, $_FILES['anexos'], (int)($userId ?? 0));
            $this->auditoria->registrar('os', $osIdUpload, 'upload', null, ['files' => count($_FILES['anexos']['name'] ?? [])], $userId);
            flash('success', 'Anexos enviados.');
            header('Location: index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=' . $osIdUpload);
            return;
        }
```

- [ ] **Step 6: Register new actions in the router**

Read `index.php` lines around the `os` route entry (found earlier at index.php ~line with `'os' => [...]`) and add `'addPendencia', 'resolvePendencia', 'changeStatusDrag'` to its `actions` array, next to the existing `'updateItemStatus', 'linkServiceRequests'`.

- [ ] **Step 7: Syntax check both files**

Run:
```bash
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Manutencao\Controllers\OrdensServicoController.php"
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\index.php"
```
Expected: `No syntax errors detected` for both.

- [ ] **Step 8: Commit**

```bash
cd "C:/xampp/htdocs/frotaandrade"
git add app/Modulos/Manutencao/Controllers/OrdensServicoController.php index.php
git commit -m "Add OS controller support for new fields, pendencias, drag status change, and anexo upload"
```

---

## Task 4: `AnexosModel` — allow PDF

**Files:**
- Modify: `app/Modulos/Manutencao/Models/AnexosModel.php`

**Interfaces:**
- Produces: `salvar()` now accepts `application/pdf` in addition to the existing image mimes.

- [ ] **Step 1: Widen the allowed mime list**

In `salvar()` (lines 55-57), replace:

```php
        $permitidos = ['image/jpeg','image/png','image/webp','image/gif'];
        $extPorMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $maxBytes = 5 * 1024 * 1024;
```

with:

```php
        $permitidos = ['image/jpeg','image/png','image/webp','image/gif','application/pdf'];
        $extPorMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'application/pdf' => 'pdf'];
        $maxBytes = 20 * 1024 * 1024;
```

(20MB matches the "Tamanho máximo: 20MB" hint from the reference form.)

- [ ] **Step 2: Syntax check**

Run: `& "C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Manutencao\Models\AnexosModel.php"`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
cd "C:/xampp/htdocs/frotaandrade"
git add app/Modulos/Manutencao/Models/AnexosModel.php
git commit -m "Allow PDF attachments in AnexosModel"
```

---

## Task 5: Rewrite `os/create.php`

**Files:**
- Modify (full rewrite): `app/Modulos/Manutencao/Views/os/create.php`

**Interfaces:**
- Consumes: `$veiculos` (id, plate, model), `$ssList` (id, titulo), plus two new view vars the controller's `create()` action must supply: `$executores` (id, name — reuse `OrdensServicoModel::listarExecutantes()`, which already returns `role in ('executante','lider')`; since `seg_usuarios.role` values in this DB are actually `admin,gestor,executante`, this list will include `executante` only — acceptable for "Responsável", matches existing usage elsewhere) — actually for "Responsável" any user should be selectable, so add and use a new `$responsaveis` var from a new lightweight query. See Step 0.
- POST field names consumed by `OrdensServicoController::store()` (Task 3): `veiculo_id, status, medicao_tipo, odometro_abertura, disponibilidade, prioridade, motivo_abertura, codigo, aberta_em, tipo_fornecedor, fornecedor, responsavel_id, observacoes, ss_ids[], pendencia_titulo[], servico_titulo[], servico_valor[], anexos[]`.

- [ ] **Step 0: Add a `$responsaveis` list to the `create()` controller action**

In `OrdensServicoController::create()` (lines 73-87), add a query for all users in the company (any role) right after `$veiculos = ...`:

```php
        $responsaveis = $this->osModel->listarExecutantes();
```

(Reuses the existing method — it already falls back to "all users in the company" when the `executante`/`lider` role filter returns nothing, per its own fallback logic at lines 277-285 of the model. Good enough: this form's "Responsável" is meant to be whoever manages the OS, and the existing fallback already returns every user when no executante/lider exists.)
Pass it to the view: add `'responsaveis' => $responsaveis,` to the `View::render()` call's data array.

- [ ] **Step 1: Write the new view**

```php
<?php
$veiculos = $veiculos ?? [];
$ssList = $ssList ?? [];
$responsaveis = $responsaveis ?? [];
$statusOptions = [
    'solicitacao' => 'Solicitação',
    'aguardando_agendamento' => 'Aguardando/Agendado',
    'em_execucao' => 'Em execução',
    'analise_aprovacao' => 'Análise e Aprovação',
];
?>
<div class="max-w-5xl mx-auto px-4 py-6 space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-semibold text-slate-900">Nova OS</h2>
        <a class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" href="index.php?page=os">Voltar</a>
    </div>

    <form method="post" action="index.php?mod=manutencao&ctrl=OrdensServico&action=store" enctype="multipart/form-data" class="space-y-6" id="os-create-form">
<?= csrf_field() ?>

        <section class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-4">
            <h3 class="text-base font-semibold text-slate-900">Detalhes</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Status *</label>
                    <select class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="status" required>
                        <?php foreach ($statusOptions as $key => $label): ?>
                            <option value="<?= sanitize($key) ?>"><?= sanitize($label) ?></option>
                        <?php endforeach; ?>
                    </select>
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

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Medição</label>
                    <div class="inline-flex rounded-lg border border-slate-200 overflow-hidden text-sm" id="medicao-toggle">
                        <button type="button" data-medicao="odometro" class="px-4 py-2 font-semibold bg-slate-900 text-white">Odômetro</button>
                        <button type="button" data-medicao="horimetro" class="px-4 py-2 font-semibold bg-white text-slate-700">Horímetro</button>
                    </div>
                    <input type="hidden" name="medicao_tipo" id="medicao_tipo" value="odometro">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1" id="odometro-label">Odômetro *</label>
                    <input class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="odometro_abertura" type="number" step="0.01" placeholder="0,00">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Disponibilidade</label>
                    <select class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="disponibilidade">
                        <option value="">Selecione a disponibilidade</option>
                        <option value="disponivel">Disponível</option>
                        <option value="indisponivel">Indisponível</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Prioridade *</label>
                    <select class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="prioridade" required>
                        <option value="baixa">Baixa</option>
                        <option value="media" selected>Média</option>
                        <option value="alta">Alta</option>
                        <option value="urgente">Urgente</option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Motivo da abertura da O.S *</label>
                    <input class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="motivo_abertura" placeholder="Ex: Troca de óleo, barulho no freio, revisão de 10.000km" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nº Ordem de Serviço</label>
                    <input class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="codigo" placeholder="Ex: OS-001 (deixe em branco para gerar automaticamente)">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Data de abertura *</label>
                    <input class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="aberta_em" type="datetime-local" value="<?= date('Y-m-d\TH:i') ?>" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tipo de fornecedor *</label>
                    <div class="inline-flex rounded-lg border border-slate-200 overflow-hidden text-sm" id="fornecedor-toggle">
                        <button type="button" data-tipo-fornecedor="externo" class="px-4 py-2 font-semibold bg-slate-900 text-white">Externo</button>
                        <button type="button" data-tipo-fornecedor="interno" class="px-4 py-2 font-semibold bg-white text-slate-700">Interno</button>
                    </div>
                    <input type="hidden" name="tipo_fornecedor" id="tipo_fornecedor" value="externo">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1" id="fornecedor-label">Fornecedor *</label>
                    <input class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="fornecedor" placeholder="Selecione um fornecedor">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Responsável *</label>
                    <select class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="responsavel_id" required>
                        <option value="">Selecione o responsável pela O.S.</option>
                        <?php foreach ($responsaveis as $r): ?>
                            <option value="<?= sanitize($r['id']) ?>"><?= sanitize($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Observações</label>
                    <textarea class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="observacoes" rows="3"></textarea>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Vincular SS (opcional)</label>
                    <select class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" multiple name="ss_ids[]">
                        <?php foreach ($ssList as $ss): ?>
                            <option value="<?= sanitize($ss['id']) ?>">#<?= sanitize($ss['id']) ?> - <?= sanitize($ss['titulo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Segure CTRL para selecionar múltiplas.</p>
                </div>
            </div>
        </section>

        <section class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900 mb-3">Pendências em aberto</h3>
            <div id="pendencias-list" class="space-y-2"></div>
            <button type="button" id="add-pendencia" class="mt-2 inline-flex items-center rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">+ Adicionar pendência</button>
        </section>

        <section class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-base font-semibold text-slate-900">Serviços</h3>
                <a class="text-sm text-blue-600 hover:underline" href="index.php?page=os">Ver lembretes</a>
            </div>
            <div id="servicos-list" class="space-y-2"></div>
            <button type="button" id="add-servico" class="mt-2 inline-flex items-center rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">+ Adicionar serviço</button>
            <div class="mt-4 flex justify-end items-center gap-3 border-t border-slate-100 pt-3">
                <span class="text-sm text-slate-500">Total</span>
                <span class="text-lg font-semibold text-slate-900" id="servicos-total">R$ 0,00</span>
            </div>
        </section>

        <section class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900 mb-1">Anexos</h3>
            <p class="text-sm text-slate-500 mb-3">Adicione uma ou mais arquivos relacionados à manutenção.</p>
            <label class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 py-8 cursor-pointer hover:bg-slate-50">
                <span class="text-sm text-slate-600">Arraste e solte ou <span class="text-blue-600 underline">clique para selecionar</span></span>
                <span class="text-xs text-slate-400">Limite: 10 arquivos • Formatos: PDF, PNG, JPEG • Máximo: 20MB</span>
                <input type="file" name="anexos[]" multiple accept=".pdf,.png,.jpg,.jpeg" class="hidden">
            </label>
        </section>

        <div class="flex justify-end gap-2">
            <a class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" href="index.php?page=os">Cancelar</a>
            <button class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Criar Ordem de Serviço</button>
        </div>
    </form>
</div>

<template id="pendencia-row-template">
    <div class="flex items-center gap-2 pendencia-row">
        <input class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm" name="pendencia_titulo[]" placeholder="Descreva a pendência">
        <button type="button" class="remove-row text-slate-400 hover:text-red-600 text-sm">Remover</button>
    </div>
</template>

<template id="servico-row-template">
    <div class="flex items-center gap-2 servico-row">
        <input class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm" name="servico_titulo[]" placeholder="Descrição do serviço">
        <input class="w-32 rounded-lg border border-slate-200 px-3 py-2 text-sm servico-valor" name="servico_valor[]" type="number" step="0.01" min="0" placeholder="0,00">
        <button type="button" class="remove-row text-slate-400 hover:text-red-600 text-sm">Remover</button>
    </div>
</template>

<script>
(function() {
    function addRow(listId, templateId) {
        const list = document.getElementById(listId);
        const tpl = document.getElementById(templateId);
        const node = tpl.content.cloneNode(true);
        node.querySelector('.remove-row').addEventListener('click', function(e) {
            e.target.closest('div').remove();
            recalcTotal();
        });
        list.appendChild(node);
    }

    document.getElementById('add-pendencia').addEventListener('click', () => addRow('pendencias-list', 'pendencia-row-template'));
    document.getElementById('add-servico').addEventListener('click', () => addRow('servicos-list', 'servico-row-template'));

    function recalcTotal() {
        let total = 0;
        document.querySelectorAll('.servico-valor').forEach((input) => {
            total += parseFloat(input.value || '0') || 0;
        });
        document.getElementById('servicos-total').textContent =
            'R$ ' + total.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    document.getElementById('servicos-list').addEventListener('input', function(e) {
        if (e.target.classList.contains('servico-valor')) {
            recalcTotal();
        }
    });

    function wireToggle(containerId, hiddenId, labelId, labels) {
        const container = document.getElementById(containerId);
        const hidden = document.getElementById(hiddenId);
        container.querySelectorAll('button').forEach((btn) => {
            btn.addEventListener('click', function() {
                const value = this.dataset.medicao || this.dataset.tipoFornecedor;
                hidden.value = value;
                container.querySelectorAll('button').forEach((b) => {
                    b.classList.toggle('bg-slate-900', b === this);
                    b.classList.toggle('text-white', b === this);
                    b.classList.toggle('bg-white', b !== this);
                    b.classList.toggle('text-slate-700', b !== this);
                });
                if (labelId && labels) {
                    document.getElementById(labelId).textContent = labels[value];
                }
            });
        });
    }
    wireToggle('medicao-toggle', 'medicao_tipo', 'odometro-label', { odometro: 'Odômetro *', horimetro: 'Horímetro *' });
    wireToggle('fornecedor-toggle', 'tipo_fornecedor', 'fornecedor-label', { externo: 'Fornecedor *', interno: 'Fornecedor (interno)' });
})();
</script>
```

- [ ] **Step 2: Syntax check**

Run: `& "C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Manutencao\Views\os\create.php"`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
cd "C:/xampp/htdocs/frotaandrade"
git add app/Modulos/Manutencao/Controllers/OrdensServicoController.php app/Modulos/Manutencao/Views/os/create.php
git commit -m "Rewrite OS creation form with new fields, pendencias, servicos, and anexos (Tailwind)"
```

---

## Task 6: Update `os/show.php`

**Files:**
- Modify: `app/Modulos/Manutencao/Views/os/show.php`

**Interfaces:**
- Consumes: `$os['pendencias']` (from Task 2's `obter()`), `$os['items']` now includes `valor`.

- [ ] **Step 1: Replace the status maps (lines 7-29)**

```php
$statusLabels = [
    'solicitacao' => 'Solicitação',
    'aguardando_agendamento' => 'Aguardando/Agendado',
    'em_execucao' => 'Em execução',
    'analise_aprovacao' => 'Análise e Aprovação',
    'encerrada' => 'Encerrada',
    'cancelada' => 'Cancelada',
];
$statusColors = [
    'solicitacao' => 'bg-slate-100 text-slate-700',
    'aguardando_agendamento' => 'bg-indigo-100 text-indigo-800',
    'em_execucao' => 'bg-amber-100 text-amber-800',
    'analise_aprovacao' => 'bg-orange-100 text-orange-800',
    'encerrada' => 'bg-emerald-100 text-emerald-800',
    'cancelada' => 'bg-gray-200 text-gray-700',
];
```

- [ ] **Step 2: Update the status select options (line 68)**

```php
                            <?php foreach (['solicitacao','aguardando_agendamento','em_execucao','analise_aprovacao','encerrada','cancelada'] as $s): ?>
```

- [ ] **Step 3: Rename "Itens da OS" section to "Serviços" and show valor + total**

Replace the "Itens da OS" `<h3>` and its list block (lines 106-137) with:

```php
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-slate-900">Serviços</h3>
                <span class="text-xs text-slate-500"><?= sanitize($os['items'] ? count($os['items']) : 0) ?> serviços</span>
            </div>
            <div class="mt-4 space-y-3">
                <?php foreach ($os['items'] ?? [] as $it): ?>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                        <div class="flex items-center justify-between">
                            <div class="font-semibold text-slate-800"><?= sanitize($it['titulo']) ?></div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-slate-700">R$ <?= number_format((float)($it['valor'] ?? 0), 2, ',', '.') ?></span>
                                <span class="text-xs font-medium text-slate-500"><?= ucfirst(sanitize($it['status'])) ?></span>
                            </div>
                        </div>
                        <?php if (!empty($it['descricao'])): ?>
                            <div class="mt-2 text-sm text-slate-600"><?= nl2br(sanitize($it['descricao'])) ?></div>
                        <?php endif; ?>
                        <?php if (has_permission('os.manage')): ?>
                            <form class="mt-2 flex items-center gap-2" method="post" action="index.php?mod=manutencao&ctrl=OrdensServico&action=updateItemStatus">
<?= csrf_field() ?>
                                <input type="hidden" name="os_id" value="<?= sanitize($os['id'] ?? '') ?>">
                                <input type="hidden" name="item_id" value="<?= sanitize($it['id']) ?>">
                                <select class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs" name="status">
                                    <?php foreach (['pendente','em_andamento','concluido','bloqueado','cancelado'] as $is): ?>
                                        <option value="<?= $is ?>" <?= ($it['status'] ?? '') === $is ? 'selected' : '' ?>><?= ucfirst($is) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="text-xs font-semibold text-slate-700 hover:underline">Salvar</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($os['items'])): ?>
                    <div class="text-sm text-slate-500">Sem serviços cadastrados.</div>
                <?php endif; ?>
                <?php
                    $totalGeral = array_sum(array_column($os['items'] ?? [], 'valor'))
                        + array_sum(array_column($os['labor'] ?? [], 'total'))
                        + array_sum(array_column($os['parts'] ?? [], 'total'));
                ?>
                <div class="flex justify-end items-center gap-2 border-t border-slate-100 pt-3">
                    <span class="text-sm text-slate-500">Total geral</span>
                    <span class="text-base font-semibold text-slate-900">R$ <?= number_format((float)$totalGeral, 2, ',', '.') ?></span>
                </div>
            </div>

            <?php if (has_permission('os.manage')): ?>
                <form class="mt-5 space-y-3" method="post" action="index.php?mod=manutencao&ctrl=OrdensServico&action=addItem">
<?= csrf_field() ?>
                    <input type="hidden" name="os_id" value="<?= sanitize($os['id'] ?? '') ?>">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Novo serviço</label>
                        <input class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="titulo" placeholder="Descrição do serviço" required>
                    </div>
                    <div>
                        <textarea class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="descricao" rows="2" placeholder="Detalhes"></textarea>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <select class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" name="prioridade">
                            <?php foreach (['baixa','media','alta','critica'] as $p): ?>
                                <option value="<?= $p ?>"><?= ucfirst($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm w-32" name="valor" type="number" step="0.01" placeholder="Valor">
                        <button class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Adicionar serviço</button>
                    </div>
                </form>
            <?php endif; ?>
```

- [ ] **Step 4: Pass `valor` through in `addItem` controller call**

In `OrdensServicoController::addItem()`, add `'valor' => $_POST['valor'] ?? 0,` to the array passed to `$this->osModel->addItem()`.

- [ ] **Step 5: Add a Pendências section**

Insert a new `<section>` right after the "SS vinculadas"/"Itens" `<section>` block (after line 194, before the "Mão de obra"/"Peças" section):

```php
    <section class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-slate-900">Pendências em aberto</h3>
            <span class="text-xs text-slate-500"><?= sanitize($os['pendencias'] ? count($os['pendencias']) : 0) ?> pendências</span>
        </div>
        <div class="mt-4 space-y-2 text-sm">
            <?php foreach ($os['pendencias'] ?? [] as $p): ?>
                <div class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2">
                    <span class="<?= $p['resolvida'] ? 'line-through text-slate-400' : 'font-medium text-slate-800' ?>"><?= sanitize($p['titulo']) ?></span>
                    <?php if (has_permission('os.manage')): ?>
                        <form method="post" action="index.php?mod=manutencao&ctrl=OrdensServico&action=resolvePendencia">
<?= csrf_field() ?>
                            <input type="hidden" name="os_id" value="<?= sanitize($os['id'] ?? '') ?>">
                            <input type="hidden" name="pendencia_id" value="<?= sanitize($p['id']) ?>">
                            <input type="hidden" name="resolvida" value="<?= $p['resolvida'] ? '0' : '1' ?>">
                            <button class="text-xs font-semibold text-blue-600 hover:underline"><?= $p['resolvida'] ? 'Reabrir' : 'Marcar resolvida' ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (empty($os['pendencias'])): ?>
                <div class="text-sm text-slate-500">Nenhuma pendência adicionada.</div>
            <?php endif; ?>
        </div>
        <?php if (has_permission('os.manage')): ?>
            <form class="mt-5 flex items-center gap-2" method="post" action="index.php?mod=manutencao&ctrl=OrdensServico&action=addPendencia">
<?= csrf_field() ?>
                <input type="hidden" name="os_id" value="<?= sanitize($os['id'] ?? '') ?>">
                <input class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm" name="titulo" placeholder="Descreva a pendência" required>
                <button class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Adicionar</button>
            </form>
        <?php endif; ?>
    </section>
```

- [ ] **Step 6: Add anexo upload form to the "Anexos" section**

In the "Anexos" `<section>` (around line 256-268), add an upload form before the grid of existing attachments:

```php
        <?php if (has_permission('os.manage')): ?>
            <form class="mt-4 mb-4" method="post" action="index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=<?= sanitize($os['id'] ?? '') ?>" enctype="multipart/form-data">
<?= csrf_field() ?>
                <input type="hidden" name="os_id" value="<?= sanitize($os['id'] ?? '') ?>">
                <label class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 py-6 cursor-pointer hover:bg-slate-50">
                    <span class="text-sm text-slate-600">Arraste e solte ou <span class="text-blue-600 underline">clique para selecionar</span></span>
                    <span class="text-xs text-slate-400">PDF, PNG, JPEG • Máximo 20MB</span>
                    <input type="file" name="anexos[]" multiple accept=".pdf,.png,.jpg,.jpeg" class="hidden" onchange="this.form.submit()">
                </label>
            </form>
        <?php endif; ?>
```

- [ ] **Step 7: Syntax check**

Run: `& "C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Manutencao\Views\os\show.php"`
Expected: `No syntax errors detected`
Also re-run: `& "C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Manutencao\Controllers\OrdensServicoController.php"`

- [ ] **Step 8: Commit**

```bash
cd "C:/xampp/htdocs/frotaandrade"
git add app/Modulos/Manutencao/Views/os/show.php app/Modulos/Manutencao/Controllers/OrdensServicoController.php
git commit -m "Update OS detail view: new status labels, servicos valor/total, pendencias section, anexo upload"
```

---

## Task 7: Kanban view in `os/index.php`

**Files:**
- Modify: `app/Modulos/Manutencao/Views/os/index.php`

**Interfaces:**
- Consumes: `$orders` (each row now has `status` from the new pipeline).
- Produces: a `#os-kanban-view` block, a 3-way toggle (`Kanban`/`Agenda`/`Lista`), JS posting to `index.php?mod=manutencao&ctrl=OrdensServico&action=changeStatusDrag`.

- [ ] **Step 1: Replace the status maps (lines 8-27)**

```php
$statusLabels = [
    'solicitacao' => 'Solicitação',
    'aguardando_agendamento' => 'Aguardando/Agendado',
    'em_execucao' => 'Em execução',
    'analise_aprovacao' => 'Análise e Aprovação',
    'encerrada' => 'Encerrada',
    'cancelada' => 'Cancelada',
];
$statusColors = [
    'solicitacao' => 'bg-slate-100 text-slate-700',
    'aguardando_agendamento' => 'bg-indigo-100 text-indigo-800',
    'em_execucao' => 'bg-amber-100 text-amber-800',
    'analise_aprovacao' => 'bg-orange-100 text-orange-800',
    'encerrada' => 'bg-emerald-100 text-emerald-800',
    'cancelada' => 'bg-gray-200 text-gray-700',
];
$kanbanColumns = [
    'solicitacao' => 'Solicitação',
    'aguardando_agendamento' => 'Aguardando/Agendado',
    'em_execucao' => 'Em Execução',
    'analise_aprovacao' => 'Análise e Aprovação',
];
$kanbanCardsByStatus = ['solicitacao' => [], 'aguardando_agendamento' => [], 'em_execucao' => [], 'analise_aprovacao' => []];
$kanbanConcluidas = [];
foreach ($orders as $os) {
    $st = $os['status'] ?? 'solicitacao';
    if (isset($kanbanCardsByStatus[$st])) {
        $kanbanCardsByStatus[$st][] = $os;
    } elseif (in_array($st, ['encerrada', 'cancelada'], true)) {
        $kanbanConcluidas[] = $os;
    }
}
```

- [ ] **Step 2: Add the third view-toggle button**

In the `.os-view-switch` block (around line 648-651), add a Kanban button before Agenda:

```php
                        <div class="os-view-switch">
                            <button type="button" class="btn btn-sm btn-primary" data-view="kanban">Kanban</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-view="agenda">Agenda</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-view="lista">Lista</button>
                        </div>
```

- [ ] **Step 3: Add the Kanban markup**

Insert a new block right before `<div id="os-agenda-view" ...>` (before line 680):

```php
    <div id="os-kanban-view" class="space-y-3">
        <div class="flex items-center gap-2">
            <button type="button" class="os-pill active" data-kanban-tab="ativas">Em andamento</button>
            <button type="button" class="os-pill" data-kanban-tab="concluidas">Concluídas</button>
        </div>

        <div data-kanban-panel="ativas" class="grid gap-3" style="grid-template-columns: repeat(4, minmax(260px, 1fr));">
            <?php foreach ($kanbanColumns as $statusKey => $label): ?>
                <?php $cards = $kanbanCardsByStatus[$statusKey] ?? []; ?>
                <section class="os-column" style="min-height:200px;">
                    <div class="os-column-header">
                        <div class="os-column-title"><?= sanitize($label) ?></div>
                        <div class="os-column-count"><?= count($cards) ?></div>
                    </div>
                    <div class="os-dropzone" data-kanban-dropzone="<?= sanitize($statusKey) ?>" style="min-height:150px;">
                        <?php if (!$cards): ?>
                            <div class="os-empty">Sem OS nesta coluna.</div>
                        <?php endif; ?>
                        <?php foreach ($cards as $os): ?>
                            <?php
                                $osTotal = 0.0;
                                $diasAberta = '';
                                if (!empty($os['aberta_em'])) {
                                    $diff = (new DateTime())->diff(new DateTime($os['aberta_em']));
                                    $diasAberta = 'Há ' . $diff->days . ' dias';
                                }
                            ?>
                            <article class="os-card" draggable="true" data-os-id="<?= sanitize($os['id']) ?>" data-status="<?= sanitize($statusKey) ?>">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                    <div class="os-card-code"><?= sanitize($os['codigo']) ?></div>
                                </div>
                                <div class="os-card-meta mb-1"><?= sanitize($diasAberta) ?></div>
                                <div class="os-card-meta mb-1"><?= sanitize($os['vehicle_plate'] ?? '-') ?></div>
                                <div class="os-card-meta mb-2"><?= sanitize($os['motivo_abertura'] ?? '') ?></div>
                                <div class="os-card-actions">
                                    <a class="btn btn-sm btn-outline-primary os-open-btn" href="index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=<?= sanitize($os['id']) ?>">Abrir OS</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>

        <div data-kanban-panel="concluidas" class="os-column" style="display:none;">
            <div class="os-column-header">
                <div class="os-column-title">Concluídas</div>
                <div class="os-column-count"><?= count($kanbanConcluidas) ?></div>
            </div>
            <div class="os-dropzone">
                <?php if (!$kanbanConcluidas): ?>
                    <div class="os-empty">Sem OS concluídas.</div>
                <?php endif; ?>
                <?php foreach ($kanbanConcluidas as $os): ?>
                    <article class="os-card" data-os-id="<?= sanitize($os['id']) ?>">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                            <div class="os-card-code"><?= sanitize($os['codigo']) ?></div>
                            <span class="os-chip <?= $statusColors[$os['status']] ?? '' ?>"><?= sanitize($statusLabels[$os['status']] ?? $os['status']) ?></span>
                        </div>
                        <div class="os-card-meta mb-1"><?= sanitize($os['vehicle_plate'] ?? '-') ?></div>
                        <div class="os-card-actions">
                            <a class="btn btn-sm btn-outline-primary os-open-btn" href="index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=<?= sanitize($os['id']) ?>">Abrir OS</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
```

- [ ] **Step 4: Wire up view switching (Kanban is now a real view, not just Agenda/Lista) and drag-and-drop**

In the `<script>` block, update `setView()` (around line 853) to handle three views, and add Kanban drag logic. Replace the `setView` function with:

```javascript
    const kanbanView = document.getElementById('os-kanban-view');
    function setView(view) {
        agendaView.style.display = view === 'agenda' ? '' : 'none';
        listaView.style.display = view === 'lista' ? '' : 'none';
        kanbanView.style.display = view === 'kanban' ? '' : 'none';
        viewButtons.forEach((btn) => {
            const active = btn.dataset.view === view;
            btn.classList.toggle('btn-primary', active);
            btn.classList.toggle('btn-outline-secondary', !active);
        });
        setPageScrollLock(view === 'agenda');
    }
```

And change the initial call from `setView('agenda');` to `setView('kanban');`.

Add, right after the existing drag-and-drop block for the executor/week board (before the closing `})();`), the Kanban tab switch and drag-and-drop wiring:

```javascript
    document.querySelectorAll('[data-kanban-tab]').forEach((btn) => {
        btn.addEventListener('click', function() {
            const tab = this.dataset.kanbanTab;
            document.querySelectorAll('[data-kanban-tab]').forEach((b) => b.classList.toggle('active', b === this));
            document.querySelector('[data-kanban-panel="ativas"]').style.display = tab === 'ativas' ? '' : 'none';
            document.querySelector('[data-kanban-panel="concluidas"]').style.display = tab === 'concluidas' ? '' : 'none';
        });
    });

    let draggedKanbanCard = null;
    document.querySelectorAll('#os-kanban-view .os-card[draggable="true"]').forEach((card) => {
        card.addEventListener('dragstart', () => { draggedKanbanCard = card; card.classList.add('dragging'); });
        card.addEventListener('dragend', () => { card.classList.remove('dragging'); draggedKanbanCard = null; });
    });

    function getChangeStatusDragUrl() {
        const url = new URL(window.location.href);
        url.search = 'mod=manutencao&ctrl=OrdensServico&action=changeStatusDrag';
        return url.toString();
    }

    document.querySelectorAll('[data-kanban-dropzone]').forEach((zone) => {
        zone.addEventListener('dragover', (e) => e.preventDefault());
        zone.addEventListener('drop', async (e) => {
            e.preventDefault();
            if (!draggedKanbanCard) return;
            const card = draggedKanbanCard;
            const oldZone = card.closest('[data-kanban-dropzone]');
            const newStatus = zone.dataset.kanbanDropzone;
            if (!oldZone || oldZone === zone) return;

            zone.appendChild(card);
            card.dataset.status = newStatus;

            const body = new URLSearchParams();
            body.append('csrf_token', CSRF_TOKEN);
            body.append('os_id', card.dataset.osId);
            body.append('status', newStatus);

            try {
                const res = await fetch(getChangeStatusDragUrl(), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString(),
                });
                const data = await res.json();
                if (!res.ok || !data.ok) {
                    throw new Error(data.message || 'Falha ao mover a OS.');
                }
            } catch (err) {
                oldZone.appendChild(card);
                window.alert(err.message || 'Nao foi possivel mover a OS.');
            }
        });
    });
```

- [ ] **Step 5: Syntax check**

Run: `& "C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Manutencao\Views\os\index.php"`
Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
cd "C:/xampp/htdocs/frotaandrade"
git add app/Modulos/Manutencao/Views/os/index.php
git commit -m "Add status-based Kanban view to OS index with drag-and-drop"
```

---

## Task 8: Rebrand "Vencimentos" as "Lembretes"

**Files:**
- Modify: `app/Modulos/Manutencao/Models/PlanosPreventivaModel.php`
- Modify: `app/Modulos/Manutencao/Controllers/PlanosPreventivaController.php`
- Modify: `app/Modulos/Manutencao/Views/preventiva/vencimentos.php`
- Modify: `layout.php`

**Interfaces:**
- Produces: `PlanosPreventivaModel::contarVencimentos(): array` returning `['todos' => int, 'due_soon' => int, 'overdue' => int, 'ok' => int]`.

- [ ] **Step 1: Add a count method to the model**

In `PlanosPreventivaModel`, add after `listarVencimentos()` (after line 222):

```php
    public function contarVencimentos(): array
    {
        $sql = 'SELECT status, COUNT(*) c FROM man_maintenance_due WHERE empresa_id = ? GROUP BY status';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->empresaId]);
        $counts = ['ok' => 0, 'due_soon' => 0, 'overdue' => 0];
        foreach ($stmt as $row) {
            $counts[$row['status']] = (int)$row['c'];
        }
        $counts['todos'] = $counts['ok'] + $counts['due_soon'] + $counts['overdue'];
        return $counts;
    }
```

- [ ] **Step 2: Pass counts from the controller**

In `PlanosPreventivaController::vencimentos()` (lines 103-117), add before `View::render`:

```php
        $counts = $this->model->contarVencimentos();
```

and add `'counts' => $counts,` to the `View::render()` data array.

- [ ] **Step 3: Rewrite the view**

```php
<?php
$vencimentos = $vencimentos ?? [];
$status = $status ?? '';
$counts = $counts ?? ['todos' => 0, 'ok' => 0, 'due_soon' => 0, 'overdue' => 0];
$statusLabels = ['ok' => 'Em dia', 'due_soon' => 'Em breve', 'overdue' => 'Atrasado'];
$statusColors = [
    'ok' => 'bg-emerald-100 text-emerald-800',
    'due_soon' => 'bg-amber-100 text-amber-800',
    'overdue' => 'bg-rose-100 text-rose-800',
];

function lembreteFormatarRestante(?string $dueDate, ?int $dueKm): string
{
    $partes = [];
    if ($dueDate) {
        $hoje = new DateTimeImmutable('today');
        $due = new DateTimeImmutable($dueDate);
        $dias = (int)$hoje->diff($due)->format('%r%a');
        $partes[] = $dias < 0 ? 'Há ' . abs($dias) . ' dias' : 'Restam ' . $dias . ' dias';
    }
    if ($dueKm !== null) {
        $partes[] = 'Restam ' . number_format((float)$dueKm, 0, ',', '.') . ' km';
    }
    return $partes ? implode(' • ', $partes) : '-';
}
?>

<style>
.venc-filterbar { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-top:8px; }
.venc-pill { padding:6px 12px; border-radius:8px; border:1px solid #e2e8f0; background:#f8fafc; color:#334155; font-size:0.92rem; text-decoration:none; }
.venc-pill.active { background:#0f172a; color:#fff; border-color:#0f172a; }
.venc-pill-group { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
.venc-chip { display:inline-flex; align-items:center; padding:0.2rem 0.65rem; border-radius:999px; font-size:0.85rem; }
.venc-table thead th { padding:12px; }
.venc-table tbody td { padding:14px 12px; vertical-align:middle; }
.venc-table tbody tr + tr { border-top:1px solid #eef2f7; }
</style>

<div class="container py-4">
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h5 class="mb-0">Lembretes</h5>
                <?php if (has_permission('preventiva.manage')): ?>
                    <a class="btn btn-primary btn-sm" href="index.php?mod=manutencao&ctrl=PlanosPreventiva&action=run">Rodar preventiva</a>
                <?php endif; ?>
            </div>
            <form class="venc-filterbar" method="get" action="index.php">
                <input type="hidden" name="page" value="vencimentos_preventiva">
                <div class="venc-pill-group">
                    <button type="submit" name="status" value="" class="venc-pill <?= $status === '' ? 'active' : '' ?>">Todos <?= (int)$counts['todos'] ?></button>
                    <button type="submit" name="status" value="due_soon" class="venc-pill <?= $status === 'due_soon' ? 'active' : '' ?>">Em breve <?= (int)$counts['due_soon'] ?></button>
                    <button type="submit" name="status" value="overdue" class="venc-pill <?= $status === 'overdue' ? 'active' : '' ?>">Atrasados <?= (int)$counts['overdue'] ?></button>
                    <button type="submit" name="status" value="ok" class="venc-pill <?= $status === 'ok' ? 'active' : '' ?>">Em dia <?= (int)$counts['ok'] ?></button>
                </div>
                <button class="btn btn-sm btn-primary" type="submit">Aplicar</button>
                <a class="btn btn-sm btn-light" href="index.php?page=vencimentos_preventiva">Limpar</a>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0 venc-table">
                <thead class="table-light">
                    <tr><th>Veículo</th><th>Tarefa</th><th>Status</th><th>Próxima manutenção</th><th>Última conclusão</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($vencimentos as $v): ?>
                        <tr>
                            <td><?= sanitize($v['vehicle_plate'] ?? '') ?></td>
                            <td class="fw-semibold"><?= sanitize($v['tarefa_nome'] ?? '') ?></td>
                            <td><span class="venc-chip <?= $statusColors[$v['status'] ?? 'ok'] ?? '' ?>"><?= sanitize($statusLabels[$v['status'] ?? ''] ?? $v['status']) ?></span></td>
                            <td><?= sanitize(lembreteFormatarRestante($v['due_date'] ?? null, isset($v['due_km']) ? (int)$v['due_km'] : null)) ?></td>
                            <td><?= sanitize($v['last_check_at'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$vencimentos): ?><tr><td colspan="5" class="text-center text-muted py-4">Nenhum lembrete.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
```

- [ ] **Step 4: Rename the menu label**

In `layout.php`, change both occurrences of `'label' => 'Vencimentos'` (line 31 and line 493) to `'label' => 'Lembretes'`. Keep `'page' => 'vencimentos_preventiva'` unchanged in both places (only the label changes — no route/link breaks).

- [ ] **Step 5: Syntax check all four files**

```bash
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Manutencao\Models\PlanosPreventivaModel.php"
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Manutencao\Controllers\PlanosPreventivaController.php"
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Manutencao\Views\preventiva\vencimentos.php"
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\layout.php"
```
Expected: `No syntax errors detected` for all four.

- [ ] **Step 6: Commit**

```bash
cd "C:/xampp/htdocs/frotaandrade"
git add app/Modulos/Manutencao/Models/PlanosPreventivaModel.php app/Modulos/Manutencao/Controllers/PlanosPreventivaController.php app/Modulos/Manutencao/Views/preventiva/vencimentos.php layout.php
git commit -m "Rebrand Vencimentos Preventiva screen as Lembretes with tab counts and better formatting"
```

---

## Task 9: Full manual verification

**Files:** none (verification only)

- [ ] **Step 1: Run `php -l` across every file touched in this plan, in one pass**

```bash
cd "C:/xampp/htdocs/frotaandrade"
for f in \
  app/Modulos/Manutencao/Models/OrdensServicoModel.php \
  app/Modulos/Manutencao/Controllers/OrdensServicoController.php \
  app/Modulos/Manutencao/Models/AnexosModel.php \
  app/Modulos/Manutencao/Views/os/create.php \
  app/Modulos/Manutencao/Views/os/show.php \
  app/Modulos/Manutencao/Views/os/index.php \
  index.php \
  app/Modulos/Manutencao/Models/PlanosPreventivaModel.php \
  app/Modulos/Manutencao/Controllers/PlanosPreventivaController.php \
  app/Modulos/Manutencao/Views/preventiva/vencimentos.php \
  layout.php; do
  "/c/xampp/php/php.exe" -l "$f" || echo "FAILED: $f"
done
```
Expected: `No syntax errors detected` for every file, no `FAILED` lines.

- [ ] **Step 2: Manual browser smoke test (requires a logged-in session — do this with the user or ask them to confirm)**

Checklist (matches the spec's test plan):
1. Open `index.php?page=os` — confirm the Kanban view loads by default with 4 columns and correct counts.
2. Click "Nova OS", fill every new field (status, veículo, medição, disponibilidade, prioridade, motivo, nº OS, data, fornecedor, responsável), add 1 pendência, add 2 serviços with values, attach a PDF and a PNG, submit.
3. On the OS detail page, confirm: status chip shows the right label, "Serviços" shows both items with their values and the running total, "Pendências em aberto" shows the pendência, "Anexos" shows both files.
4. Back on the Kanban board, drag the new OS card from "Solicitação" to "Em Execução" — confirm it persists after a page reload.
5. Try dragging a card and reloading before the drop finishes; confirm no card is duplicated or lost (network tab should show one POST to `changeStatusDrag` per successful drop).
6. From the OS detail page, change status to `analise_aprovacao`, then try to close it (`encerrada`) without an odômetro — confirm it's blocked with the existing error message.
7. Fill the odômetro and mark all serviços/items `concluido`, then close the OS — confirm it succeeds and moves to the "Concluídas" tab on the Kanban board.
8. Open `index.php?page=vencimentos_preventiva` — confirm the page title/menu now say "Lembretes", tab counts match, and the "Próxima manutenção" column shows "Restam X dias"/"Restam Y km" phrasing.
9. Switch to "Agenda" and "Lista" views on the OS board — confirm both still work exactly as before (regression check).

- [ ] **Step 3: Report results to the user**

Summarize pass/fail for each of the 9 checks above before considering the feature done. Do not mark the feature complete if any check fails — fix and re-verify instead.
