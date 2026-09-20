# Plano de Manutenção Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the single-vehicle "Planos de Preventiva" module with a multi-vehicle/unit plan model backed by a reusable services catalog, matching the reference screens (list, side-panel create, plan detail with Serviços/Veículos tabs, add-service form), and fix the dead odometer-detection bug in the due-tracking job.

**Architecture:** Same `app/Modulos/Manutencao` MVC layout. `man_maintenance_plans`/`man_maintenance_tasks`/`man_maintenance_due` are dropped and recreated (0 rows exist, confirmed) with a new shape; one new catalog table (`man_maintenance_services`) and one new junction table (`man_maintenance_plan_veiculos`). All views in Tailwind, matching `os/show.php`'s style.

**Tech Stack:** PHP 8, PDO/MySQL, vanilla JS, Tailwind CDN. No unit test framework — verification is `php -l` plus live DB/HTTP checks against the running dev instance (`http://localhost/frotaandrade`, XAMPP, credentials available this session).

**Spec:** `docs/superpowers/specs/2026-09-19-plano-manutencao-redesign-design.md`

## Global Constraints

- DB is not production; the 3 existing tables have 0 rows — safe to `DROP`/recreate directly.
- Only Tailwind is loaded (`layout.php:98`) — no Bootstrap classes in new/rewritten views.
- `sanitize()` wraps all dynamic view output.
- Every new controller action enforces `has_permission('preventiva.view')` (read) or `has_permission('preventiva.manage')` (write), matching the existing pattern in `PlanosPreventivaController`.
- Every query filters by `empresa_id` (and `filial_id` via the same `filialWhere()`/`detectarFilialColuna()` pattern already in `PlanosPreventivaModel`).
- `cad_veiculos.nin_km_preventiva` is the real "current odometer" column (not `odometro_atual`/`odometro`/`km_atual`, which don't exist) — use it directly, no more column-detection guessing.
- One-off verification scripts use the `__` filename prefix and are deleted before each task's commit.

---

## File Structure

| File | Responsibility |
|---|---|
| `db/migrations/2026-09-19-plano-manutencao-redesign.sql` (new) | DDL: drop+recreate 3 tables, create 2 new tables. |
| `app/Modulos/Manutencao/Models/ManutencaoServicosModel.php` (new) | Catalog CRUD: `listar()`, `criar()`, `toggleAtivo()`. |
| `app/Modulos/Manutencao/Controllers/ManutencaoServicosController.php` (new) | `index()` (list+inline create form), `store()`, `toggle()`. |
| `app/Modulos/Manutencao/Views/preventiva/servicos_index.php` (new) | Catalog list + inline create form. |
| `app/Modulos/Manutencao/Models/PlanosPreventivaModel.php` (rewrite) | Plan CRUD, plan-service CRUD, plan-vehicle association CRUD, rewritten `processarPreventiva()`/`listarVencimentos()`. |
| `app/Modulos/Manutencao/Controllers/PlanosPreventivaController.php` (rewrite) | `index`, `create`, `store`, `show`, `publish`, `toggleStatus`, `addService`, `removeService`, `addVeiculo`, `removeVeiculo`, `vencimentos`, `run`. |
| `app/Modulos/Manutencao/Views/preventiva/planos_index.php` (rewrite) | List with tabs/search/pagination. |
| `app/Modulos/Manutencao/Views/preventiva/plano_create.php` (new, replaces `plano_form.php`) | Create form (name, medição, associação). |
| `app/Modulos/Manutencao/Views/preventiva/plano_show.php` (rewrite) | Detail page, Serviços/Veículos tabs, publish/inativar. |
| `app/Modulos/Manutencao/Views/preventiva/plano_service_form.php` (new) | Add-service form. |
| `app/Modulos/Manutencao/Views/preventiva/plano_form.php` (delete) | Replaced by `plano_create.php`. |
| `index.php` (modify) | Update the `planos_preventiva` route's `actions` allow-list. |

---

## Task 1: Database migration

**Files:**
- Create: `db/migrations/2026-09-19-plano-manutencao-redesign.sql` (gitignored like the previous migration file — applied directly, not committed)

- [ ] **Step 1: Write the migration SQL**

```sql
-- db/migrations/2026-09-19-plano-manutencao-redesign.sql

DROP TABLE IF EXISTS man_maintenance_due;
DROP TABLE IF EXISTS man_maintenance_tasks;
DROP TABLE IF EXISTS man_maintenance_plans;

CREATE TABLE man_maintenance_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    filial_id INT NULL,
    nome VARCHAR(160) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_services_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE man_maintenance_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    filial_id INT NULL,
    nome VARCHAR(180) NOT NULL,
    medicao_tipo ENUM('odometro','horimetro') NOT NULL DEFAULT 'odometro',
    association_type ENUM('unidade','veiculos') NOT NULL DEFAULT 'veiculos',
    association_filial_id INT NULL,
    status ENUM('rascunho','ativo','inativo') NOT NULL DEFAULT 'rascunho',
    criado_por INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_plans_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE man_maintenance_plan_veiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    veiculo_id INT NOT NULL,
    UNIQUE KEY uq_plan_veiculo (plan_id, veiculo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE man_maintenance_plan_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    filial_id INT NULL,
    plan_id INT NOT NULL,
    service_id INT NOT NULL,
    tipo ENUM('recorrente','unico') NOT NULL DEFAULT 'recorrente',
    intervalo_tempo_valor INT NULL,
    intervalo_tempo_unidade ENUM('dias','meses','anos') NULL,
    alerta_tempo_dias INT NULL,
    intervalo_medicao INT NULL,
    alerta_medicao INT NULL,
    ultima_execucao_em DATETIME NULL,
    ultimo_valor_medicao INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_plan_services_plan (plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE man_maintenance_due (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    filial_id INT NULL,
    plan_id INT NOT NULL,
    plan_service_id INT NOT NULL,
    veiculo_id INT NOT NULL,
    status ENUM('ok','due_soon','overdue') NOT NULL DEFAULT 'ok',
    due_date DATE NULL,
    due_medicao INT NULL,
    last_check_at DATETIME NULL,
    generated_ss_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_due_plan_service_veiculo (plan_service_id, veiculo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 2: Apply it with a throwaway runner (same pattern as the OS migration)**

```php
<?php
// __migrate_plano.php
$cfg = require __DIR__ . '/config.php';
$dsn = "mysql:host={$cfg['db_host']};port={$cfg['db_port']};dbname={$cfg['db_name']};charset=utf8mb4";
$db = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$raw = file_get_contents(__DIR__ . '/db/migrations/2026-09-19-plano-manutencao-redesign.sql');
$lines = array_filter(explode("\n", $raw), fn($l) => !preg_match('/^\s*--/', $l));
foreach (array_filter(array_map('trim', explode(';', implode("\n", $lines)))) as $stmt) {
    echo "Running: " . substr(preg_replace('/\s+/', ' ', $stmt), 0, 70) . "...\n";
    $db->exec($stmt);
}
echo "Migration applied.\n";
```

Run: `& "C:\xampp\php\php.exe" "C:\xampp\htdocs\frotaandrade\__migrate_plano.php"` (note: this reuses the fixed comment-stripping logic learned from the OS migration — strip full-line comments before splitting on `;`, never rely on `str_starts_with($stmt, '--')` on a chunk that has a comment merged with real SQL).

- [ ] **Step 3: Verify with a throwaway check script**

```php
<?php
// __verify_plano_migration.php
require __DIR__ . '/bootstrap.php';
$db = db();
foreach (['man_maintenance_services','man_maintenance_plans','man_maintenance_plan_veiculos','man_maintenance_plan_services','man_maintenance_due'] as $t) {
    $exists = $db->query("SHOW TABLES LIKE '$t'")->fetch();
    echo "$t: " . ($exists ? 'OK' : 'MISSING') . "\n";
}
```

Run and expect `OK` for all 5 tables, then delete both throwaway scripts.

- [ ] **Step 4: No commit for the `.sql` file** (gitignored, same as the OS migration — confirm with `git status` that it's not tracked, no action needed).

---

## Task 2: Services catalog (model, controller, view)

**Files:**
- Create: `app/Modulos/Manutencao/Models/ManutencaoServicosModel.php`
- Create: `app/Modulos/Manutencao/Controllers/ManutencaoServicosController.php`
- Create: `app/Modulos/Manutencao/Views/preventiva/servicos_index.php`
- Modify: `index.php` (new route)

**Interfaces:**
- Produces: `ManutencaoServicosModel::listar(bool $apenasAtivos = false): array`, `criar(string $nome): int`, `toggleAtivo(int $id, bool $ativo): bool`.

- [ ] **Step 1: Write the model**

```php
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
```

- [ ] **Step 2: Write the controller**

```php
<?php
namespace App\Modulos\Manutencao\Controllers;

use App\Core\View;
use App\Modulos\Manutencao\Models\ManutencaoServicosModel;

class ManutencaoServicosController
{
    private ManutencaoServicosModel $model;

    public function __construct()
    {
        $this->model = new ManutencaoServicosModel(db(), current_company_id(), current_branch_id());
    }

    public function index(): void
    {
        if (!has_permission('preventiva.view')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php');
            return;
        }
        View::render('Manutencao', 'preventiva/servicos_index', [
            'title' => 'Serviços de manutenção',
            'servicos' => $this->model->listar(),
        ]);
    }

    public function store(): void
    {
        if (!has_permission('preventiva.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=manutencao_servicos');
            return;
        }
        $nome = trim($_POST['nome'] ?? '');
        if ($nome === '') {
            flash('error', 'Informe o nome do serviço.');
        } else {
            $this->model->criar($nome);
            flash('success', 'Serviço criado.');
        }
        header('Location: index.php?page=manutencao_servicos');
    }

    public function toggle(): void
    {
        if (!has_permission('preventiva.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=manutencao_servicos');
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $ativo = !empty($_POST['ativo']);
        $this->model->toggleAtivo($id, $ativo);
        flash('success', 'Serviço atualizado.');
        header('Location: index.php?page=manutencao_servicos');
    }
}
```

- [ ] **Step 3: Write the view**

```php
<?php
$servicos = $servicos ?? [];
?>
<div class="max-w-3xl mx-auto px-4 py-6 space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-semibold text-slate-900">Serviços de manutenção</h2>
        <a class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" href="index.php?page=planos_preventiva">Voltar aos planos</a>
    </div>

    <?php if (has_permission('preventiva.manage')): ?>
        <form class="flex items-center gap-2 bg-white border border-slate-200 rounded-2xl p-4 shadow-sm" method="post" action="index.php?mod=manutencao&ctrl=ManutencaoServicos&action=store">
<?= csrf_field() ?>
            <input class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm" name="nome" placeholder="Ex: Troca de óleo" required>
            <button class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Adicionar</button>
        </form>
    <?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm divide-y divide-slate-100">
        <?php foreach ($servicos as $s): ?>
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-sm <?= $s['ativo'] ? 'text-slate-800 font-medium' : 'text-slate-400 line-through' ?>"><?= sanitize($s['nome']) ?></span>
                <?php if (has_permission('preventiva.manage')): ?>
                    <form method="post" action="index.php?mod=manutencao&ctrl=ManutencaoServicos&action=toggle">
<?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= sanitize($s['id']) ?>">
                        <input type="hidden" name="ativo" value="<?= $s['ativo'] ? '0' : '1' ?>">
                        <button class="text-xs font-semibold text-blue-600 hover:underline"><?= $s['ativo'] ? 'Inativar' : 'Reativar' ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if (!$servicos): ?>
            <div class="px-4 py-6 text-sm text-slate-500 text-center">Nenhum serviço cadastrado.</div>
        <?php endif; ?>
    </div>
</div>
```

- [ ] **Step 4: Register the route**

In `index.php`, add next to the `planos_preventiva`/`vencimentos_preventiva` entries:

```php
            'manutencao_servicos' => ['mod' => 'manutencao', 'ctrl' => 'ManutencaoServicos', 'action' => 'index', 'actions' => ['index', 'store', 'toggle']],
```

- [ ] **Step 5: Syntax check**

```bash
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Manutencao\Models\ManutencaoServicosModel.php"
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Manutencao\Controllers\ManutencaoServicosController.php"
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Manutencao\Views\preventiva\servicos_index.php"
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\index.php"
```

- [ ] **Step 6: Verify live** (server is up, login with the credential from this session) — GET `index.php?page=manutencao_servicos`, POST a new service via `store`, confirm it lists, POST `toggle` and confirm the label flips to "Reativar".

- [ ] **Step 7: Commit**

```bash
cd "C:/xampp/htdocs/frotaandrade"
git add app/Modulos/Manutencao/Models/ManutencaoServicosModel.php app/Modulos/Manutencao/Controllers/ManutencaoServicosController.php app/Modulos/Manutencao/Views/preventiva/servicos_index.php index.php
git commit -m "Add reusable maintenance services catalog (model, controller, view, route)"
```

---

## Task 3: Rewrite `PlanosPreventivaModel`

**Files:**
- Modify (rewrite): `app/Modulos/Manutencao/Models/PlanosPreventivaModel.php`

**Interfaces:**
- Produces:
  - `listarPlanos(array $filters): array` — filters: `association_type`, `q`; returns each plan with `servicos_count`, `veiculos_count`.
  - `obterPlano(int $id): ?array` — includes `servicos` (each joined with `man_maintenance_services.nome`) and `veiculos` (resolved per `association_type`).
  - `criarPlano(array $data): int`
  - `publicar(int $id): bool` (rascunho→ativo)
  - `toggleStatus(int $id, string $status): bool` (ativo↔inativo)
  - `addVeiculo(int $planId, int $veiculoId): void` / `removeVeiculo(int $planId, int $veiculoId): void`
  - `addServicos(int $planId, array $serviceIds, array $recorrencia): void` / `removeServico(int $planServiceId, int $planId): void`
  - `veiculosDoPlano(int $planId): array` (resolves `association_type`)
  - `contarVencimentos(): array` (unchanged signature from before, still used by `vencimentos()`)
  - `listarVencimentos(array $filters): array` (rewritten join)
  - `processarPreventiva(SolicitacoesServicoModel $ssModel, AuditoriaModel $audit, bool $criarSS): array` (rewritten)

- [ ] **Step 1: Write the full model file**

```php
<?php
namespace App\Modulos\Manutencao\Models;

class PlanosPreventivaModel
{
    private \PDO $db;
    private int $empresaId;
    private ?int $filialId;
    private array $filiais;

    public function __construct(\PDO $db, int $empresaId, ?int $filialId, array $filiais = [])
    {
        $this->db = $db;
        $this->empresaId = $empresaId;
        $this->filialId = $filialId;
        $this->filiais = $filiais;
    }

    public function listarFiliais(): array
    {
        $stmt = $this->db->prepare('SELECT id, name FROM cad_filiais WHERE empresa_id = ? ORDER BY name ASC');
        $stmt->execute([$this->empresaId]);
        return $stmt->fetchAll();
    }

    public function listarVeiculos(): array
    {
        $stmt = $this->db->prepare('SELECT id, plate, model, filial_id FROM cad_veiculos WHERE empresa_id = ? ORDER BY plate ASC');
        $stmt->execute([$this->empresaId]);
        return $stmt->fetchAll();
    }

    public function listarPlanos(array $filters = []): array
    {
        $sql = "SELECT p.*,
                    (SELECT COUNT(*) FROM man_maintenance_plan_services ps WHERE ps.plan_id = p.id) AS servicos_count,
                    (CASE WHEN p.association_type = 'unidade'
                        THEN (SELECT COUNT(*) FROM cad_veiculos v WHERE v.empresa_id = p.empresa_id AND v.filial_id = p.association_filial_id)
                        ELSE (SELECT COUNT(*) FROM man_maintenance_plan_veiculos pv WHERE pv.plan_id = p.id)
                    END) AS veiculos_count
                FROM man_maintenance_plans p
                WHERE p.empresa_id = ?";
        $params = [$this->empresaId];
        if (!empty($filters['association_type'])) {
            $sql .= ' AND p.association_type = ?';
            $params[] = $filters['association_type'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND p.nome LIKE ?';
            $params[] = '%' . $filters['q'] . '%';
        }
        $sql .= ' ORDER BY p.created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obterPlano(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM man_maintenance_plans WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $this->empresaId]);
        $plan = $stmt->fetch();
        if (!$plan) {
            return null;
        }
        $plan['servicos'] = $this->listarServicosDoPlano($id);
        $plan['veiculos'] = $this->veiculosDoPlano($id, $plan);
        return $plan;
    }

    public function criarPlano(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO man_maintenance_plans (empresa_id, filial_id, nome, medicao_tipo, association_type, association_filial_id, status, criado_por, created_at)
             VALUES (?, ?, ?, ?, ?, ?, "rascunho", ?, NOW())'
        );
        $stmt->execute([
            $this->empresaId,
            $this->filialId,
            $data['nome'],
            $data['medicao_tipo'],
            $data['association_type'],
            $data['association_filial_id'] ?? null,
            $data['criado_por'] ?? null,
        ]);
        $planId = (int)$this->db->lastInsertId();
        if ($data['association_type'] === 'veiculos' && !empty($data['veiculo_ids'])) {
            foreach ($data['veiculo_ids'] as $vid) {
                $this->addVeiculo($planId, (int)$vid);
            }
        }
        return $planId;
    }

    public function publicar(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE man_maintenance_plans SET status = 'ativo', updated_at = NOW() WHERE id = ? AND empresa_id = ? AND status = 'rascunho'");
        $stmt->execute([$id, $this->empresaId]);
        return $stmt->rowCount() > 0;
    }

    public function toggleStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            return false;
        }
        $stmt = $this->db->prepare("UPDATE man_maintenance_plans SET status = ?, updated_at = NOW() WHERE id = ? AND empresa_id = ? AND status IN ('ativo','inativo')");
        $stmt->execute([$status, $id, $this->empresaId]);
        return $stmt->rowCount() > 0;
    }

    public function addVeiculo(int $planId, int $veiculoId): void
    {
        $this->db->prepare(
            'INSERT IGNORE INTO man_maintenance_plan_veiculos (plan_id, veiculo_id) VALUES (?, ?)'
        )->execute([$planId, $veiculoId]);
    }

    public function removeVeiculo(int $planId, int $veiculoId): void
    {
        $this->db->prepare(
            'DELETE FROM man_maintenance_plan_veiculos WHERE plan_id = ? AND veiculo_id = ?'
        )->execute([$planId, $veiculoId]);
    }

    public function veiculosDoPlano(int $planId, ?array $plan = null): array
    {
        $plan = $plan ?? $this->obterPlanoRaw($planId);
        if (!$plan) {
            return [];
        }
        if ($plan['association_type'] === 'unidade') {
            $stmt = $this->db->prepare('SELECT id, plate, model FROM cad_veiculos WHERE empresa_id = ? AND filial_id = ? ORDER BY plate ASC');
            $stmt->execute([$this->empresaId, $plan['association_filial_id']]);
            return $stmt->fetchAll();
        }
        $stmt = $this->db->prepare(
            'SELECT v.id, v.plate, v.model FROM man_maintenance_plan_veiculos pv
             INNER JOIN cad_veiculos v ON v.id = pv.veiculo_id
             WHERE pv.plan_id = ? ORDER BY v.plate ASC'
        );
        $stmt->execute([$planId]);
        return $stmt->fetchAll();
    }

    private function obterPlanoRaw(int $planId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM man_maintenance_plans WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$planId, $this->empresaId]);
        return $stmt->fetch() ?: null;
    }

    public function listarServicosDoPlano(int $planId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ps.*, s.nome AS service_nome
             FROM man_maintenance_plan_services ps
             INNER JOIN man_maintenance_services s ON s.id = ps.service_id
             WHERE ps.plan_id = ? ORDER BY ps.created_at ASC'
        );
        $stmt->execute([$planId]);
        return $stmt->fetchAll();
    }

    public function addServicos(int $planId, array $serviceIds, array $recorrencia): void
    {
        $filialId = $this->filialId;
        foreach ($serviceIds as $serviceId) {
            $stmt = $this->db->prepare(
                'INSERT INTO man_maintenance_plan_services
                    (empresa_id, filial_id, plan_id, service_id, tipo, intervalo_tempo_valor, intervalo_tempo_unidade, alerta_tempo_dias, intervalo_medicao, alerta_medicao, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $this->empresaId,
                $filialId,
                $planId,
                (int)$serviceId,
                $recorrencia['tipo'] ?? 'recorrente',
                $recorrencia['intervalo_tempo_valor'] ?? null,
                $recorrencia['intervalo_tempo_unidade'] ?? null,
                $recorrencia['alerta_tempo_dias'] ?? null,
                $recorrencia['intervalo_medicao'] ?? null,
                $recorrencia['alerta_medicao'] ?? null,
            ]);
        }
    }

    public function removeServico(int $planServiceId, int $planId): void
    {
        $this->db->prepare(
            'DELETE FROM man_maintenance_plan_services WHERE id = ? AND plan_id = ?'
        )->execute([$planServiceId, $planId]);
    }

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

    public function listarVencimentos(array $filters = []): array
    {
        $sql = 'SELECT d.*, s.nome AS tarefa_nome, p.nome AS plano_nome, v.plate AS vehicle_plate
                FROM man_maintenance_due d
                INNER JOIN man_maintenance_plan_services ps ON ps.id = d.plan_service_id
                INNER JOIN man_maintenance_services s ON s.id = ps.service_id
                INNER JOIN man_maintenance_plans p ON p.id = d.plan_id
                LEFT JOIN cad_veiculos v ON v.id = d.veiculo_id
                WHERE d.empresa_id = ?';
        $params = [$this->empresaId];
        if (!empty($filters['status'])) {
            $sql .= ' AND d.status = ?';
            $params[] = $filters['status'];
        }
        $sql .= ' ORDER BY d.updated_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function normalizarDiasIntervalo(?int $valor, ?string $unidade): ?int
    {
        if (!$valor) {
            return null;
        }
        return match ($unidade) {
            'anos' => $valor * 365,
            'meses' => $valor * 30,
            default => $valor,
        };
    }

    public function processarPreventiva(SolicitacoesServicoModel $ssModel, AuditoriaModel $audit, bool $criarSS = true): array
    {
        $stmt = $this->db->prepare("SELECT * FROM man_maintenance_plans WHERE empresa_id = ? AND status = 'ativo'");
        $stmt->execute([$this->empresaId]);
        $planos = $stmt->fetchAll();

        $updated = 0;
        $ssCriadas = 0;

        foreach ($planos as $plano) {
            $veiculos = $this->veiculosDoPlano((int)$plano['id'], $plano);
            $servicos = $this->listarServicosDoPlano((int)$plano['id']);

            foreach ($veiculos as $veiculo) {
                $veiculoRow = $this->db->prepare('SELECT nin_km_preventiva, plate FROM cad_veiculos WHERE id = ?');
                $veiculoRow->execute([$veiculo['id']]);
                $vRow = $veiculoRow->fetch();
                $odometroAtual = $vRow['nin_km_preventiva'] ?? null;
                $placa = $vRow['plate'] ?? '';

                foreach ($servicos as $servico) {
                    if ($servico['tipo'] === 'unico') {
                        continue;
                    }

                    $dueDate = null;
                    $dueMedicao = null;
                    $status = 'ok';
                    $hoje = new \DateTimeImmutable('today');

                    $intervaloDias = $this->normalizarDiasIntervalo(
                        $servico['intervalo_tempo_valor'] !== null ? (int)$servico['intervalo_tempo_valor'] : null,
                        $servico['intervalo_tempo_unidade']
                    );
                    if ($intervaloDias) {
                        $ultima = $servico['ultima_execucao_em'] ?: $servico['created_at'];
                        $dt = new \DateTime($ultima);
                        $dt->modify('+' . $intervaloDias . ' days');
                        $dueDate = $dt->format('Y-m-d');
                        $dueDt = new \DateTimeImmutable($dueDate);
                        $diffDias = (int)$hoje->diff($dueDt)->format('%r%a');
                        $alertaDias = (int)($servico['alerta_tempo_dias'] ?? 0);
                        if ($diffDias <= 0) {
                            $status = 'overdue';
                        } elseif ($diffDias <= $alertaDias) {
                            $status = 'due_soon';
                        }
                    }

                    if ($servico['intervalo_medicao'] && $odometroAtual !== null) {
                        $ultimoValor = (int)($servico['ultimo_valor_medicao'] ?? 0);
                        $dueMedicao = $ultimoValor + (int)$servico['intervalo_medicao'];
                        $restante = $dueMedicao - (int)$odometroAtual;
                        $alertaMedicao = (int)($servico['alerta_medicao'] ?? 0);
                        if ($restante <= 0) {
                            $status = 'overdue';
                        } elseif ($restante <= $alertaMedicao && $status !== 'overdue') {
                            $status = 'due_soon';
                        }
                    }

                    $dueRowId = $this->upsertDueRow([
                        'plan_id' => (int)$plano['id'],
                        'plan_service_id' => (int)$servico['id'],
                        'veiculo_id' => (int)$veiculo['id'],
                        'status' => $status,
                        'due_date' => $dueDate,
                        'due_medicao' => $dueMedicao,
                    ]);
                    $updated++;

                    if ($status === 'overdue' && $criarSS) {
                        $exists = $this->db->prepare('SELECT generated_ss_id FROM man_maintenance_due WHERE id = ?');
                        $exists->execute([$dueRowId]);
                        $ssId = $exists->fetchColumn();
                        if (!$ssId) {
                            $ssId = $ssModel->criar([
                                'filial_id' => $this->filialId,
                                'source_type' => 'preventive_due',
                                'source_table' => 'man_maintenance_due',
                                'source_id' => $dueRowId,
                                'source_ref' => 'plan-' . $plano['id'] . '-service-' . $servico['id'] . '-veiculo-' . $veiculo['id'],
                                'source_payload_json' => json_encode(['plan_id' => $plano['id'], 'plan_service_id' => $servico['id']]),
                                'veiculo_id' => $veiculo['id'],
                                'prioridade' => 'media',
                                'titulo' => 'Preventiva: ' . $servico['service_nome'],
                                'descricao' => 'Vencimento do plano ' . $plano['nome'] . ' para veiculo ' . $placa,
                                'status' => 'aberta',
                                'criada_por' => null,
                            ]);
                            $this->db->prepare('UPDATE man_maintenance_due SET generated_ss_id = ? WHERE id = ?')->execute([$ssId, $dueRowId]);
                            $audit->registrar('preventiva', $dueRowId, 'ss_created', null, ['ss_id' => $ssId], null);
                            $ssCriadas++;
                        }
                    }
                }
            }
        }

        return ['due_updated' => $updated, 'ss_criadas' => $ssCriadas];
    }

    private function upsertDueRow(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO man_maintenance_due (empresa_id, filial_id, plan_id, plan_service_id, veiculo_id, status, due_date, due_medicao, last_check_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE status = VALUES(status), due_date = VALUES(due_date), due_medicao = VALUES(due_medicao), last_check_at = NOW(), updated_at = NOW()'
        );
        $stmt->execute([
            $this->empresaId,
            $this->filialId,
            $data['plan_id'],
            $data['plan_service_id'],
            $data['veiculo_id'],
            $data['status'],
            $data['due_date'],
            $data['due_medicao'],
        ]);
        $id = (int)$this->db->lastInsertId();
        if (!$id) {
            $stmt2 = $this->db->prepare(
                'SELECT id FROM man_maintenance_due WHERE plan_service_id = ? AND veiculo_id = ?'
            );
            $stmt2->execute([$data['plan_service_id'], $data['veiculo_id']]);
            $id = (int)$stmt2->fetchColumn();
        }
        return $id;
    }
}
```

- [ ] **Step 2: Syntax check**

Run: `& "C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Manutencao\Models\PlanosPreventivaModel.php"`

- [ ] **Step 3: Verify against the real DB with a throwaway script**

```php
<?php
// __verify_plano_model.php
require __DIR__ . '/bootstrap.php';
$db = db();
$model = new App\Modulos\Manutencao\Models\PlanosPreventivaModel($db, 1, null, []);
$servicoModel = new App\Modulos\Manutencao\Models\ManutencaoServicosModel($db, 1, null);
$servicoId = $servicoModel->criar('Teste troca de oleo');
$veiculoId = (int)$db->query('SELECT id FROM cad_veiculos LIMIT 1')->fetchColumn();

