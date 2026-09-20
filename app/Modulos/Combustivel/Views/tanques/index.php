<?php
$tanks = $tanks ?? [];
$tanksAtivos = $tanksAtivos ?? [];
$temHistorico = $temHistorico ?? [];
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
                    <?php foreach ($tanksAtivos as $t): ?>
                        <label class="flex items-center gap-2 text-sm rounded-lg border border-slate-200 px-3 py-2">
                            <input type="radio" name="tank_id" value="<?= sanitize($t['id']) ?>" required>
                            <?= sanitize($t['nome']) ?>
                        </label>
                    <?php endforeach; ?>
                    <?php if (!$tanksAtivos): ?>
                        <span class="text-sm text-slate-500">Nenhum tanque ativo.</span>
                    <?php endif; ?>
                </div>
            </div>
            <button class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Adicionar</button>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <?php foreach ($tanks as $t): ?>
            <?php $ativo = (bool)$t['ativo']; $comHistorico = $temHistorico[$t['id']] ?? false; ?>
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm <?= $ativo ? '' : 'opacity-60' ?>">
                <div class="flex items-start justify-between gap-2">
                    <div class="font-semibold text-slate-800"><?= sanitize($t['nome']) ?></div>
                    <?php if (!$ativo): ?>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-gray-200 text-gray-700">Inativo</span>
                    <?php endif; ?>
                </div>
                <div class="text-xs text-slate-500 mt-2">Estoque atual</div>
                <div class="text-lg font-semibold text-slate-900"><?= number_format((float)$t['estoque_atual'], 1, '.', '') ?>/<?= number_format((float)$t['capacidade_maxima'], 2, ',', '.') ?> L</div>
                <div class="text-xs text-slate-500 mt-2">Capacidade máxima</div>
                <div class="text-sm text-slate-700"><?= number_format((float)$t['capacidade_maxima'], 2, ',', '.') ?> L</div>

                <?php if (has_permission('combustivel.manage')): ?>
                    <div class="mt-3 pt-3 border-t border-slate-100 flex items-center gap-3">
                        <form method="post" action="index.php?mod=combustivel&ctrl=Tanques&action=toggleAtivo">
<?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= sanitize($t['id']) ?>">
                            <input type="hidden" name="ativo" value="<?= $ativo ? '0' : '1' ?>">
                            <button class="text-xs font-semibold text-blue-600 hover:underline"><?= $ativo ? 'Desativar' : 'Reativar' ?></button>
                        </form>
                        <?php if (!$comHistorico): ?>
                            <form method="post" action="index.php?mod=combustivel&ctrl=Tanques&action=destroy" onsubmit="return confirm('Excluir este tanque?');">
<?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= sanitize($t['id']) ?>">
                                <button class="text-xs text-red-500 hover:underline">Excluir</button>
                            </form>
                        <?php else: ?>
                            <span class="text-xs text-slate-400" title="Tanque com compras ou abastecimentos registrados">Com histórico</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
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
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($compras as $c): ?>
                    <tr id="compra-row-<?= sanitize($c['id']) ?>">
                        <td class="px-4 py-3"><?= sanitize(date('d/m/Y', strtotime($c['data']))) ?></td>
                        <td class="px-4 py-3"><?= sanitize($c['numero_nota'] ?? '-') ?></td>
                        <td class="px-4 py-3"><?= number_format((float)$c['quantidade'], 2, ',', '.') ?> L</td>
                        <td class="px-4 py-3">R$ <?= number_format((float)$c['valor_pago'], 2, ',', '.') ?></td>
                        <td class="px-4 py-3"><?= sanitize($c['tank_nome']) ?></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <?php if (has_permission('combustivel.manage')): ?>
                                <button type="button" onclick="document.getElementById('compra-edit-<?= sanitize($c['id']) ?>').classList.toggle('hidden')" class="text-xs font-semibold text-blue-600 hover:underline">Editar</button>
                                <form method="post" action="index.php?mod=combustivel&ctrl=Tanques&action=destroyPurchase" class="inline" onsubmit="return confirm('Excluir esta compra?');">
<?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= sanitize($c['id']) ?>">
                                    <button class="text-xs text-red-500 hover:underline ml-2">Excluir</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (has_permission('combustivel.manage')): ?>
                        <tr id="compra-edit-<?= sanitize($c['id']) ?>" class="hidden bg-slate-50">
                            <td colspan="6" class="px-4 py-3">
                                <form method="post" action="index.php?mod=combustivel&ctrl=Tanques&action=updatePurchase" class="grid grid-cols-1 md:grid-cols-5 gap-2 items-center">
<?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= sanitize($c['id']) ?>">
                                    <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="numero_nota" value="<?= sanitize($c['numero_nota'] ?? '') ?>" placeholder="Número da nota">
                                    <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="data" type="date" value="<?= sanitize($c['data']) ?>" required>
                                    <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="valor_pago" type="number" step="0.01" value="<?= sanitize($c['valor_pago']) ?>" placeholder="Valor pago (R$)">
                                    <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="quantidade" type="number" step="0.01" value="<?= sanitize($c['quantidade']) ?>" placeholder="Quantidade (L)" required>
                                    <button class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Salvar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (!$compras): ?>
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Nenhuma compra registrada.</td></tr>
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
