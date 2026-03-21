<?php
$os = $os ?? [];
$attachments = $attachments ?? [];
$timeline = $timeline ?? [];
$ssList = $ssList ?? [];

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
$statusKey = $os['status'] ?? 'rascunho';
$statusText = $statusLabels[$statusKey] ?? $statusKey;
$statusClass = $statusColors[$statusKey] ?? 'bg-slate-100 text-slate-700';
?>

<div class="max-w-6xl mx-auto px-4 py-6 space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="text-2xl font-semibold text-slate-900">OS <?= sanitize($os['codigo'] ?? '') ?></h2>
                <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold <?= $statusClass ?>">
                    <?= sanitize($statusText) ?>
                </span>
            </div>
            <div class="mt-2 text-sm text-slate-600">
                <span class="font-medium text-slate-800">Veículo:</span>
                <?= sanitize($os['vehicle_plate'] ?? '') ?>
                <?php if (!empty($os['vehicle_model'])): ?>
                    • <?= sanitize($os['vehicle_model']) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" href="index.php?page=os">Voltar</a>
        </div>
    </div>

    <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">Resumo</h3>
            <p class="mt-3 text-sm text-slate-700 leading-relaxed">
                <?= nl2br(sanitize($os['observacoes'] ?? 'Sem observações.')) ?>
            </p>

            <?php if (has_permission('os.manage')): ?>
                <div class="mt-4 border-t border-slate-100 pt-4">
                    <form class="flex flex-wrap items-center gap-2" method="post" action="index.php?mod=manutencao&ctrl=OrdensServico&action=changeStatus">
                        <input type="hidden" name="os_id" value="<?= sanitize($os['id'] ?? '') ?>">
                        <label class="text-sm font-medium text-slate-700">Status</label>
                        <select class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" name="status">
                            <?php foreach (['aprovada','programada','em_execucao','aguardando_pecas','concluida','encerrada','cancelada'] as $s): ?>
                                <option value="<?= $s ?>" <?= ($os['status'] ?? '') === $s ? 'selected' : '' ?>><?= $statusLabels[$s] ?? ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Atualizar status</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">Detalhes</h3>
            <dl class="mt-4 space-y-3 text-sm">
                <div>
                    <dt class="text-slate-500">Aberta em</dt>
                    <dd class="font-medium text-slate-800"><?= sanitize($os['aberta_em'] ?? '') ?></dd>
                </div>
                <div>
                    <dt class="text-slate-500">Aberta por</dt>
                    <dd class="font-medium text-slate-800"><?= sanitize($os['aberta_por_nome'] ?? '') ?></dd>
                </div>
                <div>
                    <dt class="text-slate-500">Odômetro</dt>
                    <dd class="font-medium text-slate-800"><?= sanitize($os['odometro_abertura'] ?? '') ?></dd>
                </div>
                <div>
                    <dt class="text-slate-500">SS vinculadas</dt>
                    <dd class="font-medium text-slate-800"><?= sanitize($os['service_requests'] ? count($os['service_requests']) : 0) ?></dd>
                </div>
            </dl>
        </div>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-slate-900">Itens da OS</h3>
                <span class="text-xs text-slate-500"><?= sanitize($os['items'] ? count($os['items']) : 0) ?> itens</span>
            </div>
            <div class="mt-4 space-y-3">
                <?php foreach ($os['items'] ?? [] as $it): ?>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                        <div class="flex items-center justify-between">
                            <div class="font-semibold text-slate-800"><?= sanitize($it['titulo']) ?></div>
                            <span class="text-xs font-medium text-slate-500"><?= ucfirst(sanitize($it['status'])) ?></span>
                        </div>
                        <?php if (!empty($it['descricao'])): ?>
                            <div class="mt-2 text-sm text-slate-600"><?= nl2br(sanitize($it['descricao'])) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($os['items'])): ?>
                    <div class="text-sm text-slate-500">Sem itens cadastrados.</div>
                <?php endif; ?>
            </div>

            <?php if (has_permission('os.manage')): ?>
                <form class="mt-5 space-y-3" method="post" action="index.php?mod=manutencao&ctrl=OrdensServico&action=addItem">
                    <input type="hidden" name="os_id" value="<?= sanitize($os['id'] ?? '') ?>">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Novo item</label>
                        <input class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="titulo" placeholder="Descrição do item" required>
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
                        <button class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Adicionar item</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-slate-900">SS vinculadas</h3>
                <span class="text-xs text-slate-500"><?= sanitize($os['service_requests'] ? count($os['service_requests']) : 0) ?> vínculos</span>
            </div>
            <div class="mt-4 space-y-2 text-sm">
                <?php foreach ($os['service_requests'] ?? [] as $s): ?>
                    <div class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2">
                        <span class="font-medium text-slate-800">#<?= sanitize($s['id']) ?> - <?= sanitize($s['titulo']) ?></span>
                        <span class="text-xs text-slate-500"><?= ucfirst(sanitize($s['status'])) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($os['service_requests'])): ?>
                    <div class="text-sm text-slate-500">Sem SS vinculadas.</div>
                <?php endif; ?>
            </div>

            <?php if (has_permission('os.manage')): ?>
                <form class="mt-5" method="post" action="index.php?mod=manutencao&ctrl=OrdensServico&action=addItem">
                    <input type="hidden" name="os_id" value="<?= sanitize($os['id'] ?? '') ?>">
                    <label class="text-sm font-medium text-slate-700">Vincular SS</label>
                    <select class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="ss_ids[]" multiple>
                        <?php foreach ($ssList as $s): ?>
                            <option value="<?= sanitize($s['id']) ?>">#<?= sanitize($s['id']) ?> - <?= sanitize($s['titulo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-2 text-xs text-slate-500">Para salvar, use o botão de adicionar item (fluxo atual não altera SS automaticamente).</p>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">Mão de obra</h3>
            <div class="mt-4 space-y-2 text-sm">
                <?php foreach ($os['labor'] ?? [] as $lb): ?>
                    <div class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2">
                        <span class="font-medium text-slate-800"><?= sanitize($lb['descricao']) ?></span>
                        <span class="text-slate-500"><?= sanitize($lb['horas']) ?>h • <?= sanitize($lb['total']) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($os['labor'])): ?>
                    <div class="text-sm text-slate-500">Sem registros de mão de obra.</div>
                <?php endif; ?>
            </div>

            <?php if (has_permission('os.manage')): ?>
                <form class="mt-5 grid grid-cols-1 md:grid-cols-4 gap-2" method="post" action="index.php?mod=manutencao&ctrl=OrdensServico&action=addLabor">
                    <input type="hidden" name="os_id" value="<?= sanitize($os['id'] ?? '') ?>">
                    <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm md:col-span-2" name="descricao" placeholder="Descrição" required>
                    <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="horas" type="number" step="0.1" placeholder="Horas">
                    <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="valor_hora" type="number" step="0.01" placeholder="Valor/h">
                    <div class="md:col-span-4">
                        <button class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Adicionar</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">Peças</h3>
            <div class="mt-4 space-y-2 text-sm">
                <?php foreach ($os['parts'] ?? [] as $p): ?>
                    <div class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2">
                        <span class="font-medium text-slate-800"><?= sanitize($p['descricao']) ?></span>
                        <span class="text-slate-500"><?= sanitize($p['quantidade']) ?> <?= sanitize($p['unidade']) ?> • <?= sanitize($p['total']) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($os['parts'])): ?>
                    <div class="text-sm text-slate-500">Sem peças lançadas.</div>
                <?php endif; ?>
            </div>

            <?php if (has_permission('os.manage')): ?>
                <form class="mt-5 grid grid-cols-1 md:grid-cols-6 gap-2" method="post" action="index.php?mod=manutencao&ctrl=OrdensServico&action=addPart">
                    <input type="hidden" name="os_id" value="<?= sanitize($os['id'] ?? '') ?>">
                    <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm md:col-span-2" name="descricao" placeholder="Descrição" required>
                    <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="part_number" placeholder="Código">
                    <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="quantidade" type="number" step="0.01" placeholder="Qtd">
                    <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="custo_unit" type="number" step="0.01" placeholder="Custo">
                    <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="unidade" placeholder="Unidade" value="un">
                    <div class="md:col-span-6">
                        <button class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Adicionar</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <section class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
        <h3 class="text-base font-semibold text-slate-900">Anexos</h3>
        <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-3">
            <?php foreach ($attachments as $at): ?>
                <a class="group block overflow-hidden rounded-xl border border-slate-100" href="<?= asset_url($at['file_path']) ?>" target="_blank">
                    <img class="h-32 w-full object-cover transition-transform duration-200 group-hover:scale-105" src="<?= asset_url($at['file_path']) ?>" alt="<?= sanitize($at['original_name']) ?>">
                </a>
            <?php endforeach; ?>
            <?php if (!$attachments): ?>
                <div class="col-span-full text-sm text-slate-500">Sem anexos.</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
        <h3 class="text-base font-semibold text-slate-900">Timeline</h3>
        <div class="mt-4 space-y-3">
            <?php foreach ($timeline as $log): ?>
                <div class="flex items-start gap-3">
                    <div class="mt-1 h-2.5 w-2.5 rounded-full bg-slate-400"></div>
                    <div>
                        <div class="text-sm font-semibold text-slate-800"><?= sanitize($log['action']) ?></div>
                        <div class="text-xs text-slate-500"><?= sanitize($log['created_at']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$timeline): ?>
                <div class="text-sm text-slate-500">Nenhum evento registrado.</div>
            <?php endif; ?>
        </div>
    </section>
</div>