$planId = $model->criarPlano([
    'nome' => 'Plano teste',
    'medicao_tipo' => 'odometro',
    'association_type' => 'veiculos',
    'veiculo_ids' => [$veiculoId],
]);
echo "Plano criado: $planId\n";
$model->addServicos($planId, [$servicoId], ['tipo' => 'recorrente', 'intervalo_medicao' => 10000, 'alerta_medicao' => 1000]);
$plan = $model->obterPlano($planId);
echo "Servicos no plano: " . count($plan['servicos']) . "\n";
echo "Veiculos no plano: " . count($plan['veiculos']) . "\n";
echo "Publicar: " . ($model->publicar($planId) ? 'ok' : 'falhou') . "\n";
$plan = $model->obterPlano($planId);
echo "Status apos publicar: " . $plan['status'] . "\n";

$db->prepare('DELETE FROM man_maintenance_plan_services WHERE plan_id = ?')->execute([$planId]);
$db->prepare('DELETE FROM man_maintenance_plan_veiculos WHERE plan_id = ?')->execute([$planId]);
$db->prepare('DELETE FROM man_maintenance_plans WHERE id = ?')->execute([$planId]);
$db->prepare('DELETE FROM man_maintenance_services WHERE id = ?')->execute([$servicoId]);
echo "Limpo.\n";
```

Run and expect: plan/servico counts correct, status becomes `ativo` after publish, no errors, then delete the script.

- [ ] **Step 4: Commit**

```bash
cd "C:/xampp/htdocs/frotaandrade"
git add app/Modulos/Manutencao/Models/PlanosPreventivaModel.php
git commit -m "Rewrite PlanosPreventivaModel for multi-vehicle plans, services catalog, and fixed odometer column"
```

---

## Task 4: Rewrite `PlanosPreventivaController`

**Files:**
- Modify (rewrite): `app/Modulos/Manutencao/Controllers/PlanosPreventivaController.php`
- Modify: `index.php` (route actions)

- [ ] **Step 1: Write the full controller file**

```php
<?php
namespace App\Modulos\Manutencao\Controllers;

