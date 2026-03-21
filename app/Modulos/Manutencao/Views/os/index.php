<?php
$orders = $orders ?? [];
$veiculos = $veiculos ?? [];
$filters = $filters ?? [];

$statusLabels = [
    'rascunho' => 'Rascunho',
    'aprovada' => 'Aprovada',
    'programada' => 'Programada',
    'em_execucao' => 'Em execução',
    'aguardando_pecas' => 'Aguardando peças',
    'concluida' => 'Concluída',
    'encerrada' => 'Encerrada',
    'cancelada' => 'Cancelada',
];
$statusColors = [
    'rascunho' => 'bg-slate-100 text-slate-700',
    'aprovada' => 'bg-blue-100 text-blue-800',
    'programada' => 'bg-indigo-100 text-indigo-800',
    'em_execucao' => 'bg-amber-100 text-amber-800',
    'aguardando_pecas' => 'bg-rose-100 text-rose-800',
    'concluida' => 'bg-emerald-100 text-emerald-800',
    'encerrada' => 'bg-slate-200 text-slate-800',
    'cancelada' => 'bg-gray-200 text-gray-700',
];
?>

<style>
.os-filterbar { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-top:8px; }
.os-search { flex:1 1 360px; min-width:260px; }
.os-pill { padding:6px 12px; border-radius:8px; border:1px solid #e2e8f0; background:#f8fafc; color:#334155; font-size:0.92rem; text-decoration:none; }
.os-pill.active { background:#0f172a; color:#fff; border-color:#0f172a; }
.os-pill-group { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
.os-chip { display:inline-flex; align-items:center; padding:0.2rem 0.65rem; border-radius:999px; font-size:0.85rem; }
.os-table thead th { padding:12px; }
.os-table tbody td { padding:14px 12px; vertical-align:middle; }
.os-table tbody tr + tr { border-top:1px solid #eef2f7; }
</style>

<div class="container py-4">
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h5 class="mb-0">Ordens de Serviço</h5>
                <?php if (has_permission('os.manage')): ?>
                    <a class="btn btn-primary btn-sm" href="index.php?mod=manutencao&ctrl=OrdensServico&action=create">Nova OS</a>
                <?php endif; ?>
            </div>
            <form class="os-filterbar" method="get" action="index.php">
                <input type="hidden" name="page" value="os">
                <div class="os-search">
                    <input class="form-control form-control-sm" name="q" placeholder="Buscar código ou placa" value="<?= sanitize($filters['q'] ?? '') ?>">
                </div>
                <div class="os-pill-group">
                    <?php foreach (['' => 'Todos'] + $statusLabels as $key => $label): ?>
                        <button type="submit" name="status" value="<?= sanitize($key) ?>" class="os-pill <?= ($filters['status'] ?? '') === $key ? 'active' : '' ?>"><?= sanitize($label) ?></button>
                    <?php endforeach; ?>
                </div>
                <select class="form-select form-select-sm w-auto" name="veiculo_id">
                    <option value="">Veículo</option>
                    <?php foreach ($veiculos as $v): ?>
                        <option value="<?= sanitize($v['id']) ?>" <?= ($filters['veiculo_id'] ?? '') == $v['id'] ? 'selected' : '' ?>><?= sanitize($v['plate']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-primary" type="submit">Aplicar</button>
                <a class="btn btn-sm btn-light" href="index.php?page=os">Limpar</a>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0 os-table">
                <thead class="table-light">
                    <tr>
                        <th>Código</th>
                        <th>Veículo</th>
                        <th>Status</th>
                        <th>Aberta em</th>
                        <th>SS</th>
                        <th class="text-nowrap">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $os): ?>
                        <tr>
                            <td class="fw-semibold"><?= sanitize($os['codigo']) ?></td>
                            <td><?= sanitize($os['vehicle_plate'] ?? '') ?></td>
                            <td><span class="os-chip <?= $statusColors[$os['status'] ?? 'rascunho'] ?? '' ?>"><?= sanitize($statusLabels[$os['status']] ?? $os['status']) ?></span></td>
                            <td><?= sanitize($os['aberta_em'] ?? '') ?></td>
                            <td><?= sanitize($os['total_ss'] ?? 0) ?></td>
                            <td><a class="btn btn-sm btn-outline-primary" href="index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=<?= sanitize($os['id']) ?>">Ver</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$orders): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma OS encontrada.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
