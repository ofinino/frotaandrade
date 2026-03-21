<?php
$vencimentos = $vencimentos ?? [];
$status = $status ?? '';
$statusLabels = ['ok' => 'OK', 'due_soon' => 'Em breve', 'overdue' => 'Vencido'];
$statusColors = [
    'ok' => 'bg-emerald-100 text-emerald-800',
    'due_soon' => 'bg-amber-100 text-amber-800',
    'overdue' => 'bg-rose-100 text-rose-800',
];
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
                <h5 class="mb-0">Vencimentos de Preventiva</h5>
                <?php if (has_permission('preventiva.manage')): ?>
                    <a class="btn btn-primary btn-sm" href="index.php?mod=manutencao&ctrl=PlanosPreventiva&action=run">Rodar preventiva</a>
                <?php endif; ?>
            </div>
            <form class="venc-filterbar" method="get" action="index.php">
                <input type="hidden" name="page" value="vencimentos_preventiva">
                <div class="venc-pill-group">
                    <?php foreach (['' => 'Todos'] + $statusLabels as $key => $label): ?>
                        <button type="submit" name="status" value="<?= sanitize($key) ?>" class="venc-pill <?= $status === $key ? 'active' : '' ?>"><?= sanitize($label) ?></button>
                    <?php endforeach; ?>
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
                    <tr><th>Plano</th><th>Tarefa</th><th>Veiculo</th><th>Status</th><th>Due data</th><th>Due km</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($vencimentos as $v): ?>
                        <tr>
                            <td class="fw-semibold"><?= sanitize($v['plano_nome'] ?? '') ?></td>
                            <td><?= sanitize($v['tarefa_nome'] ?? '') ?></td>
                            <td><?= sanitize($v['vehicle_plate'] ?? '') ?></td>
                            <td><span class="venc-chip <?= $statusColors[$v['status'] ?? 'ok'] ?? '' ?>"><?= sanitize($statusLabels[$v['status'] ?? ''] ?? $v['status']) ?></span></td>
                            <td><?= sanitize($v['due_date'] ?? '') ?></td>
                            <td><?= sanitize($v['due_km'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$vencimentos): ?><tr><td colspan="6" class="text-center text-muted py-4">Nenhum vencimento.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