use App\Core\View;
use App\Modulos\Manutencao\Models\PlanosPreventivaModel;
use App\Modulos\Manutencao\Models\ManutencaoServicosModel;
use App\Modulos\Manutencao\Models\SolicitacoesServicoModel;
use App\Modulos\Manutencao\Models\AuditoriaModel;

class PlanosPreventivaController
{
    private PlanosPreventivaModel $model;
    private ManutencaoServicosModel $servicosModel;
    private SolicitacoesServicoModel $ssModel;
    private AuditoriaModel $auditoria;

    public function __construct()
    {
        $db = db();
        $empresaId = current_company_id();
        $filialId = current_branch_id();
        $filiais = current_branch_ids();
        $this->model = new PlanosPreventivaModel($db, $empresaId, $filialId, $filiais);
        $this->servicosModel = new ManutencaoServicosModel($db, $empresaId, $filialId);
        $this->ssModel = new SolicitacoesServicoModel($db, $empresaId, $filialId, $filiais);
        $this->auditoria = new AuditoriaModel($db, $empresaId, $filialId, $filiais);
    }

    public function index(): void
    {
        if (!has_permission('preventiva.view')) {
            flash('error', 'Sem permissao para preventiva.');
            header('Location: index.php');
            return;
        }
        $filters = [
            'association_type' => $_GET['tab'] ?? null,
            'q' => trim($_GET['q'] ?? ''),
        ];
        $plans = $this->model->listarPlanos($filters);
        View::render('Manutencao', 'preventiva/planos_index', [
            'title' => 'Plano de manutenção',
            'plans' => $plans,
            'filters' => $filters,
        ]);
    }

