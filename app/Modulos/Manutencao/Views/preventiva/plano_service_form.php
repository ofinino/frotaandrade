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
