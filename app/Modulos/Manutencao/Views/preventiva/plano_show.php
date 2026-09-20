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

    <div>
        <div class="flex items-center gap-4 border-b border-slate-200">
            <button type="button" data-tab-btn="servicos" class="pb-2 border-b-2 border-slate-900 font-medium text-sm text-slate-900">Serviços (<?= count($plan['servicos']) ?>)</button>
            <button type="button" data-tab-btn="veiculos" class="pb-2 border-b-2 border-transparent font-medium text-sm text-slate-500">Veículos (<?= count($plan['veiculos']) ?>)</button>
        </div>

        <div id="tab-servicos" data-tab-panel="servicos" class="pt-4 space-y-3">
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
                <div id="add-service-form" class="pt-6 mt-6 border-t border-slate-100">
                    <?php include __DIR__ . '/plano_service_form.php'; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="tab-veiculos" data-tab-panel="veiculos" class="pt-4 space-y-2" style="display:none;">
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

<script>
(function() {
    const buttons = document.querySelectorAll('[data-tab-btn]');
    buttons.forEach((btn) => {
        btn.addEventListener('click', function() {
            const tab = this.dataset.tabBtn;
            document.querySelectorAll('[data-tab-panel]').forEach((panel) => {
                panel.style.display = panel.dataset.tabPanel === tab ? '' : 'none';
            });
            buttons.forEach((b) => {
                b.classList.toggle('border-slate-900', b === this);
                b.classList.toggle('text-slate-900', b === this);
                b.classList.toggle('border-transparent', b !== this);
                b.classList.toggle('text-slate-500', b !== this);
            });
        });
    });
})();
</script>