    public function create(): void
    {
        if (!has_permission('preventiva.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=planos_preventiva');
            return;
        }
        View::render('Manutencao', 'preventiva/plano_create', [
            'title' => 'Novo plano de manutenção',
            'filiais' => $this->model->listarFiliais(),
            'veiculos' => $this->model->listarVeiculos(),
        ]);
    }

    public function store(): void
    {
        if (!has_permission('preventiva.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=planos_preventiva');
            return;
        }
        $nome = trim($_POST['nome'] ?? '');
        $medicaoTipo = ($_POST['medicao_tipo'] ?? 'odometro') === 'horimetro' ? 'horimetro' : 'odometro';
        $associationType = ($_POST['association_type'] ?? 'veiculos') === 'unidade' ? 'unidade' : 'veiculos';
        $associationFilialId = $_POST['association_filial_id'] ?? null;
        $veiculoIds = $_POST['veiculo_ids'] ?? [];

        if ($nome === '') {
            flash('error', 'Informe o nome do plano.');
            header('Location: index.php?mod=manutencao&ctrl=PlanosPreventiva&action=create');
            return;
        }
        if ($associationType === 'unidade' && !$associationFilialId) {
            flash('error', 'Selecione a unidade.');
            header('Location: index.php?mod=manutencao&ctrl=PlanosPreventiva&action=create');
            return;
        }
        if ($associationType === 'veiculos' && empty($veiculoIds)) {
            flash('error', 'Selecione ao menos um veículo.');
            header('Location: index.php?mod=manutencao&ctrl=PlanosPreventiva&action=create');
            return;
        }

        $planId = $this->model->criarPlano([
            'nome' => $nome,
            'medicao_tipo' => $medicaoTipo,
            'association_type' => $associationType,
            'association_filial_id' => $associationType === 'unidade' ? (int)$associationFilialId : null,
            'veiculo_ids' => $veiculoIds,
            'criado_por' => current_user()['id'] ?? null,
        ]);
        $this->auditoria->registrar('preventiva', $planId, 'create_plan', null, ['nome' => $nome], current_user()['id'] ?? null);
        flash('success', 'Plano criado.');
        header('Location: index.php?mod=manutencao&ctrl=PlanosPreventiva&action=show&id=' . $planId);
    }

