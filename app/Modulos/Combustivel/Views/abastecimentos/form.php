<?php
$veiculos = $veiculos ?? [];
$fornecedores = $fornecedores ?? [];
$tanks = $tanks ?? [];
$tiposCombustivel = $tiposCombustivel ?? [];
?>
<div class="max-w-xl mx-auto px-4 py-6">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 space-y-5">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-900">Novo Abastecimento</h2>
            <a class="text-slate-400 hover:text-slate-600" href="index.php?page=abastecimentos">✕</a>
        </div>

        <form method="post" action="index.php?mod=combustivel&ctrl=Abastecimentos&action=store" enctype="multipart/form-data" class="space-y-4">
<?= csrf_field() ?>
            <div class="inline-flex rounded-lg border border-slate-200 overflow-hidden text-sm" id="tipo-toggle">
                <button type="button" data-tipo="comercial" class="px-4 py-2 font-semibold bg-slate-900 text-white">Comercial</button>
                <button type="button" data-tipo="interno" class="px-4 py-2 font-semibold bg-white text-slate-700">Interno</button>
            </div>
            <input type="hidden" name="tipo" id="tipo" value="comercial">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Data e hora *</label>
                    <input class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="data_hora" type="datetime-local" value="<?= date('Y-m-d\TH:i') ?>" required>
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

                <div id="campo-fornecedor" class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Fornecedor *</label>
                    <input class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="fornecedor_nome" list="fornecedores-list" placeholder="Digite ou selecione um fornecedor">
                    <datalist id="fornecedores-list">
                        <?php foreach ($fornecedores as $f): ?>
                            <option value="<?= sanitize($f['nome']) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div id="campo-tanque" class="md:col-span-2 hidden">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tanque de origem *</label>
                    <select class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="tank_id">
                        <option value="">Selecione um tanque</option>
                        <?php foreach ($tanks as $t): ?>
                            <option value="<?= sanitize($t['id']) ?>"><?= sanitize($t['nome']) ?> (<?= number_format((float)$t['estoque_atual'], 1, ',', '.') ?> L disponíveis)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Quantidade (L) *</label>
                    <input class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="quantidade" type="number" step="0.01" min="0.01" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Odômetro (km) *</label>
                    <input class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="odometro" type="number" step="0.1" min="0" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Combustível *</label>
                    <select class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="combustivel_tipo_id" required>
                        <option value="">Tipo de combustível</option>
                        <?php foreach ($tiposCombustivel as $ct): ?>
                            <option value="<?= sanitize($ct['id']) ?>"><?= sanitize($ct['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1 text-xs text-slate-500"><a class="text-blue-600 hover:underline" href="index.php?page=combustivel_tipos">Gerenciar tipos de combustível</a></p>
                </div>
                <div id="campo-custo">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Custo (R$) *</label>
                    <input class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="custo" type="number" step="0.01" min="0">
                </div>

                <div class="md:col-span-2 flex items-center gap-2">
                    <input type="checkbox" id="tanque_cheio" name="tanque_cheio" value="1" class="rounded border-slate-300">
                    <label for="tanque_cheio" class="text-sm text-slate-700">Tanque cheio</label>
                </div>

                <div class="md:col-span-2 flex items-start gap-3 rounded-lg border border-slate-200 px-3 py-2">
                    <button type="button" id="atualizar-odometro-toggle" data-on="0" class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full bg-slate-200 transition-colors mt-0.5">
                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow translate-x-1 transition-transform"></span>
                    </button>
                    <input type="hidden" name="atualizar_odometro" id="atualizar_odometro" value="0">
                    <div>
                        <div class="text-sm font-medium text-slate-800">Atualizar odômetro</div>
                        <div class="text-xs text-slate-500">Utilizar odômetro para atualizar a medição atual do veículo.</div>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Observações</label>
                    <textarea class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="observacoes" rows="2"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Foto da nota fiscal</label>
                    <input type="file" name="anexos[]" accept=".pdf,.png,.jpg,.jpeg" class="w-full text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Foto do odômetro</label>
                    <input type="file" name="anexos[]" accept=".png,.jpg,.jpeg" class="w-full text-sm">
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                <a class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" href="index.php?page=abastecimentos">Cancelar</a>
                <button class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Adicionar</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const toggle = document.getElementById('tipo-toggle');
    const input = document.getElementById('tipo');
    const campoFornecedor = document.getElementById('campo-fornecedor');
    const campoTanque = document.getElementById('campo-tanque');
    const campoCusto = document.getElementById('campo-custo');

    toggle.querySelectorAll('button').forEach((btn) => {
        btn.addEventListener('click', function() {
            const tipo = this.dataset.tipo;
            input.value = tipo;
            toggle.querySelectorAll('button').forEach((b) => {
                b.classList.toggle('bg-slate-900', b === this);
                b.classList.toggle('text-white', b === this);
                b.classList.toggle('bg-white', b !== this);
                b.classList.toggle('text-slate-700', b !== this);
            });
            campoFornecedor.classList.toggle('hidden', tipo !== 'comercial');
            campoCusto.classList.toggle('hidden', tipo !== 'comercial');
            campoTanque.classList.toggle('hidden', tipo !== 'interno');
        });
    });

    const odometroToggle = document.getElementById('atualizar-odometro-toggle');
    const odometroInput = document.getElementById('atualizar_odometro');
    odometroToggle.addEventListener('click', function() {
        const ligado = this.dataset.on === '1';
        this.dataset.on = ligado ? '0' : '1';
        odometroInput.value = ligado ? '0' : '1';
        this.classList.toggle('bg-slate-900', !ligado);
        this.classList.toggle('bg-slate-200', ligado);
        this.querySelector('span').classList.toggle('translate-x-6', !ligado);
        this.querySelector('span').classList.toggle('translate-x-1', ligado);
    });
})();
</script>
