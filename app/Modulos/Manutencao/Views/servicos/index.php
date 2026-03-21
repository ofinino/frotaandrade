<?php
$lista = $lista ?? [];
$osList = $osList ?? [];
$veiculos = $veiculos ?? [];
$filters = $filters ?? [];

$statusColors = [
    'aberta' => 'bg-emerald-100 text-emerald-800',
    'triagem' => 'bg-amber-100 text-amber-800',
    'convertida' => 'bg-blue-100 text-blue-800',
    'rejeitada' => 'bg-rose-100 text-rose-800',
    'encerrada' => 'bg-slate-200 text-slate-800',
];
$prioColors = [
    'baixa' => 'bg-slate-100 text-slate-700',
    'media' => 'bg-blue-100 text-blue-800',
    'alta' => 'bg-amber-100 text-amber-800',
    'critica' => 'bg-red-100 text-red-800',
];

$origemLabels = [
    'checklist_nonconformity' => 'Checklist (nao conformidade)',
    'preventive_due' => 'Preventiva (vencimento)',
    'manual' => 'Manual',
];
?>

<style>
.ss-filterbar { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-top:8px; }
.ss-search { flex:1 1 360px; min-width:260px; }
.ss-pill, .ss-pill-outline { padding:6px 12px; border-radius:8px; border:1px solid #e2e8f0; background:#f8fafc; color:#334155; font-size:0.92rem; text-decoration:none; }
.ss-pill.active { background:#0f172a; color:#fff; border-color:#0f172a; }
.ss-pill-outline { background:#fff; }
.ss-pill-outline.active { border-color:#0d6efd; color:#0d6efd; background:#e7f1ff; }
.ss-pill-group, .ss-pill-outline-group { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
.ss-chip { display:inline-flex; align-items:center; padding:0.2rem 0.65rem; border-radius:999px; font-size:0.85rem; }
.table-actions form { display:inline-block; margin-right:8px; }
.table-actions select { min-width:120px; }
.servicos-table thead th { padding:12px; }
.servicos-table tbody td { padding:14px 12px; vertical-align:middle; }
.servicos-table tbody tr + tr { border-top:1px solid #eef2f7; }
</style>

<div class="container py-4">
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h5 class="mb-0">Solicitacoes recentes</h5>
                <?php if (has_permission('ss.manage')): ?>
                    <a class="btn btn-primary btn-sm" href="index.php?mod=manutencao&ctrl=SolicitacoesServico&action=create">Nova SS</a>
                <?php endif; ?>
            </div>
            <form class="ss-filterbar" method="get" action="index.php">
                <input type="hidden" name="page" value="servicos">
                <div class="ss-search">
                    <input class="form-control form-control-sm" type="text" name="q" placeholder="Buscar por titulo, descricao, veiculo ou OS" value="<?= sanitize($filters['q'] ?? '') ?>">
                </div>
                <div class="ss-pill-group">
                    <?php foreach (['' => 'Todos','aberta'=>'Aberta','triagem'=>'Triagem','convertida'=>'Convertida','rejeitada'=>'Rejeitada','encerrada'=>'Encerrada'] as $key => $label): ?>
                        <button type="submit" name="status" value="<?= sanitize($key) ?>" class="ss-pill <?= ($filters['status'] ?? '') === $key ? 'active' : '' ?>"><?= sanitize($label) ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="ss-pill-outline-group">
                    <?php foreach (['' => 'Prio: Todas','baixa'=>'Baixa','media'=>'Media','alta'=>'Alta','critica'=>'Critica'] as $key => $label): ?>
                        <button type="submit" name="prioridade" value="<?= sanitize($key) ?>" class="ss-pill-outline <?= ($filters['prioridade'] ?? '') === $key ? 'active' : '' ?>"><?= sanitize($label) ?></button>
                    <?php endforeach; ?>
                </div>
                <select name="veiculo_id" class="form-select form-select-sm w-auto">
                    <option value="">Veiculo</option>
                    <?php foreach ($veiculos as $v): ?>
                        <option value="<?= sanitize($v['id']) ?>" <?= ($filters['veiculo_id'] ?? '') == $v['id'] ? 'selected' : '' ?>><?= sanitize($v['plate'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-primary" type="submit">Aplicar</button>
                <a class="btn btn-sm btn-light" href="index.php?page=servicos">Limpar</a>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0 servicos-table">
                <thead class="table-light">
                    <tr>
                        <th>Titulo</th>
                        <th>Veiculo</th>
                        <th>Prioridade</th>
                        <th>Status</th>
                        <th>Origem</th>
                        <th>OS</th>
                        <th class="text-nowrap">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista as $ss): ?>
                        <tr>
                            <td class="fw-semibold">
                                <a href="index.php?page=servicos&action=show&id=<?= sanitize($ss['id']) ?>" class="text-decoration-none"><?= sanitize($ss['titulo']) ?></a>
                                <div class="text-muted small">#<?= sanitize($ss['id']) ?> - Criada em <?= sanitize(substr($ss['created_at'],0,16)) ?></div>
                            </td>
                            <td><?= sanitize($ss['vehicle_plate'] ?? '') ?></td>
                            <td><span class="ss-chip <?= $prioColors[$ss['prioridade'] ?? 'media'] ?? '' ?>"><?= ucfirst(sanitize($ss['prioridade'])) ?></span></td>
                            <td><span class="ss-chip <?= $statusColors[$ss['status'] ?? 'aberta'] ?? '' ?>"><?= ucfirst(sanitize($ss['status'])) ?></span></td>
                            <td><?= sanitize($origemLabels[$ss['source_type'] ?? ''] ?? $ss['source_type']) ?></td>
                            <td><?= sanitize($ss['work_order_codes'] ?? '') ?></td>
                            <td class="text-nowrap table-actions">
                                <?php if (has_permission('ss.manage')): ?>
                                    <form method="post" onsubmit="return confirm('Converter em OS?');">
                                        <input type="hidden" name="convert_ss" value="<?= sanitize($ss['id']) ?>">
                                        <button class="btn btn-link btn-sm p-0">Converter</button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('Encerrar SS?');">
                                        <input type="hidden" name="close_ss" value="<?= sanitize($ss['id']) ?>">
                                        <button class="btn btn-link btn-sm text-success p-0">Encerrar</button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('Rejeitar SS?');">
                                        <input type="hidden" name="reject_ss" value="<?= sanitize($ss['id']) ?>">
                                        <input type="hidden" name="motivo" value="Rejeitada pela gestao">
                                        <button class="btn btn-link btn-sm text-danger p-0">Rejeitar</button>
                                    </form>
                                    <form method="post" class="d-inline-flex align-items-center gap-1">
                                        <input type="hidden" name="link_ss" value="1">
                                        <input type="hidden" name="ss_id" value="<?= sanitize($ss['id']) ?>">
                                        <select name="os_id" class="form-select form-select-sm w-auto">
                                            <?php foreach ($osList as $os): ?>
                                                <option value="<?= sanitize($os['id']) ?>"><?= sanitize($os['codigo']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-link btn-sm">Vincular OS</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$lista): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhuma SS encontrada.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