    public function show(): void
    {
        if (!has_permission('preventiva.view')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php');
            return;
        }
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $plan = $this->model->obterPlano($id);
        if (!$plan) {
            flash('error', 'Plano nao encontrado.');
            header('Location: index.php?page=planos_preventiva');
            return;
        }
        View::render('Manutencao', 'preventiva/plano_show', [
            'title' => $plan['nome'],
            'plan' => $plan,
            'servicosCatalogo' => $this->servicosModel->listar(true),
            'veiculosDisponiveis' => $this->model->listarVeiculos(),
        ]);
    }

    public function publish(): void
    {
        if (!has_permission('preventiva.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=planos_preventiva');
            return;
        }
        $id = (int)($_POST['plan_id'] ?? 0);
        if ($this->model->publicar($id)) {
            $this->auditoria->registrar('preventiva', $id, 'publish', null, [], current_user()['id'] ?? null);
            flash('success', 'Plano publicado.');
        } else {
            flash('error', 'Nao foi possivel publicar o plano.');
        }
        header('Location: index.php?mod=manutencao&ctrl=PlanosPreventiva&action=show&id=' . $id);
    }

    public function toggleStatus(): void
    {
        if (!has_permission('preventiva.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=planos_preventiva');
            return;
        }
        $id = (int)($_POST['plan_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if ($this->model->toggleStatus($id, $status)) {
            $this->auditoria->registrar('preventiva', $id, 'status', null, ['status' => $status], current_user()['id'] ?? null);
            flash('success', 'Status do plano atualizado.');
        } else {
            flash('error', 'Nao foi possivel atualizar o status.');
        }
        header('Location: index.php?mod=manutencao&ctrl=PlanosPreventiva&action=show&id=' . $id);
    }

    public function addService(): void
    {
        if (!has_permission('preventiva.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=planos_preventiva');
            return;
        }
        $planId = (int)($_POST['plan_id'] ?? 0);
        $serviceIds = $_POST['service_ids'] ?? [];
        $tipo = ($_POST['tipo'] ?? 'recorrente') === 'unico' ? 'unico' : 'recorrente';
        $intervaloTempoValor = $_POST['intervalo_tempo_valor'] ?? null;
        $intervaloTempoUnidade = $_POST['intervalo_tempo_unidade'] ?? null;
        $alertaTempoDias = $_POST['alerta_tempo_dias'] ?? null;
        $intervaloMedicao = $_POST['intervalo_medicao'] ?? null;
        $alertaMedicao = $_POST['alerta_medicao'] ?? null;

        if (!$planId || empty($serviceIds) || !$this->model->obterPlano($planId)) {
            flash('error', 'Selecione ao menos um servico.');
            header('Location: index.php?mod=manutencao&ctrl=PlanosPreventiva&action=show&id=' . $planId);
            return;
        }
        if ($tipo === 'recorrente' && !$intervaloTempoValor && !$intervaloMedicao) {
            flash('error', 'Preencha pelo menos uma recorrencia: por tempo, odometro/horimetro ou ambas.');
            header('Location: index.php?mod=manutencao&ctrl=PlanosPreventiva&action=show&id=' . $planId);
            return;
        }

        $this->model->addServicos($planId, $serviceIds, [
            'tipo' => $tipo,
            'intervalo_tempo_valor' => $intervaloTempoValor !== '' ? $intervaloTempoValor : null,
            'intervalo_tempo_unidade' => $intervaloTempoUnidade !== '' ? $intervaloTempoUnidade : null,
            'alerta_tempo_dias' => $alertaTempoDias !== '' ? $alertaTempoDias : null,
            'intervalo_medicao' => $intervaloMedicao !== '' ? $intervaloMedicao : null,
            'alerta_medicao' => $alertaMedicao !== '' ? $alertaMedicao : null,
        ]);
        $this->auditoria->registrar('preventiva', $planId, 'add_services', null, ['service_ids' => $serviceIds], current_user()['id'] ?? null);
        flash('success', 'Servico(s) adicionado(s).');
        header('Location: index.php?mod=manutencao&ctrl=PlanosPreventiva&action=show&id=' . $planId);
    }

    public function removeService(): void
    {
        if (!has_permission('preventiva.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=planos_preventiva');
            return;
        }
        $planId = (int)($_POST['plan_id'] ?? 0);
        $planServiceId = (int)($_POST['plan_service_id'] ?? 0);
        $this->model->removeServico($planServiceId, $planId);
        flash('success', 'Servico removido.');
        header('Location: index.php?mod=manutencao&ctrl=PlanosPreventiva&action=show&id=' . $planId);
    }

    public function addVeiculo(): void
    {
        if (!has_permission('preventiva.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=planos_preventiva');
            return;
        }
        $planId = (int)($_POST['plan_id'] ?? 0);
        $veiculoId = (int)($_POST['veiculo_id'] ?? 0);
        if ($planId && $veiculoId) {
            $this->model->addVeiculo($planId, $veiculoId);
            flash('success', 'Veiculo adicionado.');
        }
        header('Location: index.php?mod=manutencao&ctrl=PlanosPreventiva&action=show&id=' . $planId);
    }

    public function removeVeiculo(): void
    {
        if (!has_permission('preventiva.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=planos_preventiva');
            return;
        }
        $planId = (int)($_POST['plan_id'] ?? 0);
        $veiculoId = (int)($_POST['veiculo_id'] ?? 0);
        $this->model->removeVeiculo($planId, $veiculoId);
        flash('success', 'Veiculo removido.');
        header('Location: index.php?mod=manutencao&ctrl=PlanosPreventiva&action=show&id=' . $planId);
    }

    public function vencimentos(): void
    {
        if (!has_permission('preventiva.view')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php');
            return;
        }
        $status = $_GET['status'] ?? null;
        $due = $this->model->listarVencimentos(['status' => $status]);
        $counts = $this->model->contarVencimentos();
        View::render('Manutencao', 'preventiva/vencimentos', [
            'title' => 'Lembretes',
            'vencimentos' => $due,
            'status' => $status,
            'counts' => $counts,
        ]);
    }

    public function run(): void
    {
        if (!has_permission('preventiva.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php');
            return;
        }
        $result = $this->model->processarPreventiva($this->ssModel, $this->auditoria, true);
        flash('success', 'Preventiva executada. Vencimentos: ' . $result['due_updated'] . ' | SS criadas: ' . $result['ss_criadas']);
        header('Location: index.php?page=vencimentos_preventiva');
    }
}
```

- [ ] **Step 2: Update the route's allowed actions in `index.php`**

Replace the `planos_preventiva` entry:

```php
            'planos_preventiva' => ['mod' => 'manutencao', 'ctrl' => 'PlanosPreventiva', 'action' => 'index', 'actions' => ['index', 'create', 'store', 'show', 'publish', 'toggleStatus', 'addService', 'removeService', 'addVeiculo', 'removeVeiculo', 'run']],
```

(`form`/`save` are gone — replaced by `create`/`store`.)

- [ ] **Step 3: Syntax check**

```bash
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Manutencao\Controllers\PlanosPreventivaController.php"
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\index.php"
```

- [ ] **Step 4: Commit**

```bash
cd "C:/xampp/htdocs/frotaandrade"
git add app/Modulos/Manutencao/Controllers/PlanosPreventivaController.php index.php
git commit -m "Rewrite PlanosPreventivaController for the new plan/service/vehicle model"
```

---

## Task 5: Views — list, create, detail, add-service form

**Files:**
- Rewrite: `app/Modulos/Manutencao/Views/preventiva/planos_index.php`
- Create: `app/Modulos/Manutencao/Views/preventiva/plano_create.php`
- Rewrite: `app/Modulos/Manutencao/Views/preventiva/plano_show.php`
- Create: `app/Modulos/Manutencao/Views/preventiva/plano_service_form.php`
- Delete: `app/Modulos/Manutencao/Views/preventiva/plano_form.php`

- [ ] **Step 1: Rewrite the list view**

```php
<?php
$plans = $plans ?? [];
$filters = $filters ?? [];
$statusLabels = ['rascunho' => 'Rascunho', 'ativo' => 'Ativo', 'inativo' => 'Inativo'];
$statusColors = [
    'rascunho' => 'bg-slate-100 text-slate-700',
    'ativo' => 'bg-emerald-100 text-emerald-800',
    'inativo' => 'bg-gray-200 text-gray-700',
];
?>
<div class="max-w-6xl mx-auto px-4 py-6 space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-semibold text-slate-900">Plano de manutenção</h2>
        <?php if (has_permission('preventiva.manage')): ?>
            <a class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800" href="index.php?mod=manutencao&ctrl=PlanosPreventiva&action=create">Novo plano de manutenção</a>
        <?php endif; ?>
    </div>

