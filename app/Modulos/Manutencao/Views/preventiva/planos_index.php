<?php
$plans = $plans ?? [];
$statusColors = [
    1 => 'bg-emerald-100 text-emerald-800',
    0 => 'bg-slate-200 text-slate-800',
];
?>

<style>
.prev-table thead th { padding:12px; }
.prev-table tbody td { padding:14px 12px; vertical-align:middle; }
.prev-table tbody tr + tr { border-top:1px solid #eef2f7; }
.prev-chip { display:inline-flex; align-items:center; padding:0.2rem 0.65rem; border-radius:999px; font-size:0.85rem; }
</style>

<div class="container py-4">
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <h5 class="mb-0">Planos de Preventiva</h5>
                <?php if (has_permission('preventiva.manage')): ?>
                    <a class="btn btn-primary btn-sm" href="index.php?mod=manutencao&ctrl=PlanosPreventiva&action=form">Novo plano</a>
                <?php endif; ?>
            </div>
            <p class="text-muted mb-0 small">Cadastre planos por veiculo com tarefas de km/tempo.</p>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0 prev-table">
                <thead class="table-light">
                    <tr><th>Nome</th><th>Veiculo</th><th>Tipo</th><th>Status</th><th class="text-nowrap">Acoes</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($plans as $p): ?>
                        <tr>
                            <td class="fw-semibold"><?= sanitize($p['nome']) ?></td>
                            <td><?= sanitize($p['vehicle_plate'] ?? '') ?></td>
                            <td><?= sanitize($p['tipo']) ?></td>
                            <td><span class="prev-chip <?= $statusColors[$p['ativo'] ? 1 : 0] ?? '' ?>"><?= $p['ativo'] ? 'Ativo' : 'Inativo' ?></span></td>
                            <td>
                                <a class="btn btn-sm btn-outline-primary" href="index.php?mod=manutencao&ctrl=PlanosPreventiva&action=show&id=<?= sanitize($p['id']) ?>">Ver</a>
                                <?php if (has_permission('preventiva.manage')): ?>
                                    <a class="btn btn-sm btn-secondary" href="index.php?mod=manutencao&ctrl=PlanosPreventiva&action=form&id=<?= sanitize($p['id']) ?>">Editar</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$plans): ?><tr><td colspan="5" class="text-center text-muted py-4">Nenhum plano.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
