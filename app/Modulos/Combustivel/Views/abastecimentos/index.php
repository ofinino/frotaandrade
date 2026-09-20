<?php
$records = $records ?? [];
$veiculos = $veiculos ?? [];
$filters = $filters ?? [];
$anexosByRecord = $anexosByRecord ?? [];
$combustivelLabels = [
    'alcool' => 'Álcool', 'arla32' => 'Arla 32', 'diesel' => 'Diesel',
    'diesel_s10' => 'Diesel S10', 'gasolina' => 'Gasolina', 'gasolina_aditivada' => 'Gasolina aditivada',
];
?>
<div class="max-w-7xl mx-auto px-4 py-6 space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-semibold text-slate-900">Abastecimentos</h2>
        <?php if (has_permission('combustivel.manage')): ?>
            <a class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800" href="index.php?mod=combustivel&ctrl=Abastecimentos&action=create">Novo Abastecimento</a>
        <?php endif; ?>
    </div>

    <form method="get" action="index.php" class="flex flex-wrap items-center gap-2">
        <input type="hidden" name="page" value="abastecimentos">
        <select class="rounded-lg border border-slate-200 px-3 py-2 text-sm" name="veiculo_id">
            <option value="">Todos os veículos</option>
            <?php foreach ($veiculos as $v): ?>
                <option value="<?= sanitize($v['id']) ?>" <?= ($filters['veiculo_id'] ?? '') == $v['id'] ? 'selected' : '' ?>><?= sanitize($v['plate']) ?></option>
            <?php endforeach; ?>
        </select>
        <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" type="date" name="de" value="<?= sanitize($filters['de'] ?? '') ?>">
        <input class="rounded-lg border border-slate-200 px-3 py-2 text-sm" type="date" name="ate" value="<?= sanitize($filters['ate'] ?? '') ?>">
        <button class="inline-flex items-center rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrar</button>
        <a class="text-sm text-slate-500 hover:underline" href="index.php?page=abastecimentos">Limpar</a>
    </form>

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Data</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Veículo</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Quantidade</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Medição</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Fornecedor/Tanque</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Criado por</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Valor do litro</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Custo total</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Medida percorrida</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Autonomia média</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Anexo</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($records as $r): ?>
                    <tr>
                        <td class="px-3 py-3 whitespace-nowrap"><?= sanitize(date('d/m/y \à\s H:i', strtotime($r['data_hora']))) ?></td>
                        <td class="px-3 py-3 whitespace-nowrap"><?= sanitize($r['veiculo_plate'] ?? '-') ?></td>
                        <td class="px-3 py-3 whitespace-nowrap"><?= number_format((float)$r['quantidade'], 2, ',', '.') ?> L</td>
                        <td class="px-3 py-3 whitespace-nowrap"><?= number_format((float)$r['odometro'], 1, ',', '.') ?> km</td>
                        <td class="px-3 py-3 whitespace-nowrap"><?= sanitize($r['tipo'] === 'interno' ? ($r['tank_nome'] ?? '-') : ($r['fornecedor_nome'] ?? '-')) ?></td>
                        <td class="px-3 py-3 whitespace-nowrap"><?= sanitize($r['criado_por_nome'] ?? '-') ?></td>
                        <td class="px-3 py-3 whitespace-nowrap"><?= $r['valor_litro'] !== null ? number_format((float)$r['valor_litro'], 2, ',', '.') . ' R$/L' : '-,--' ?></td>
                        <td class="px-3 py-3 whitespace-nowrap"><?= $r['custo'] !== null ? 'R$ ' . number_format((float)$r['custo'], 2, ',', '.') : '-' ?></td>
                        <td class="px-3 py-3 whitespace-nowrap"><?= $r['medida_percorrida'] !== null ? number_format((float)$r['medida_percorrida'], 1, ',', '.') . ' km' : '-,-- km' ?></td>
                        <td class="px-3 py-3 whitespace-nowrap"><?= $r['autonomia_media'] !== null ? number_format((float)$r['autonomia_media'], 2, ',', '.') . ' km/L' : '-,-- km/L' ?></td>
                        <td class="px-3 py-3 whitespace-nowrap">
                            <?php foreach ($anexosByRecord[$r['id']] ?? [] as $i => $at): ?>
                                <a class="text-blue-600 hover:underline" href="<?= sanitize(asset_url($at['file_path'])) ?>" target="_blank">📎<?= $i + 1 ?></a>
                            <?php endforeach; ?>
                            <?php if (empty($anexosByRecord[$r['id']])): ?>
                                <span class="text-slate-300">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-3 whitespace-nowrap">
                            <?php if (has_permission('combustivel.manage')): ?>
                                <form method="post" action="index.php?mod=combustivel&ctrl=Abastecimentos&action=destroy" onsubmit="return confirm('Excluir este abastecimento?');">
<?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= sanitize($r['id']) ?>">
                                    <button class="text-xs text-red-500 hover:underline">Excluir</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$records): ?>
                    <tr><td colspan="12" class="px-4 py-8 text-center text-slate-500">Nenhum abastecimento encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if ($records): ?>
            <?php
                $totalCusto = array_sum(array_column($records, 'custo'));
                $custosValidos = array_values(array_filter($records, fn($r) => $r['custo'] !== null && (float)$r['quantidade'] > 0));
                $precoMedio = $custosValidos ? array_sum(array_map(fn($r) => (float)$r['custo'], $custosValidos)) / array_sum(array_map(fn($r) => (float)$r['quantidade'], $custosValidos)) : 0;
            ?>
            <div class="flex flex-wrap items-center gap-6 px-4 py-3 border-t border-slate-100 text-sm">
                <div><span class="text-slate-500">Total de abastecimentos</span> <span class="font-semibold text-slate-900"><?= count($records) ?></span></div>
                <div><span class="text-slate-500">Custo total</span> <span class="font-semibold text-slate-900">R$ <?= number_format((float)$totalCusto, 2, ',', '.') ?></span></div>
                <div><span class="text-slate-500">Preço médio por litro</span> <span class="font-semibold text-slate-900">R$ <?= number_format($precoMedio, 2, ',', '.') ?>/L</span></div>
            </div>
        <?php endif; ?>
    </div>
</div>