    <div class="flex items-center gap-2">
        <a class="inline-flex items-center rounded-lg border px-3 py-2 text-sm font-medium <?= ($filters['association_type'] ?? '') === '' ? 'bg-slate-900 text-white border-slate-900' : 'border-slate-200 text-slate-700' ?>" href="index.php?page=planos_preventiva">Todos</a>
        <a class="inline-flex items-center rounded-lg border px-3 py-2 text-sm font-medium <?= ($filters['association_type'] ?? '') === 'unidade' ? 'bg-slate-900 text-white border-slate-900' : 'border-slate-200 text-slate-700' ?>" href="index.php?page=planos_preventiva&tab=unidade">Unidades</a>
        <a class="inline-flex items-center rounded-lg border px-3 py-2 text-sm font-medium <?= ($filters['association_type'] ?? '') === 'veiculos' ? 'bg-slate-900 text-white border-slate-900' : 'border-slate-200 text-slate-700' ?>" href="index.php?page=planos_preventiva&tab=veiculos">Veículos</a>
        <a class="ml-auto text-sm text-blue-600 hover:underline" href="index.php?page=manutencao_servicos">Gerenciar serviços</a>
    </div>

    <form method="get" action="index.php">
        <input type="hidden" name="page" value="planos_preventiva">
        <input class="w-full max-w-sm rounded-lg border border-slate-200 px-3 py-2 text-sm" name="q" placeholder="Busque pelo nome do plano" value="<?= sanitize($filters['q'] ?? '') ?>">
    </form>

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium">Nome do plano</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">Serviços</th>
                    <th class="px-4 py-3 font-medium">Veículos</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($plans as $p): ?>
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-800"><?= sanitize($p['nome']) ?></td>
                        <td class="px-4 py-3"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $statusColors[$p['status']] ?? '' ?>"><?= sanitize($statusLabels[$p['status']] ?? $p['status']) ?></span></td>
                        <td class="px-4 py-3 text-slate-600"><?= sanitize($p['servicos_count']) ?></td>
                        <td class="px-4 py-3 text-slate-600"><?= sanitize($p['veiculos_count']) ?></td>
                        <td class="px-4 py-3 text-right">
                            <a class="text-blue-600 hover:underline text-sm" href="index.php?mod=manutencao&ctrl=PlanosPreventiva&action=show&id=<?= sanitize($p['id']) ?>">Abrir</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$plans): ?>
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Nenhum plano encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
```

- [ ] **Step 2: Write the create view**

```php
<?php
$filiais = $filiais ?? [];
$veiculos = $veiculos ?? [];
?>
<div class="max-w-2xl mx-auto px-4 py-6">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 space-y-5">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-900">Plano de manutenção</h2>
            <a class="text-slate-400 hover:text-slate-600" href="index.php?page=planos_preventiva">✕</a>
        </div>

        <form method="post" action="index.php?mod=manutencao&ctrl=PlanosPreventiva&action=store" class="space-y-5">
