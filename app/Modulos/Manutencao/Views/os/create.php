<?php
$veiculos = $veiculos ?? [];
$ssList = $ssList ?? [];
$responsaveis = $responsaveis ?? [];
$statusOptions = [
    'solicitacao' => 'Solicitação',
    'aguardando_agendamento' => 'Aguardando/Agendado',
    'em_execucao' => 'Em execução',
    'analise_aprovacao' => 'Análise e Aprovação',
];
?>
<div class="max-w-5xl mx-auto px-4 py-6 space-y-6 os-redesign">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-semibold text-slate-900">Nova OS</h2>
        <a class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" href="index.php?page=os">Voltar</a>
    </div>

    <form method="post" action="index.php?mod=manutencao&ctrl=OrdensServico&action=store" enctype="multipart/form-data" class="space-y-6" id="os-create-form">
<?= csrf_field() ?>

        <section class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-4">
            <h3 class="text-base font-semibold text-slate-900">Detalhes</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Status *</label>
                    <select class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="status" required>
                        <?php foreach ($statusOptions as $key => $label): ?>
                            <option value="<?= sanitize($key) ?>"><?= sanitize($label) ?></option>
                        <?php endforeach; ?>
                    </select>
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

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Medição</label>
                    <div class="inline-flex rounded-lg border border-slate-200 overflow-hidden text-sm" id="medicao-toggle">
                        <button type="button" data-medicao="odometro" class="px-4 py-2 font-semibold bg-slate-900 text-white">Odômetro</button>
                        <button type="button" data-medicao="horimetro" class="px-4 py-2 font-semibold bg-white text-slate-700">Horímetro</button>
                    </div>
                    <input type="hidden" name="medicao_tipo" id="medicao_tipo" value="odometro">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1" id="odometro-label">Odômetro *</label>
                    <input class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="odometro_abertura" type="number" step="0.01" placeholder="0,00">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Disponibilidade</label>
                    <select class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="disponibilidade">
                        <option value="">Selecione a disponibilidade</option>
                        <option value="disponivel">Disponível</option>
                        <option value="indisponivel">Indisponível</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Prioridade *</label>
                    <select class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="prioridade" required>
                        <option value="baixa">Baixa</option>
                        <option value="media" selected>Média</option>
                        <option value="alta">Alta</option>
                        <option value="urgente">Urgente</option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Motivo da abertura da O.S *</label>
                    <input class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="motivo_abertura" placeholder="Ex: Troca de óleo, barulho no freio, revisão de 10.000km" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nº Ordem de Serviço</label>
                    <input class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="codigo" placeholder="Ex: OS-001 (deixe em branco para gerar automaticamente)">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Data de abertura *</label>
                    <input class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="aberta_em" type="datetime-local" value="<?= date('Y-m-d\TH:i') ?>" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tipo de fornecedor *</label>
                    <div class="inline-flex rounded-lg border border-slate-200 overflow-hidden text-sm" id="fornecedor-toggle">
                        <button type="button" data-tipo-fornecedor="externo" class="px-4 py-2 font-semibold bg-slate-900 text-white">Externo</button>
                        <button type="button" data-tipo-fornecedor="interno" class="px-4 py-2 font-semibold bg-white text-slate-700">Interno</button>
                    </div>
                    <input type="hidden" name="tipo_fornecedor" id="tipo_fornecedor" value="externo">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1" id="fornecedor-label">Fornecedor *</label>
                    <input class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="fornecedor" placeholder="Nome do fornecedor">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Responsável *</label>
                    <select class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="responsavel_id" required>
                        <option value="">Selecione o responsável pela O.S.</option>
                        <?php foreach ($responsaveis as $r): ?>
                            <option value="<?= sanitize($r['id']) ?>"><?= sanitize($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Observações</label>
                    <textarea class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" name="observacoes" rows="3"></textarea>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Vincular SS (opcional)</label>
                    <select class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" multiple name="ss_ids[]">
                        <?php foreach ($ssList as $ss): ?>
                            <option value="<?= sanitize($ss['id']) ?>">#<?= sanitize($ss['id']) ?> - <?= sanitize($ss['titulo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Segure CTRL para selecionar múltiplas.</p>
                </div>
            </div>
        </section>

        <section class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900 mb-3">Pendências em aberto</h3>
            <div id="pendencias-list" class="space-y-2"></div>
            <button type="button" id="add-pendencia" class="mt-2 inline-flex items-center rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">+ Adicionar pendência</button>
        </section>

        <section class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900 mb-3">Serviços</h3>
            <div id="servicos-list" class="space-y-2"></div>
            <button type="button" id="add-servico" class="mt-2 inline-flex items-center rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">+ Adicionar serviço</button>
            <div class="mt-4 flex justify-end items-center gap-3 border-t border-slate-100 pt-3">
                <span class="text-sm text-slate-500">Total</span>
                <span class="text-lg font-semibold text-slate-900" id="servicos-total">R$ 0,00</span>
            </div>
        </section>

        <section class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900 mb-1">Anexos</h3>
            <p class="text-sm text-slate-500 mb-3">Adicione uma ou mais arquivos relacionados à manutenção.</p>
            <label class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 py-8 cursor-pointer hover:bg-slate-50">
                <span class="text-sm text-slate-600">Arraste e solte ou <span class="text-blue-600 underline">clique para selecionar</span></span>
                <span class="text-xs text-slate-400">Limite: 10 arquivos • Formatos: PDF, PNG, JPEG • Máximo: 20MB</span>
                <input type="file" name="anexos[]" multiple accept=".pdf,.png,.jpg,.jpeg" class="hidden">
            </label>
        </section>

        <div class="flex justify-end gap-2">
            <a class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" href="index.php?page=os">Cancelar</a>
            <button class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Criar Ordem de Serviço</button>
        </div>
    </form>
</div>

<template id="pendencia-row-template">
    <div class="flex items-center gap-2 pendencia-row">
        <input class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm" name="pendencia_titulo[]" placeholder="Descreva a pendência">
        <button type="button" class="remove-row text-slate-400 hover:text-red-600 text-sm">Remover</button>
    </div>
</template>

<template id="servico-row-template">
    <div class="flex items-center gap-2 servico-row">
        <input class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm" name="servico_titulo[]" placeholder="Descrição do serviço">
        <input class="w-32 rounded-lg border border-slate-200 px-3 py-2 text-sm servico-valor" name="servico_valor[]" type="number" step="0.01" min="0" placeholder="0,00">
        <button type="button" class="remove-row text-slate-400 hover:text-red-600 text-sm">Remover</button>
    </div>
</template>

<script>
(function() {
    function addRow(listId, templateId) {
        const list = document.getElementById(listId);
        const tpl = document.getElementById(templateId);
        const node = tpl.content.cloneNode(true);
        node.querySelector('.remove-row').addEventListener('click', function(e) {
            e.target.closest('div').remove();
            recalcTotal();
        });
        list.appendChild(node);
    }

    document.getElementById('add-pendencia').addEventListener('click', () => addRow('pendencias-list', 'pendencia-row-template'));
    document.getElementById('add-servico').addEventListener('click', () => addRow('servicos-list', 'servico-row-template'));

    function recalcTotal() {
        let total = 0;
        document.querySelectorAll('.servico-valor').forEach((input) => {
            total += parseFloat(input.value || '0') || 0;
        });
        document.getElementById('servicos-total').textContent =
            'R$ ' + total.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    document.getElementById('servicos-list').addEventListener('input', function(e) {
        if (e.target.classList.contains('servico-valor')) {
            recalcTotal();
        }
    });

    function wireToggle(containerId, hiddenId, labelId, labels) {
        const container = document.getElementById(containerId);
        const hidden = document.getElementById(hiddenId);
        container.querySelectorAll('button').forEach((btn) => {
            btn.addEventListener('click', function() {
                const value = this.dataset.medicao || this.dataset.tipoFornecedor;
                hidden.value = value;
                container.querySelectorAll('button').forEach((b) => {
                    b.classList.toggle('bg-slate-900', b === this);
                    b.classList.toggle('text-white', b === this);
                    b.classList.toggle('bg-white', b !== this);
                    b.classList.toggle('text-slate-700', b !== this);
                });
                if (labelId && labels) {
                    document.getElementById(labelId).textContent = labels[value];
                }
            });
        });
    }
    wireToggle('medicao-toggle', 'medicao_tipo', 'odometro-label', { odometro: 'Odômetro *', horimetro: 'Horímetro *' });
    wireToggle('fornecedor-toggle', 'tipo_fornecedor', 'fornecedor-label', { externo: 'Fornecedor *', interno: 'Fornecedor (interno)' });
})();
</script>
