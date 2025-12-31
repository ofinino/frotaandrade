<?php
$groups = $groups ?? [];
$tipos = [
    'veiculo' => 'Veículo',
    'maquina' => 'Máquina',
    'equipamento' => 'Equipamento',
    'construcao' => 'Construção',
    'componente' => 'Componente industrial',
    'outro' => 'Outro',
];
?>
<div class="space-y-4">
    <div class="bg-white border border-slate-100 shadow rounded-lg p-4">
        <div class="flex items-start justify-between mb-3">
            <div>
                <div class="font-semibold text-slate-900"><?= !empty($editingGroup) ? 'Editar grupo' : 'Novo grupo' ?></div>
                <p class="text-xs text-slate-500">Use para separar veículos, máquinas, obras, etc.</p>
            </div>
            <button type="submit" form="group-form" class="hidden md:inline-flex items-center bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded">
                Salvar
            </button>
        </div>
        <form method="post" id="group-form" class="grid grid-cols-1 lg:grid-cols-4 gap-3 items-end">
            <div class="lg:col-span-2">
                <label class="block text-sm text-slate-600 mb-1">Nome do grupo</label>
                <input class="w-full h-10 border border-slate-200 rounded px-3" name="nome" required value="<?= isset($editingGroup['nome']) ? sanitize($editingGroup['nome']) : '' ?>" />
            </div>
            <div>
                <label class="block text-sm text-slate-600 mb-1">Tipo</label>
                <select name="tipo" class="w-full h-10 border border-slate-200 rounded px-3">
                    <?php foreach ($tipos as $val => $label): ?>
                        <option value="<?= $val ?>" <?= isset($editingGroup['tipo']) && $editingGroup['tipo'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lg:col-span-1 flex items-center gap-2">
                <?php if (!empty($editingGroup)): ?>
                    <a class="hidden md:inline-flex text-sm text-slate-600 hover:text-slate-800" href="index.php?page=groups">Cancelar</a>
                <?php endif; ?>
                <button type="submit" class="md:hidden w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded">Salvar</button>
            </div>
        </form>
    </div>

    <div class="bg-white border border-slate-100 shadow rounded-lg p-4">
        <div class="font-semibold text-slate-900 mb-3">Grupos cadastrados</div>
        <?php if (empty($groups)): ?>
            <p class="text-sm text-slate-600">Nenhum grupo cadastrado.</p>
        <?php else: ?>
            <div class="overflow-x-auto text-sm">
                <table class="min-w-full">
                    <thead>
                        <tr class="text-left text-slate-500">
                            <th class="py-2">Nome</th>
                            <th class="py-2">Tipo</th>
                            <th class="py-2">Filial</th>
                            <th class="py-2 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($groups as $g): ?>
                            <tr>
                                <td class="py-2 font-semibold text-slate-800"><?= sanitize($g['nome']) ?></td>
                                <td class="py-2 text-slate-600"><?= $tipos[$g['tipo']] ?? sanitize($g['tipo']) ?></td>
                                <td class="py-2 text-slate-600"><?= $g['filial_id'] ? 'Filial ' . (int)$g['filial_id'] : '-' ?></td>
                                <td class="py-2 text-right">
                                <a class="text-blue-600 mr-2" href="index.php?page=groups&edit=<?= $g['id'] ?>">Editar</a>
                                <a class="text-rose-600" href="index.php?page=groups&delete=<?= $g['id'] ?>" onclick="return confirm('Excluir grupo? Modelos associados serão afetados.');">Excluir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
