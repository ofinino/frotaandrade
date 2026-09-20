<?php
$tanks = $tanks ?? [];
$compras = $compras ?? [];
$filters = $filters ?? [];
?>
<div class="max-w-6xl mx-auto px-4 py-6 space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-semibold text-slate-900">Meus Tanques</h2>
        <?php if (has_permission('combustivel.manage')): ?>
            <div class="flex gap-2">
                <button type="button" onclick="document.getElementById('new-tank-panel').classList.toggle('hidden')" class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Adicionar novo tanque</button>
                <button type="button" onclick="document.getElementById('purchase-panel').classList.toggle('hidden')" class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Compra de combustível</button>
            </div>
        <?php endif; ?>
    </div>

    <div id="new-tank-panel" class="hidden bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
        <h3 class="text-base font-semibold text-slate-900 mb-3">Novo tanque</h3>
        <form method="post" action="index.php?mod=combustivel&ctrl=Tanques&action=store" class="grid grid-cols-1 md:grid-cols-3 gap-3">
<?= csrf_field() ?>
            <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="nome" placeholder="Nome do tanque" required>
            <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="capacidade_maxima" type="number" step="0.01" placeholder="Capacidade máxima (L)" required>
            <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="estoque_inicial" type="number" step="0.01" placeholder="Estoque inicial (L)">
            <div class="md:col-span-3">
                <button class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Adicionar</button>
            </div>
        </form>
    </div>

    <div id="purchase-panel" class="hidden bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
        <h3 class="text-base font-semibold text-slate-900 mb-3">Compra de combustível</h3>
        <form method="post" action="index.php?mod=combustivel&ctrl=Tanques&action=addPurchase" class="space-y-3">
<?= csrf_field() ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="numero_nota" placeholder="Número da nota">
                <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="data" type="date" value="<?= date('Y-m-d') ?>" required>
                <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="valor_pago" type="number" step="0.01" placeholder="Valor pago (R$)">
                <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="quantidade" type="number" step="0.01" placeholder="Quantidade (L)" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Em que tanque deseja adicionar combustível?</label>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                    <?php foreach ($tanks as $t): ?>
                        <label class="flex items-center gap-2 text-sm rounded-lg border border-slate-200 px-3 py-2">
                            <input type="radio" name="tank_id" value="<?= sanitize($t['id']) ?>" required>
                            <?= sanitize($t['nome']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Adicionar</button>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <?php foreach ($tanks as $t): ?>
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
                <div class="flex items-start justify-between">
                    <div class="font-semibold text-slate-800"><?= sanitize($t['nome']) ?></div>
                    <?php if (has_permission('combustivel.manage')): ?>
                        <form method="post" action="index.php?mod=combustivel&ctrl=Tanques&action=destroy" onsubmit="return confirm('Excluir este tanque?');">
<?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= sanitize($t['id']) ?>">
                            <button class="text-xs text-red-500 hover:underline">Excluir</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="text-xs text-slate-500 mt-2">Estoque atual</div>
                <div class="text-lg font-semibold text-slate-900"><?= number_format((float)$t['estoque_atual'], 1, '.', '') ?>/<?= number_format((float)$t['capacidade_maxima'], 2, ',', '.') ?> L</div>
                <div class="text-xs text-slate-500 mt-2">Capacidade máxima</div>
                <div class="text-sm text-slate-700"><?= number_format((float)$t['capacidade_maxima'], 2, ',', '.') ?> L</div>
            </div>
        <?php endforeach; ?>
        <?php if (!$tanks): ?>
            <div class="col-span-full text-center text-slate-500 py-10">Nenhum tanque cadastrado.</div>
        <?php endif; ?>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm">
        <div class="p-4 border-b border-slate-100">
            <h3 class="text-base font-semibold text-slate-900">Histórico de compra</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium">Data</th>
                    <th class="px-4 py-3 font-medium">Nº da nota</th>
                    <th class="px-4 py-3 font-medium">Quantidade</th>
                    <th class="px-4 py-3 font-medium">Valor pago</th>
                    <th class="px-4 py-3 font-medium">Tanque</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($compras as $c): ?>
                    <tr>
                        <td class="px-4 py-3"><?= sanitize(date('d/m/Y', strtotime($c['data']))) ?></td>
                        <td class="px-4 py-3"><?= sanitize($c['numero_nota'] ?? '-') ?></td>
                        <td class="px-4 py-3"><?= number_format((float)$c['quantidade'], 2, ',', '.') ?> L</td>
                        <td class="px-4 py-3">R$ <?= number_format((float)$c['valor_pago'], 2, ',', '.') ?></td>
                        <td class="px-4 py-3"><?= sanitize($c['tank_nome']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$compras): ?>
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Nenhuma compra registrada.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if ($compras): ?>
            <?php
                $totalLitros = array_sum(array_column($compras, 'quantidade'));
                $totalValor = array_sum(array_column($compras, 'valor_pago'));
            ?>
            <div class="flex items-center gap-6 px-4 py-3 border-t border-slate-100 text-sm">
                <div><span class="text-slate-500">Total de litros comprados</span> <span class="font-semibold text-slate-900"><?= number_format($totalLitros, 2, ',', '.') ?> L</span></div>
                <div><span class="text-slate-500">Valor total</span> <span class="font-semibold text-slate-900">R$ <?= number_format($totalValor, 2, ',', '.') ?></span></div>
            </div>
        <?php endif; ?>
    </div>
</div>