<?= csrf_field() ?>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nome do plano</label>
                <input class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="nome" placeholder="Ex: Onix 2019-2025" required>
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
                <label class="block text-sm font-medium text-slate-700 mb-1">Associação</label>
                <select class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" id="association-select">
                    <option value="">Selecione a associação</option>
                    <optgroup label="Unidade">
                        <?php foreach ($filiais as $f): ?>
                            <option value="unidade:<?= sanitize($f['id']) ?>">Toda a unidade: <?= sanitize($f['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <option value="veiculos">Veículos específicos</option>
                </select>
                <input type="hidden" name="association_type" id="association_type" value="veiculos">
                <input type="hidden" name="association_filial_id" id="association_filial_id" value="">
            </div>

            <div id="veiculos-picker">
                <label class="block text-sm font-medium text-slate-700 mb-1">Veículos</label>
                <select class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="veiculo_ids[]" multiple size="6">
                    <?php foreach ($veiculos as $v): ?>
                        <option value="<?= sanitize($v['id']) ?>"><?= sanitize($v['plate']) ?> <?= sanitize($v['model']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-1 text-xs text-slate-500">Segure CTRL para selecionar múltiplos.</p>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                <a class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" href="index.php?page=planos_preventiva">Cancelar</a>
                <button class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Criar</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const select = document.getElementById('association-select');
    const typeInput = document.getElementById('association_type');
    const filialInput = document.getElementById('association_filial_id');
    const veiculosPicker = document.getElementById('veiculos-picker');

    select.addEventListener('change', function() {
        if (this.value === 'veiculos') {
            typeInput.value = 'veiculos';
            filialInput.value = '';
            veiculosPicker.style.display = '';
        } else if (this.value.startsWith('unidade:')) {
            typeInput.value = 'unidade';
            filialInput.value = this.value.split(':')[1];
            veiculosPicker.style.display = 'none';
        } else {
            veiculosPicker.style.display = '';
        }
    });

    const medicaoToggle = document.getElementById('medicao-toggle');
    const medicaoInput = document.getElementById('medicao_tipo');
    medicaoToggle.querySelectorAll('button').forEach((btn) => {
        btn.addEventListener('click', function() {
            medicaoInput.value = this.dataset.medicao;
            medicaoToggle.querySelectorAll('button').forEach((b) => {
                b.classList.toggle('bg-slate-900', b === this);
                b.classList.toggle('text-white', b === this);
                b.classList.toggle('bg-white', b !== this);
                b.classList.toggle('text-slate-700', b !== this);
            });
        });
    });
})();
</script>
```

- [ ] **Step 3: Rewrite the detail view**

```php
<?php
$plan = $plan ?? [];
$servicosCatalogo = $servicosCatalogo ?? [];
$veiculosDisponiveis = $veiculosDisponiveis ?? [];
$statusLabels = ['rascunho' => 'Rascunho', 'ativo' => 'Ativo', 'inativo' => 'Inativo'];
$statusColors = [
    'rascunho' => 'bg-slate-100 text-slate-700',
    'ativo' => 'bg-emerald-100 text-emerald-800',
    'inativo' => 'bg-gray-200 text-gray-700',
];
$unidadeLabels = ['dias' => 'dias', 'meses' => 'meses', 'anos' => 'anos'];
$medicaoLabel = $plan['medicao_tipo'] === 'horimetro' ? 'horas' : 'km';
?>
<div class="max-w-5xl mx-auto px-4 py-6 space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a class="text-slate-400 hover:text-slate-600" href="index.php?page=planos_preventiva">← Voltar</a>
            <h2 class="text-2xl font-semibold text-slate-900"><?= sanitize($plan['nome']) ?></h2>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $statusColors[$plan['status']] ?? '' ?>"><?= sanitize($statusLabels[$plan['status']] ?? $plan['status']) ?></span>
        </div>
        <?php if (has_permission('preventiva.manage')): ?>
            <div class="flex items-center gap-2">
                <?php if ($plan['status'] === 'rascunho'): ?>
                    <form method="post" action="index.php?mod=manutencao&ctrl=PlanosPreventiva&action=publish">
<?= csrf_field() ?>
                        <input type="hidden" name="plan_id" value="<?= sanitize($plan['id']) ?>">
                        <button class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Publicar</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="index.php?mod=manutencao&ctrl=PlanosPreventiva&action=toggleStatus">
<?= csrf_field() ?>
                        <input type="hidden" name="plan_id" value="<?= sanitize($plan['id']) ?>">
                        <input type="hidden" name="status" value="<?= $plan['status'] === 'ativo' ? 'inativo' : 'ativo' ?>">
                        <button class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"><?= $plan['status'] === 'ativo' ? 'Inativar' : 'Reativar' ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div x-data="{tab:'servicos'}">
        <div class="flex items-center gap-4 border-b border-slate-200">
            <button onclick="document.getElementById('tab-servicos').style.display='';document.getElementById('tab-veiculos').style.display='none';" class="pb-2 border-b-2 border-slate-900 font-medium text-sm text-slate-900">Serviços (<?= count($plan['servicos']) ?>)</button>
            <button onclick="document.getElementById('tab-servicos').style.display='none';document.getElementById('tab-veiculos').style.display='';" class="pb-2 border-b-2 border-transparent font-medium text-sm text-slate-500">Veículos (<?= count($plan['veiculos']) ?>)</button>
        </div>

        <div id="tab-servicos" class="pt-4 space-y-3">
            <?php foreach ($plan['servicos'] as $ps): ?>
                <div class="bg-white border border-slate-200 rounded-xl p-4 flex items-center justify-between">
                    <div>
                        <div class="font-medium text-slate-800"><?= sanitize($ps['service_nome']) ?></div>
                        <div class="text-xs text-slate-500 mt-1">
                            <?= $ps['tipo'] === 'unico' ? 'Único' : 'Recorrente' ?>
                            <?php if ($ps['intervalo_tempo_valor']): ?>
                                • a cada <?= sanitize($ps['intervalo_tempo_valor']) ?> <?= sanitize($unidadeLabels[$ps['intervalo_tempo_unidade']] ?? '') ?>
                            <?php endif; ?>
                            <?php if ($ps['intervalo_medicao']): ?>
                                • a cada <?= sanitize($ps['intervalo_medicao']) ?> <?= $medicaoLabel ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (has_permission('preventiva.manage')): ?>
                        <form method="post" action="index.php?mod=manutencao&ctrl=PlanosPreventiva&action=removeService">
<?= csrf_field() ?>
                            <input type="hidden" name="plan_id" value="<?= sanitize($plan['id']) ?>">
                            <input type="hidden" name="plan_service_id" value="<?= sanitize($ps['id']) ?>">
                            <button class="text-xs font-semibold text-red-600 hover:underline">Remover</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$plan['servicos']): ?>
                <div class="text-center py-10">
                    <p class="text-slate-800 font-medium">Nenhum serviço adicionado</p>
                    <p class="text-sm text-slate-500 mt-1">Adicione o primeiro serviço para começar a montar seu plano.</p>
                </div>
            <?php endif; ?>
            <?php if (has_permission('preventiva.manage')): ?>
                <a class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700" href="index.php?mod=manutencao&ctrl=PlanosPreventiva&action=show&id=<?= sanitize($plan['id']) ?>#add-service-form">Adicionar serviço</a>
            <?php endif; ?>

            <?php if (has_permission('preventiva.manage')): ?>
                <div id="add-service-form" class="pt-6 mt-6 border-t border-slate-100">
                    <?php include __DIR__ . '/plano_service_form.php'; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="tab-veiculos" class="pt-4 space-y-2" style="display:none;">
            <?php foreach ($plan['veiculos'] as $v): ?>
                <div class="bg-white border border-slate-200 rounded-xl p-3 flex items-center justify-between">
                    <span class="text-sm text-slate-800"><?= sanitize($v['plate']) ?> <?= sanitize($v['model']) ?></span>
                    <?php if ($plan['association_type'] === 'veiculos' && has_permission('preventiva.manage')): ?>
                        <form method="post" action="index.php?mod=manutencao&ctrl=PlanosPreventiva&action=removeVeiculo">
