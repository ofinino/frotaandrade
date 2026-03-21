<?php
$vehicles = $vehicles ?? [];
$editVehicle = $editVehicle ?? null;
$filters = $filters ?? ['ativo' => '', 'ano_de' => '', 'ano_ate' => '', 'modelo' => '', 'frota' => '', 'tipo' => ''];
$total = $total ?? count($vehicles);
?>
<div class="space-y-4">
    <div class="bg-white border border-slate-100 shadow rounded-lg p-4">
        <div class="font-semibold text-slate-900 mb-2">Veículos</div>
        <div class="text-sm text-slate-700">
            Veículos são cadastrados e atualizados no sistema Bd_Vand. Esta tela é somente para consulta.
        </div>
    </div>

    <div class="bg-white border border-slate-100 shadow rounded-lg p-4">
        <form class="flex flex-wrap gap-2 text-sm items-center mb-2" method="get" action="index.php">
            <input type="hidden" name="page" value="vehicles">
            <select name="ativo" class="border border-slate-200 rounded px-2 py-1">
                <option value="">Ativos/Desativados</option>
                <option value="1" <?= $filters['ativo'] === '1' ? 'selected' : '' ?>>Somente ativos</option>
                <option value="0" <?= $filters['ativo'] === '0' ? 'selected' : '' ?>>Somente desativados</option>
            </select>
            <input name="frota" class="border border-slate-200 rounded px-2 py-1" placeholder="Frota/Placa" value="<?= sanitize($filters['frota']) ?>">
            <input name="ano_de" class="border border-slate-200 rounded px-2 py-1 w-24" placeholder="Ano de" value="<?= sanitize($filters['ano_de']) ?>">
            <input name="ano_ate" class="border border-slate-200 rounded px-2 py-1 w-24" placeholder="Ano até" value="<?= sanitize($filters['ano_ate']) ?>">
            <input name="modelo" class="border border-slate-200 rounded px-2 py-1" placeholder="Modelo" value="<?= sanitize($filters['modelo']) ?>">
            <input name="tipo" class="border border-slate-200 rounded px-2 py-1" placeholder="Tipo de veículo" value="<?= sanitize($filters['tipo']) ?>">
            <button class="bg-slate-800 text-white px-3 py-1 rounded">Filtrar</button>
            <a class="px-3 py-1 rounded border border-slate-200" href="index.php?page=vehicles">Limpar</a>
        </form>
        <div class="text-sm text-slate-600 mb-2">Total: <?= (int)$total ?> veículo(s) encontrado(s).</div>
        <?php if (empty($vehicles)): ?>
            <p class="text-sm text-slate-600">Nenhum veículo cadastrado.</p>
        <?php else: ?>
            <div class="overflow-x-auto text-sm">
                <table class="min-w-full">
                    <thead>
                        <tr class="text-left text-slate-500">
                            <th class="py-2">Frota</th>
                            <th class="py-2">Placa</th>
                            <th class="py-2">Modelo</th>
                            <th class="py-2">Ano</th>
                            <th class="py-2">Lugares</th>
                            <th class="py-2">Tipo</th>
                            <th class="py-2">Chassis</th>
                            <th class="py-2">Ativo</th>
                            <th class="py-2">Obs</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($vehicles as $v): ?>
                            <tr>
                                <td class="py-2 text-slate-600"><?= sanitize($v['plate']) ?></td>
                                <td class="py-2 font-semibold text-slate-800"><?= sanitize($v['txt_placa_veiculo']) ?></td>
                                <td class="py-2 text-slate-600"><?= sanitize($v['model']) ?></td>
                                <td class="py-2 text-slate-600"><?= sanitize($v['year']) ?></td>
                                <td class="py-2 text-slate-600"><?= sanitize($v['nin_lotacao_sentado']) ?></td>
                                <td class="py-2 text-slate-600"><?= sanitize($v['txt_tipo_veiculo']) ?></td>
                                <td class="py-2 text-slate-600"><?= sanitize($v['txt_chassis']) ?></td>
                                <?php $isAtivo = ((string)($v['csn_ativo'] ?? '')) !== '0'; ?>
                                <td class="py-2 text-slate-600">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs <?= $isAtivo ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' ?>">
                                        <?= $isAtivo ? 'Ativo' : 'Desativado' ?>
                                    </span>
                                </td>
                                <td class="py-2 text-slate-600"><?= sanitize($v['notes']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
