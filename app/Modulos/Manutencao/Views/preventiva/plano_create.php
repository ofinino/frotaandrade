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