<?= csrf_field() ?>
                            <input type="hidden" name="plan_id" value="<?= sanitize($plan['id']) ?>">
                            <input type="hidden" name="veiculo_id" value="<?= sanitize($v['id']) ?>">
                            <button class="text-xs font-semibold text-red-600 hover:underline">Remover</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$plan['veiculos']): ?>
                <div class="text-sm text-slate-500 text-center py-6">Nenhum veículo associado.</div>
            <?php endif; ?>

            <?php if ($plan['association_type'] === 'veiculos' && has_permission('preventiva.manage')): ?>
                <form method="post" action="index.php?mod=manutencao&ctrl=PlanosPreventiva&action=addVeiculo" class="flex items-center gap-2 pt-3">
<?= csrf_field() ?>
                    <input type="hidden" name="plan_id" value="<?= sanitize($plan['id']) ?>">
                    <select class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm" name="veiculo_id" required>
                        <option value="">Selecione um veículo</option>
                        <?php foreach ($veiculosDisponiveis as $v): ?>
                            <option value="<?= sanitize($v['id']) ?>"><?= sanitize($v['plate']) ?> <?= sanitize($v['model']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Adicionar veículo</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
```

- [ ] **Step 4: Write the add-service form partial**

```php
<?php
$servicosCatalogo = $servicosCatalogo ?? [];
$plan = $plan ?? [];
$medicaoLabel = ($plan['medicao_tipo'] ?? 'odometro') === 'horimetro' ? 'Horímetro' : 'Odômetro';
$medicaoUnidade = ($plan['medicao_tipo'] ?? 'odometro') === 'horimetro' ? 'horas' : 'km';
?>
<div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-4">
    <h3 class="text-base font-semibold text-slate-900">Adicionar serviço</h3>

    <form method="post" action="index.php?mod=manutencao&ctrl=PlanosPreventiva&action=addService" class="space-y-4">
<?= csrf_field() ?>
        <input type="hidden" name="plan_id" value="<?= sanitize($plan['id']) ?>">

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Serviço *</label>
            <select class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="service_ids[]" multiple size="5" required>
                <?php foreach ($servicosCatalogo as $s): ?>
                    <option value="<?= sanitize($s['id']) ?>"><?= sanitize($s['nome']) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="mt-1 text-xs text-slate-500">Segure CTRL para selecionar vários. <a class="text-blue-600 hover:underline" href="index.php?page=manutencao_servicos">Gerenciar serviços</a>.</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Tipo</label>
            <div class="inline-flex rounded-lg border border-slate-200 overflow-hidden text-sm" id="tipo-toggle">
                <button type="button" data-tipo="recorrente" class="px-4 py-2 font-semibold bg-slate-900 text-white">Recorrente</button>
                <button type="button" data-tipo="unico" class="px-4 py-2 font-semibold bg-white text-slate-700">Único</button>
            </div>
            <input type="hidden" name="tipo" id="tipo" value="recorrente">
        </div>

        <div class="rounded-lg bg-blue-50 text-blue-800 text-sm px-3 py-2">
            Preencha pelo menos uma recorrência: por tempo, <?= strtolower($medicaoLabel) ?> ou ambas.
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <div class="text-sm font-semibold text-slate-800 mb-2">Intervalo de tempo</div>
                <label class="block text-xs text-slate-500 mb-1">Recorrência a cada</label>
                <div class="flex gap-2">
                    <input class="w-24 rounded-lg border border-slate-200 px-3 py-2 text-sm" name="intervalo_tempo_valor" type="number" min="1" placeholder="Ex: 2">
                    <select class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm" name="intervalo_tempo_unidade">
                        <option value="dias">Dias</option>
                        <option value="meses" selected>Meses</option>
                        <option value="anos">Anos</option>
                    </select>
                </div>
                <label class="block text-xs text-slate-500 mt-2 mb-1">Emitir alerta quando faltar (opcional)</label>
                <input class="w-24 rounded-lg border border-slate-200 px-3 py-2 text-sm" name="alerta_tempo_dias" type="number" min="0" placeholder="30"> dias
            </div>
            <div>
                <div class="text-sm font-semibold text-slate-800 mb-2">Intervalo de <?= strtolower($medicaoLabel) ?></div>
                <label class="block text-xs text-slate-500 mb-1">Recorrência a cada</label>
                <input class="w-32 rounded-lg border border-slate-200 px-3 py-2 text-sm" name="intervalo_medicao" type="number" min="1" placeholder="Ex: 10000"> <?= $medicaoUnidade ?>
                <label class="block text-xs text-slate-500 mt-2 mb-1">Emitir alerta quando faltar (opcional)</label>
                <input class="w-32 rounded-lg border border-slate-200 px-3 py-2 text-sm" name="alerta_medicao" type="number" min="0" placeholder="1000"> <?= $medicaoUnidade ?>
            </div>
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <button class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Salvar</button>
        </div>
    </form>
</div>

<script>
(function() {
    const toggle = document.getElementById('tipo-toggle');
    const input = document.getElementById('tipo');
    if (!toggle) return;
    toggle.querySelectorAll('button').forEach((btn) => {
        btn.addEventListener('click', function() {
            input.value = this.dataset.tipo;
            toggle.querySelectorAll('button').forEach((b) => {
                b.classList.toggle('bg-slate-900', b === this);
                b.classList.toggle('text-white', b === this);
                b.classList.toggle('bg-white', b !== this);
                b.classList.toggle('text-slate-700', b !== this);
            });
        });
    });
})();
</script>
```

- [ ] **Step 5: Delete the old form view**

```bash
rm "C:/xampp/htdocs/frotaandrade/app/Modulos/Manutencao/Views/preventiva/plano_form.php"
```

- [ ] **Step 6: Syntax check all 4 remaining files**

```bash
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Manutencao\Views\preventiva\planos_index.php"
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Manutencao\Views\preventiva\plano_create.php"
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Manutencao\Views\preventiva\plano_show.php"
"C:\xampp\php\php.exe" -l "C:\xampp\htdocs\frotaandrade\app\Modulos\Manutencao\Views\preventiva\plano_service_form.php"
```

- [ ] **Step 7: Commit**

```bash
cd "C:/xampp/htdocs/frotaandrade"
git add -A app/Modulos/Manutencao/Views/preventiva/
git commit -m "Rewrite Plano de Manutencao views: list, create panel, detail tabs, add-service form"
```

---

## Task 6: Live verification and cleanup

**Files:** none (verification only)

- [ ] **Step 1:** With the session's login credential, walk the full flow via `curl` (same cookie-jar pattern used for the OS module): login → `index.php?page=manutencao_servicos` create 2 catalog services → `create` a plan with `association_type=unidade` → `create` a second plan with `association_type=veiculos` (2+ vehicles) → `addService` on each with one time-based and one measurement-based recorrência → `publish` one plan → `run` the preventiva job → `vencimentos_preventiva` and confirm rows/labels are correct → `toggleStatus` to inativo and re-run, confirming `processarPreventiva` skips it.
- [ ] **Step 2:** Delete every test plan/service/due row created during verification (same cleanup discipline as the OS module tasks — a throwaway PHP script, run once, then removed).
- [ ] **Step 3:** Re-run `php -l` across every file touched in this plan in one pass.
- [ ] **Step 4:** Report pass/fail against the spec's 9-point manual test plan to the user before calling this done.
