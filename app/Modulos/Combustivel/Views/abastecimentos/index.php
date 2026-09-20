<?php
$records = $records ?? [];
$veiculos = $veiculos ?? [];
$filters = $filters ?? [];
$anexosByRecord = $anexosByRecord ?? [];
$resumo = $resumo ?? ['total_abastecimentos' => 0, 'custo_total' => 0, 'preco_medio' => 0, 'quantidade_total' => 0, 'total_inconsistentes' => 0];
$pagina = $pagina ?? 1;
$porPagina = $porPagina ?? 20;
$totalPaginas = $totalPaginas ?? 1;
$totalRegistros = $totalRegistros ?? 0;

$chipPalette = [
    ['bg' => 'bg-blue-100', 'text' => 'text-blue-800'],
    ['bg' => 'bg-amber-100', 'text' => 'text-amber-800'],
    ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-800'],
    ['bg' => 'bg-purple-100', 'text' => 'text-purple-800'],
    ['bg' => 'bg-rose-100', 'text' => 'text-rose-800'],
    ['bg' => 'bg-slate-200', 'text' => 'text-slate-800'],
];
$chipFor = function (string $nome) use ($chipPalette) {
    $idx = crc32($nome) % count($chipPalette);
    return $chipPalette[$idx];
};

$combustivelQueryBase = function (array $filters, array $extra = []): string {
    $params = array_merge([
        'veiculo_id' => $filters['veiculo_id'] ?? '',
        'de' => $filters['de'] ?? '',
        'ate' => $filters['ate'] ?? '',
        'aba' => !empty($filters['apenas_inconsistentes']) ? 'inconsistentes' : 'todos',
    ], $extra);
    return 'index.php?page=abastecimentos&' . http_build_query($params);
};
?>
<div class="max-w-7xl mx-auto px-4 py-6 space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-semibold text-slate-900">Abastecimentos</h2>
        <?php if (has_permission('combustivel.manage')): ?>
            <a class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800" href="index.php?mod=combustivel&ctrl=Abastecimentos&action=create">Novo Abastecimento</a>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
            <div class="text-xs text-slate-500">Total de abastecimentos</div>
            <div class="text-2xl font-semibold text-slate-900 mt-1"><?= (int)$resumo['total_abastecimentos'] ?></div>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
            <div class="text-xs text-slate-500">Custo total</div>
            <div class="text-2xl font-semibold text-slate-900 mt-1">R$ <?= number_format((float)$resumo['custo_total'], 2, ',', '.') ?></div>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
            <div class="text-xs text-slate-500">Preço médio por litro</div>
            <div class="text-2xl font-semibold text-slate-900 mt-1">R$ <?= number_format((float)$resumo['preco_medio'], 2, ',', '.') ?>/L</div>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
            <div class="text-xs text-slate-500">Quantidade de litros</div>
            <div class="text-2xl font-semibold text-slate-900 mt-1"><?= number_format((float)$resumo['quantidade_total'], 2, ',', '.') ?> L</div>
        </div>
    </div>

    <form method="get" action="index.php" class="flex flex-wrap items-center gap-2">
        <input type="hidden" name="page" value="abastecimentos">
        <input type="hidden" name="aba" value="<?= !empty($filters['apenas_inconsistentes']) ? 'inconsistentes' : 'todos' ?>">
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

        <div class="ml-auto inline-flex rounded-lg border border-slate-200 overflow-hidden text-sm">
            <a href="<?= sanitize($combustivelQueryBase($filters, ['aba' => 'todos'])) ?>" class="px-4 py-2 font-semibold <?= empty($filters['apenas_inconsistentes']) ? 'bg-slate-900 text-white' : 'bg-white text-slate-700' ?>">Todos</a>
            <a href="<?= sanitize($combustivelQueryBase($filters, ['aba' => 'inconsistentes'])) ?>" class="px-4 py-2 font-semibold <?= !empty($filters['apenas_inconsistentes']) ? 'bg-slate-900 text-white' : 'bg-white text-slate-700' ?>">Inconsistentes (<?= (int)$resumo['total_inconsistentes'] ?>)</a>
        </div>
    </form>

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Veículo</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Data</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Medição</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Tipo</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Fornecedor/Tanque</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Medida percorrida</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Quantidade</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Autonomia média</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Custo</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap">Anexo</th>
                    <th class="px-3 py-3 font-medium whitespace-nowrap"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($records as $r): ?>
                    <?php $chip = $chipFor($r['combustivel_nome'] ?? ''); ?>
                    <tr>
                        <td class="px-3 py-3 whitespace-nowrap">
                            <?php if ($r['inconsistente']): ?><span title="Autonomia abaixo do esperado" class="text-amber-500">⚠</span><?php endif; ?>
                            <?= sanitize($r['veiculo_plate'] ?? '-') ?>
                        </td>
                        <td class="px-3 py-3 whitespace-nowrap"><?= sanitize(date('d/m/y \à\s H:i', strtotime($r['data_hora']))) ?></td>
                        <td class="px-3 py-3 whitespace-nowrap"><?= number_format((float)$r['odometro'], 1, ',', '.') ?> km</td>
                        <td class="px-3 py-3 whitespace-nowrap"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $chip['bg'] ?> <?= $chip['text'] ?>"><?= sanitize($r['combustivel_nome'] ?? '-') ?></span></td>
                        <td class="px-3 py-3 whitespace-nowrap"><?= sanitize($r['tipo'] === 'interno' ? ($r['tank_nome'] ?? '-') : ($r['fornecedor_nome'] ?? '-')) ?></td>
                        <td class="px-3 py-3 whitespace-nowrap"><?= $r['medida_percorrida'] !== null ? number_format((float)$r['medida_percorrida'], 1, ',', '.') . ' km' : '-,-- km' ?></td>
                        <td class="px-3 py-3 whitespace-nowrap"><?= number_format((float)$r['quantidade'], 2, ',', '.') ?> L</td>
                        <td class="px-3 py-3 whitespace-nowrap">
                            <?php if ($r['inconsistente']): ?>
                                <span class="inline-flex items-center gap-1 rounded-lg bg-amber-50 text-amber-700 px-2 py-0.5">⚠ <?= number_format((float)$r['autonomia_media'], 2, ',', '.') ?> km/L</span>
                            <?php else: ?>
                                <?= $r['autonomia_media'] !== null ? number_format((float)$r['autonomia_media'], 2, ',', '.') . ' km/L' : '-,-- km/L' ?>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-3 whitespace-nowrap">
                            <?= $r['custo'] !== null ? 'R$ ' . number_format((float)$r['custo'], 2, ',', '.') : '-' ?>
                            <?php if ($r['valor_litro'] !== null): ?>
                                <div class="text-xs text-slate-400">R$ <?= number_format((float)$r['valor_litro'], 2, ',', '.') ?>/L</div>
                            <?php endif; ?>
                        </td>
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
                    <tr><td colspan="11" class="px-4 py-8 text-center text-slate-500">Nenhum abastecimento encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm">
            <span class="text-slate-500">Mostrando <?= count($records) ?> de <?= $totalRegistros ?></span>
            <div class="flex items-center gap-2">
                <a class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-1.5 <?= $pagina <= 1 ? 'pointer-events-none opacity-40' : 'hover:bg-slate-50' ?>" href="<?= sanitize($combustivelQueryBase($filters, ['pagina' => max(1, $pagina - 1)])) ?>">Anterior</a>
                <span class="text-slate-600">Página <?= $pagina ?> de <?= $totalPaginas ?></span>
                <a class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-1.5 <?= $pagina >= $totalPaginas ? 'pointer-events-none opacity-40' : 'hover:bg-slate-50' ?>" href="<?= sanitize($combustivelQueryBase($filters, ['pagina' => min($totalPaginas, $pagina + 1)])) ?>">Próxima</a>
            </div>
        </div>
    </div>
</div>
