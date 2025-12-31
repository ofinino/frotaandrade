<?php
$runs = $runs ?? [];
$templates = $templates ?? [];
$vehicles = $vehicles ?? [];
$executantes = $executantes ?? [];
$groups = $groups ?? [];
$user = $user ?? current_user();
$editingRun = $editingRun ?? null;
$editingDueAt = $editingRun && !empty($editingRun['prazo_em']) ? date('Y-m-d\TH:i', strtotime($editingRun['prazo_em'])) : '';
$editingGroup = $editingRun['grupo_id'] ?? null;
$statusLabels = [
    'pendente' => 'Pendente',
    'em_andamento' => 'Em andamento',
    'pausado' => 'Pausado',
    'concluido' => 'Concluido',
];
$statusColors = [
    'pendente' => 'bg-amber-100 text-amber-800',
    'em_andamento' => 'bg-blue-100 text-blue-800',
    'pausado' => 'bg-slate-100 text-slate-800',
    'concluido' => 'bg-emerald-100 text-emerald-800',
];
?>
<style>
    .filter-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        align-items: center;
    }
    .status-segment {
        display: inline-flex;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
    }
    .status-segment button {
        padding: 0.35rem 0.65rem;
        font-size: 0.85rem;
        border: 0;
        background: transparent;
        color: #475569;
    }
    .status-segment button[data-active="true"] {
        background: #1f2937;
        color: #fff;
    }
    .filter-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.25rem 0.55rem;
        border-radius: 999px;
        border: 2px solid #e2e8f0;
        background: #f8fafc;
        font-size: 0.85rem;
    }
    .filter-chip a { color: #475569; text-decoration: none; }
    .filter-chip a:hover { text-decoration: underline; }

    .advanced-card {
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        width: min(480px, 92vw);
        background: #fff;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 20px 45px rgba(15,23,42,0.18);
        padding: 12px;
        z-index: 60;
        display: none;
    }
    .advanced-card.open { display: block; }

    .advanced-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.35);
        z-index: 50;
        display: none;
    }
    .advanced-backdrop.open { display: block; }

    @media (max-width: 768px) {
        .advanced-card {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            top: auto;
            width: 100%;
            border-radius: 16px 16px 0 0;
            transform: translateY(100%);
            transition: transform 180ms ease;
        }
    .advanced-card.open { display: block; transform: translateY(0); }
    }

    /* dark theme borders */
    html[data-page-theme="dark"] .status-segment,
    .main-shell.page-dark .status-segment { border-color: #475569; }
    html[data-page-theme="dark"] .filter-chip,
    .main-shell.page-dark .filter-chip { border-color: #475569; background: #0f172a; color: #e2e8f0; }
    html[data-page-theme="dark"] .advanced-card,
    .main-shell.page-dark .advanced-card { border-color: #475569; background: #0b1220; }
    html[data-page-theme="dark"] .bg-white.border-2,
    .main-shell.page-dark .bg-white.border-2 { border-color: #475569 !important; background-color: #0f172a !important; }
    .exec-card { border: 2px solid #e2e8f0; background: #f8fafc; }
    html[data-page-theme="dark"] .exec-card,
    .main-shell.page-dark .exec-card { border-color: #475569; background-color: #0f172a; }
</style>
<div class="space-y-4">
    <?php if ($user['role'] !== 'executante'): ?>
        <div class="bg-white border-2 border-slate-200 shadow rounded-lg p-4">
            <div class="flex items-center justify-between mb-2">
                <div>
                    <div class="font-semibold text-slate-900"><?= $editingRun ? 'Editar execução pendente' : 'Programar/atribuir execução' ?></div>
                    <?php if ($editingRun): ?>
                        <div class="text-sm text-slate-600">Atualize os dados antes do início. Execução #<?= sanitize($editingRun['id']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="hidden lg:block">
                    <button type="submit" form="form-atribuir" class="bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded text-sm">Criar e atribuir</button>
                </div>
            </div>
            <form id="form-atribuir" method="post" class="grid grid-cols-1 lg:grid-cols-[1.05fr_1.55fr_1fr_1fr_1fr_1fr] gap-3 items-end">
                <?php if ($editingRun): ?>
                    <input type="hidden" name="edit_id" value="<?= (int) $editingRun['id'] ?>" />
                <?php endif; ?>
                <div class="lg:col-span-1">
                    <label class="block text-sm text-slate-600 mb-1">Grupo</label>
                    <select name="grupo_id" id="grupo-select" class="w-full border border-slate-200 rounded px-3 py-2" required>
                        <option value="">Selecione</option>
                        <?php foreach ($groups as $g): ?>
                            <option value="<?= $g['id'] ?>" <?= $editingGroup && (int)$editingGroup === (int)$g['id'] ? 'selected' : '' ?>><?= sanitize($g['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="lg:col-span-2">
                    <label class="block text-sm text-slate-600 mb-1">Modelo</label>
                    <select name="template_id" id="template-select" class="w-full border border-slate-200 rounded px-3 py-2" required>
                        <option value="">Selecione</option>
                        <?php foreach ($templates as $tpl): ?>
                            <?php $labelTpl = $tpl['name'] . (!empty($tpl['inativo']) ? ' (inativo)' : ''); ?>
                            <option value="<?= $tpl['id'] ?>" data-grupo="<?= $tpl['grupo_id'] ?? '' ?>" <?= $editingRun && (int)$editingRun['checklist_id'] === (int)$tpl['id'] ? 'selected' : '' ?>><?= sanitize($labelTpl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="lg:col-span-1">
                    <label class="block text-sm text-slate-600 mb-1">Veiculo (opcional)</label>
                    <select name="vehicle_id" class="w-full border border-slate-200 rounded px-3 py-2">
                        <option value="">-</option>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <option value="<?= $vehicle['id'] ?>" <?= $editingRun && (int)$editingRun['veiculo_id'] === (int)$vehicle['id'] ? 'selected' : '' ?>><?= sanitize($vehicle['plate'] . ' - ' . $vehicle['model']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="lg:col-span-1">
                    <label class="block text-sm text-slate-600 mb-1">Atribuir a</label>
                    <select name="assigned_to" class="w-full border border-slate-200 rounded px-3 py-2">
                        <option value="">Selecione executante</option>
                        <?php foreach ($executantes as $exec): ?>
                            <option value="<?= $exec['id'] ?>" <?= $editingRun && (int)$editingRun['atribuido_para'] === (int)$exec['id'] ? 'selected' : '' ?>><?= sanitize($exec['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="lg:col-span-1">
                    <label class="block text-sm text-slate-600 mb-1">Prazo (data/hora)</label>
                    <input type="datetime-local" name="due_at" class="w-full border border-slate-200 rounded px-3 py-2" value="<?= $editingDueAt ? sanitize($editingDueAt) : '' ?>" />
                </div>
                <div class="lg:col-span-1 lg:self-start">
                    <label class="block text-sm text-slate-600 mb-1">Titulo</label>
                    <input class="w-full border border-slate-200 rounded px-3 py-2" name="title" placeholder="Ex: Saida turno" value="<?= $editingRun ? sanitize($editingRun['title'] ?? '') : '' ?>" />
                </div>
                <div class="lg:col-span-5 lg:self-start">
                    <label class="block text-sm text-slate-600 mb-1">Notas</label>
                    <textarea class="w-full border border-slate-200 rounded px-3 py-2 h-[42px] resize-none align-middle" name="notes" rows="1"><?= $editingRun ? sanitize($editingRun['notes'] ?? '') : '' ?></textarea>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 lg:col-span-6">
                    <?php if ($editingRun): ?>
                        <a class="w-full sm:w-auto text-center bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold px-4 py-2 rounded" href="index.php?page=checks">Cancelar edicao</a>
                    <?php endif; ?>
                    <button type="submit" class="lg:hidden w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold px-3 py-2 rounded text-sm"><?= $editingRun ? 'Atualizar' : 'Criar e atribuir' ?></button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="bg-white border-2 border-slate-200 shadow rounded-lg p-4">
        <div class="font-semibold text-slate-900 mb-3 flex items-center justify-between">
            <span>Execucoes recentes</span>
        </div>

        <?php
            $filters = $filters ?? [];
            $queryBase = $_GET ?? [];
            $queryBase['page'] = 'checks';
            $buildUrl = function(array $remove = [], array $add = []) use ($queryBase): string {
                $qs = $queryBase;
                foreach ($remove as $r) { unset($qs[$r]); }
                foreach ($add as $k => $v) {
                    if ($v === null || $v === '') { unset($qs[$k]); continue; }
                    $qs[$k] = $v;
                }
                return 'index.php?' . http_build_query($qs);
            };
            $activeChips = [];
            if (!empty($filters['q'])) {
                $activeChips[] = ['label' => 'Busca: "' . sanitize($filters['q']) . '"', 'remove' => 'q'];
            }
            if (!empty($filters['status'])) {
                $lbl = $filters['status'] === 'pendente' ? 'Pendente' : 'Concluido';
                $activeChips[] = ['label' => 'Status: ' . $lbl, 'remove' => 'status'];
            }
            if (!empty($filters['modelo'])) {
                $nomeModelo = '';
                foreach ($templates as $tpl) { if ((int)$tpl['id'] === (int)$filters['modelo']) { $nomeModelo = $tpl['name']; break; } }
                $activeChips[] = ['label' => 'Modelo: ' . sanitize($nomeModelo ?: $filters['modelo']), 'remove' => 'modelo'];
            }
            if (!empty($filters['veiculo'])) {
                $activeChips[] = ['label' => 'Veiculo: ' . sanitize($filters['veiculo']), 'remove' => 'veiculo'];
            }
            if (!empty($filters['designado'])) {
                $nomeExec = '';
                foreach ($executantes as $exec) { if ((int)$exec['id'] === (int)$filters['designado']) { $nomeExec = $exec['name']; break; } }
                $activeChips[] = ['label' => 'Designado: ' . sanitize($nomeExec ?: $filters['designado']), 'remove' => 'designado'];
            }
            if (!empty($filters['prazo_mode'])) {
                $mapPrazo = ['hoje' => 'Hoje', 'semana' => 'Esta semana', 'atrasados' => 'Atrasados', 'intervalo' => 'Intervalo'];
                $activeChips[] = ['label' => 'Prazo: ' . ($mapPrazo[$filters['prazo_mode']] ?? $filters['prazo_mode']), 'remove' => 'prazo_mode'];
            }
            if (!empty($filters['prazo_de']) || !empty($filters['prazo_ate'])) {
                $labelPrazo = ($filters['prazo_de'] ? 'De ' . sanitize($filters['prazo_de']) : '') . ($filters['prazo_ate'] ? ' Ate ' . sanitize($filters['prazo_ate']) : '');
                $activeChips[] = ['label' => 'Prazo: ' . trim($labelPrazo), 'remove' => ['prazo_de','prazo_ate','prazo_mode']];
            }
            $hasFilters = !empty($activeChips);
        ?>
        <form id="filters-form" method="get" action="index.php" class="mb-3 space-y-2">
            <input type="hidden" name="page" value="checks" />
            <input type="hidden" name="status" id="status-input" value="<?= sanitize($filters['status'] ?? '') ?>" />
            <input type="hidden" name="prazo_mode" id="prazo-mode-input" value="<?= sanitize($filters['prazo_mode'] ?? '') ?>" />
            <div class="filter-bar">
                <div class="flex-1 min-w-[220px]">
                    <input type="text" name="q" value="<?= sanitize($filters['q'] ?? '') ?>" placeholder="Buscar por modelo, veiculo ou designado" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" />
                </div>
                <div class="status-segment">
                    <?php
                        $statusAtual = $filters['status'] ?? '';
                        $statusBtns = [
                            '' => 'Todos',
                            'pendente' => 'Pendente',
                            'concluido' => 'Concluido',
                        ];
                        foreach ($statusBtns as $val => $label):
                    ?>
                        <button type="button" class="status-btn" data-value="<?= $val ?>" data-active="<?= $statusAtual === $val ? 'true' : 'false' ?>"><?= $label ?></button>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="open-advanced" class="inline-flex items-center gap-2 px-3 py-2 border border-slate-200 rounded-lg text-sm text-slate-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M7 12h10M10 19h4"/></svg>
                    <span class="hidden sm:inline">Filtros</span>
                </button>
                <button type="submit" class="hidden sm:inline-flex items-center px-3 py-2 rounded-lg bg-slate-800 text-white text-sm">Aplicar</button>
                <?php if ($hasFilters): ?>
                    <a href="<?= $buildUrl(['q','status','designado','modelo','veiculo','prazo_mode','prazo_de','prazo_ate']) ?>" class="text-sm text-slate-600 hover:underline">Limpar tudo</a>
                <?php endif; ?>
            </div>

            <?php if ($hasFilters): ?>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($activeChips as $chip): ?>
                        <?php $removeKeys = (array)$chip['remove']; ?>
                        <span class="filter-chip">
                            <span><?= $chip['label'] ?></span>
                            <a href="<?= $buildUrl($removeKeys) ?>" aria-label="Remover filtro">x</a>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="relative">
                <div id="advanced-card" class="advanced-card">
                    <div class="flex items-center justify-between mb-2">
                        <div class="font-semibold text-slate-700 text-sm">Filtros avancados</div>
                        <button type="button" id="close-advanced" class="text-slate-500 text-sm">Fechar</button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Designado</label>
                            <select name="designado" class="w-full border border-slate-200 rounded px-2.5 py-1.5">
                                <option value="">Todos</option>
                                <?php foreach ($executantes as $exec): ?>
                                    <option value="<?= $exec['id'] ?>" <?= !empty($filters['designado']) && (int)$filters['designado'] === (int)$exec['id'] ? 'selected' : '' ?>><?= sanitize($exec['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Modelo</label>
                            <select name="modelo" class="w-full border border-slate-200 rounded px-2.5 py-1.5">
                                <option value="">Todos</option>
                                <?php foreach ($templates as $tpl): ?>
                                    <option value="<?= $tpl['id'] ?>" <?= !empty($filters['modelo']) && (int)$filters['modelo'] === (int)$tpl['id'] ? 'selected' : '' ?>><?= sanitize($tpl['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Veiculo (ID)</label>
                            <input type="number" name="veiculo" class="w-full border border-slate-200 rounded px-2.5 py-1.5" placeholder="Ex: 7200" value="<?= sanitize($filters['veiculo'] ?? '') ?>" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Prazo rapido</label>
                            <div class="flex flex-wrap gap-1">
                                <?php
                                    $prazos = ['hoje' => 'Hoje', 'semana' => 'Esta semana', 'atrasados' => 'Atrasados', 'intervalo' => 'Intervalo'];
                                    $prazoAtual = $filters['prazo_mode'] ?? '';
                                    foreach ($prazos as $k => $lbl):
                                ?>
                                    <button type="button" class="px-2 py-1 rounded border text-xs <?= $prazoAtual === $k ? 'bg-slate-800 text-white border-slate-800' : 'border-slate-200 text-slate-700' ?>" data-prazo="<?= $k ?>"><?= $lbl ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Prazo de</label>
                            <input type="date" name="prazo_de" class="w-full border border-slate-200 rounded px-2.5 py-1.5" value="<?= sanitize($filters['prazo_de'] ?? '') ?>" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Prazo ate</label>
                            <input type="date" name="prazo_ate" class="w-full border border-slate-200 rounded px-2.5 py-1.5" value="<?= sanitize($filters['prazo_ate'] ?? '') ?>" />
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end gap-2">
                        <a class="text-sm text-slate-600 hover:underline" href="<?= $buildUrl(['q','status','designado','modelo','veiculo','prazo_mode','prazo_de','prazo_ate']) ?>">Limpar</a>
                        <button type="submit" class="px-3 py-2 rounded bg-slate-800 text-white text-sm">Aplicar filtros</button>
                    </div>
                </div>
            </div>
            <div id="advanced-backdrop" class="advanced-backdrop"></div>
        </form>

        <?php if (empty($runs)): ?>
            <p class="text-sm text-slate-600">Nenhuma execucao encontrada.</p>
        <?php else: ?>
            <?php $statusColorsLocal = $statusColors; ?>
            <div class="space-y-3 lg:hidden">
                <?php foreach ($runs as $run): ?>
                    <?php $statusKey = $run['status'] ?: 'pendente'; ?>
                    <div class="border-2 border-slate-200 rounded-lg p-3 bg-slate-50 shadow-sm exec-card">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="text-base font-semibold text-slate-900"><?= sanitize($run['title'] ?: 'Checklist #' . $run['id']) ?></div>
                                <div class="text-xs text-slate-500"><?= sanitize($run['template_name']) ?></div>
                            </div>
                            <span class="px-2 py-1 rounded-full text-[11px] font-semibold <?= $statusColorsLocal[$statusKey] ?? 'bg-slate-100 text-slate-700' ?>">
                                <?= sanitize($statusLabels[$statusKey] ?? $statusKey) ?>
                            </span>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2 text-xs text-slate-600">
                            <div><span class="font-semibold">Veiculo:</span> <?= sanitize($run['vehicle_plate'] ?: '-') ?></div>
                            <div><span class="font-semibold">Designado:</span> <?= sanitize($run['assigned_name'] ?: '-') ?></div>
                            <div><span class="font-semibold">Prazo:</span> <?= $run['prazo_em'] ? sanitize(date('d/m/Y H:i', strtotime($run['prazo_em']))) : '-' ?></div>
                            <div class="col-span-2 text-[11px] text-slate-500">Criado em <?= sanitize(date('d/m/Y H:i', strtotime($run['created_at']))) ?><?= $run['performer'] ? ' · Por ' . sanitize($run['performer']) : '' ?></div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <a class="flex-1 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold px-3 py-2 rounded text-center" href="index.php?page=run_check&id=<?= $run['id'] ?>">Abrir</a>
                            <a class="flex-1 bg-slate-200 hover:bg-slate-300 text-slate-800 text-sm font-semibold px-3 py-2 rounded text-center" href="index.php?page=report&id=<?= $run['id'] ?>" target="_blank">Relatorio</a>
                            <?php if ($user['role'] !== 'executante' && $statusKey === 'pendente' && empty($run['executado_por'])): ?>
                                <a class="flex-1 bg-blue-100 hover:bg-blue-200 text-blue-700 text-sm font-semibold px-3 py-2 rounded text-center" href="index.php?page=checks&edit_run=<?= $run['id'] ?>">Editar</a>
                                <a class="flex-1 bg-rose-100 hover:bg-rose-200 text-rose-700 text-sm font-semibold px-3 py-2 rounded text-center" href="index.php?page=checks&delete_run=<?= $run['id'] ?>" onclick="return confirm('Excluir execução pendente?');">Excluir</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="hidden lg:block overflow-x-auto text-sm">
                <table class="min-w-full">
                    <thead>
                        <tr class="text-left text-slate-500">
                            <th class="py-2">Titulo</th>
                            <th class="py-2">Modelo</th>
                            <th class="py-2">Veiculo</th>
                            <th class="py-2">Designado</th>
                            <th class="py-2">Status</th>
                            <th class="py-2">Prazo</th>
                            <th class="py-2 text-right">Acoes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php foreach ($runs as $run): ?>
                        <?php $statusKey = $run['status'] ?: 'pendente'; ?>
                        <tr>
                            <td class="py-2 font-semibold text-slate-800"><?= sanitize($run['title'] ?: 'Checklist #' . $run['id']) ?></td>
                            <td class="py-2"><?= sanitize($run['template_name']) ?></td>
                            <td class="py-2"><?= sanitize($run['vehicle_plate'] ?: '-') ?></td>
                            <td class="py-2">
                                <div class="text-sm"><?= sanitize($run['assigned_name'] ?: '-') ?></div>
                                <div class="text-[11px] text-slate-500">Criado em <?= sanitize(date('d/m/Y H:i', strtotime($run['created_at']))) ?></div>
                            </td>
                            <td class="py-2">
                                <span class="px-2 py-1 rounded text-xs <?= $statusColors[$statusKey] ?? 'bg-slate-100 text-slate-700' ?>"><?= sanitize($statusLabels[$statusKey] ?? $statusKey) ?></span>
                                <?php if (!empty($run['performer'])): ?>
                                    <div class="text-[11px] text-slate-500">Por <?= sanitize($run['performer']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 text-xs text-slate-600">
                                <?= $run['prazo_em'] ? sanitize(date('d/m/Y H:i', strtotime($run['prazo_em']))) : '-' ?>
                            </td>
                            <td class="py-2 text-right space-x-2">
                                <a class="text-amber-600" href="index.php?page=run_check&id=<?= $run['id'] ?>">Abrir</a>
                                <a class="text-slate-600" href="index.php?page=report&id=<?= $run['id'] ?>" target="_blank">Relatorio</a>
                                <?php if ($user['role'] !== 'executante' && $statusKey === 'pendente' && empty($run['executado_por'])): ?>
                                    <a class="text-blue-600" href="index.php?page=checks&edit_run=<?= $run['id'] ?>">Editar</a>
                                    <a class="text-rose-600" href="index.php?page=checks&delete_run=<?= $run['id'] ?>" onclick="return confirm('Excluir execução pendente?');">Excluir</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function(){
    const grupoSelect = document.getElementById('grupo-select');
    const templateSelect = document.getElementById('template-select');
    if (grupoSelect && templateSelect) {
        function filtraModelos() {
            const g = grupoSelect.value;
            for (const opt of templateSelect.options) {
                if (!opt.value) continue;
                const og = opt.getAttribute('data-grupo') || '';
                opt.hidden = g && g !== og;
                if (opt.hidden && opt.selected) {
                    templateSelect.value = '';
                }
            }
        }
        grupoSelect.addEventListener('change', filtraModelos);
        filtraModelos();
    }
})();

(function(){
    const form = document.getElementById('filters-form');
    const statusInput = document.getElementById('status-input');
    const statusBtns = document.querySelectorAll('.status-btn');
    statusBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            if (!statusInput || !form) return;
            statusInput.value = btn.getAttribute('data-value') || '';
            form.submit();
        });
    });

    const advCard = document.getElementById('advanced-card');
    const advBackdrop = document.getElementById('advanced-backdrop');
    const openAdv = document.getElementById('open-advanced');
    const closeAdv = document.getElementById('close-advanced');
    const prazoModeInput = document.getElementById('prazo-mode-input');

    const setAdvanced = (open) => {
        if (advCard) advCard.classList.toggle('open', open);
        if (advBackdrop) advBackdrop.classList.toggle('open', open);
    };
    openAdv?.addEventListener('click', () => setAdvanced(true));
    closeAdv?.addEventListener('click', () => setAdvanced(false));
    advBackdrop?.addEventListener('click', () => setAdvanced(false));
    document.addEventListener('keydown', (ev) => {
        if (ev.key === 'Escape') setAdvanced(false);
    });

    document.querySelectorAll('button[data-prazo]').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!form) return;
            const val = btn.getAttribute('data-prazo') || '';
            if (prazoModeInput) prazoModeInput.value = val;
            if (val !== 'intervalo') {
                const de = form.querySelector('input[name="prazo_de"]');
                const ate = form.querySelector('input[name="prazo_ate"]');
                if (de) de.value = '';
                if (ate) ate.value = '';
            }
            form.submit();
        });
    });
})();
</script>
