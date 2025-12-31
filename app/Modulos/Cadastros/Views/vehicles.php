<?php
$vehicles = $vehicles ?? [];
$editVehicle = $editVehicle ?? null;
?>
<div class="space-y-4">
    <div class="bg-white border border-slate-100 shadow rounded-lg p-4">
        <div class="flex items-center justify-between mb-2">
            <div class="font-semibold text-slate-900"><?= $editVehicle ? 'Editar veículo' : 'Novo veículo' ?></div>
            <button type="submit" form="form-veiculos" class="bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded text-sm hidden lg:inline-block">Salvar</button>
        </div>
        <form id="form-veiculos" method="post" class="grid grid-cols-1 lg:grid-cols-4 gap-3 items-end">
            <?php if ($editVehicle): ?>
                <input type="hidden" name="edit_id" value="<?= (int) $editVehicle['id'] ?>" />
            <?php endif; ?>
            <div>
                <label class="block text-sm text-slate-600 mb-1">Placa</label>
                <input class="w-full border border-slate-200 rounded px-3 py-2 uppercase" name="plate" value="<?= sanitize($editVehicle['plate'] ?? '') ?>" required />
            </div>
            <div>
                <label class="block text-sm text-slate-600 mb-1">Modelo</label>
                <input class="w-full border border-slate-200 rounded px-3 py-2" name="model" value="<?= sanitize($editVehicle['model'] ?? '') ?>" />
            </div>
            <div>
                <label class="block text-sm text-slate-600 mb-1">Ano</label>
                <input class="w-full border border-slate-200 rounded px-3 py-2" name="year" value="<?= sanitize($editVehicle['year'] ?? '') ?>" />
            </div>
            <div class="lg:col-span-4">
                <label class="block text-sm text-slate-600 mb-1">Obs</label>
                <textarea class="w-full border border-slate-200 rounded px-3 py-2 h-[42px] resize-none" name="notes" rows="1"><?= sanitize($editVehicle['notes'] ?? '') ?></textarea>
            </div>
            <div class="lg:hidden">
                <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded text-sm">Salvar</button>
            </div>
        </form>
    </div>

    <div class="bg-white border border-slate-100 shadow rounded-lg p-4">
        <div class="font-semibold text-slate-900 mb-3">Veículos</div>
        <?php if (empty($vehicles)): ?>
            <p class="text-sm text-slate-600">Nenhum veículo cadastrado.</p>
        <?php else: ?>
            <div class="overflow-x-auto text-sm">
                <table class="min-w-full">
                    <thead>
                        <tr class="text-left text-slate-500">
                            <th class="py-2">Placa</th>
                            <th class="py-2">Modelo</th>
                            <th class="py-2">Ano</th>
                            <th class="py-2">Obs</th>
                            <th class="py-2 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($vehicles as $v): ?>
                            <tr>
                                <td class="py-2 font-semibold text-slate-800"><?= sanitize($v['plate']) ?></td>
                                <td class="py-2 text-slate-600"><?= sanitize($v['model']) ?></td>
                                <td class="py-2 text-slate-600"><?= sanitize($v['year']) ?></td>
                                <td class="py-2 text-slate-600"><?= sanitize($v['notes']) ?></td>
                                <td class="py-2 text-right space-x-2">
                                    <a class="text-amber-600" href="index.php?page=vehicles&edit=<?= $v['id'] ?>">Editar</a>
                                    <a class="text-rose-600" href="index.php?page=vehicles&delete=<?= $v['id'] ?>" onclick="return confirm('Excluir veiculo?');">Excluir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
