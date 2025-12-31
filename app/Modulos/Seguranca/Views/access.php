<?php
$users = $users ?? [];
$roles = $roles ?? [];
$branches = $branches ?? [];
$rolesByUser = $rolesByUser ?? [];
$branchesByUser = $branchesByUser ?? [];
$features = $features ?? [];
$permsByRole = $permsByRole ?? [];
$selectedRoleId = $selectedRoleId ?? 0;

$selectedRoleId = $selectedRoleId ?: ($roles[0]['id'] ?? 0);
?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-white border border-slate-100 shadow rounded-lg p-4 space-y-3">
        <div class="font-semibold text-slate-900">Criar papel</div>
        <form method="post" class="space-y-3">
            <input type="hidden" name="create_role" value="1" />
            <div>
                <label class="block text-sm text-slate-600 mb-1">Nome</label>
                <input name="nome" required class="w-full border border-slate-200 rounded px-3 py-2" placeholder="executante, gestor..." />
            </div>
            <div>
                <label class="block text-sm text-slate-600 mb-1">Descricao</label>
                <input name="descricao" class="w-full border border-slate-200 rounded px-3 py-2" placeholder="Opcional" />
            </div>
            <button class="bg-slate-900 hover:bg-slate-800 text-white font-semibold px-4 py-2 rounded" type="submit">Salvar papel</button>
        </form>
    </div>

    <div class="bg-white border border-slate-100 shadow rounded-lg p-4 space-y-3">
        <div class="flex items-center justify-between">
            <div class="font-semibold text-slate-900">Permissoes por papel</div>
            <?php if ($selectedRoleId): ?>
                <form method="post" onsubmit="return confirm('Remover este papel?');">
                    <input type="hidden" name="delete_role" value="1" />
                    <input type="hidden" name="role_id" value="<?= $selectedRoleId ?>" />
                    <button class="text-rose-600 text-sm" type="submit">Remover</button>
                </form>
            <?php endif; ?>
        </div>

        <form id="role-switch" method="get" class="mb-3">
            <input type="hidden" name="page" value="access" />
            <label class="block text-sm text-slate-600 mb-1">Papel</label>
            <select name="role_id" class="w-full border border-slate-200 rounded px-3 py-2" onchange="this.form.submit()">
                <?php foreach ($roles as $r): ?>
                    <option value="<?= $r['id'] ?>" <?= $r['id'] == $selectedRoleId ? 'selected' : '' ?>>
                        <?= sanitize($r['nome'] ?? $r['name'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <form method="post" class="space-y-3">
            <input type="hidden" name="save_permissions" value="1" />
            <input type="hidden" name="role_id" value="<?= $selectedRoleId ?>" />
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                <?php foreach ($features as $feat): ?>
                    <?php $checked = in_array($feat['key'], $permsByRole[$selectedRoleId] ?? [], true); ?>
                    <label class="flex items-center gap-2 text-sm border border-slate-200 rounded px-3 py-2">
                        <input type="checkbox" name="features[]" value="<?= $feat['key'] ?>" <?= $checked ? 'checked' : '' ?> />
                        <span><?= sanitize($feat['label']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <button class="bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded" type="submit">Salvar permissoes</button>
        </form>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
    <div class="bg-white border border-slate-100 shadow rounded-lg p-4 space-y-3">
        <div class="font-semibold text-slate-900">Atribuir papel ao usuario</div>
        <form method="post" class="space-y-3">
            <input type="hidden" name="assign_role" value="1" />
            <div>
                <label class="block text-sm text-slate-600 mb-1">Usuario</label>
                <select name="user_id" class="w-full border border-slate-200 rounded px-3 py-2">
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= sanitize($u['name']) ?> (<?= sanitize($u['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm text-slate-600 mb-1">Papel</label>
                <select name="role_id" class="w-full border border-slate-200 rounded px-3 py-2">
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= sanitize($r['nome'] ?? $r['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="bg-slate-900 hover:bg-slate-800 text-white font-semibold px-4 py-2 rounded" type="submit">Atribuir</button>
        </form>
    </div>

    <div class="bg-white border border-slate-100 shadow rounded-lg p-4 space-y-3">
        <div class="font-semibold text-slate-900">Atribuir filial</div>
        <form method="post" class="space-y-3">
            <input type="hidden" name="assign_branch" value="1" />
            <div>
                <label class="block text-sm text-slate-600 mb-1">Usuario</label>
                <select name="user_id" class="w-full border border-slate-200 rounded px-3 py-2">
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= sanitize($u['name']) ?> (<?= sanitize($u['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm text-slate-600 mb-1">Filial</label>
                <select name="branch_id" class="w-full border border-slate-200 rounded px-3 py-2">
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= sanitize($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded" type="submit">Atribuir</button>
        </form>
    </div>
</div>

<div class="bg-white border border-slate-100 shadow rounded-lg p-4 mt-4">
    <div class="font-semibold text-slate-900 mb-2">Resumo de acessos</div>
    <div class="overflow-x-auto text-sm">
        <table class="min-w-full">
            <thead>
                <tr class="text-left text-slate-500">
                    <th class="py-2">Usuario</th>
                    <th class="py-2">Papeis</th>
                    <th class="py-2">Filiais</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="py-2">
                            <?= sanitize($u['name']) ?><br>
                            <span class="text-xs text-slate-500"><?= sanitize($u['email']) ?></span>
                        </td>
                        <td class="py-2 text-xs">
                            <?php if (!empty($rolesByUser[$u['id']])): ?>
                                <?php foreach ($rolesByUser[$u['id']] as $role): ?>
                                    <form method="post" class="inline-block">
                                        <input type="hidden" name="remove_role" value="1" />
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>" />
                                        <input type="hidden" name="role_id" value="<?= $role['id'] ?>" />
                                        <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 px-2 py-1 rounded mb-1">
                                            <?= sanitize($role['name']) ?>
                                            <button type="submit" class="text-rose-600 text-[11px]" title="Remover">&#10005;</button>
                                        </span>
                                    </form>
                                <?php endforeach; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="py-2 text-xs">
                            <?php if (!empty($branchesByUser[$u['id']])): ?>
                                <?php foreach ($branchesByUser[$u['id']] as $branch): ?>
                                    <form method="post" class="inline-block">
                                        <input type="hidden" name="remove_branch" value="1" />
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>" />
                                        <input type="hidden" name="branch_id" value="<?= $branch['id'] ?>" />
                                        <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 px-2 py-1 rounded mb-1">
                                            <?= sanitize($branch['name']) ?>
                                            <button type="submit" class="text-rose-600 text-[11px]" title="Remover">&#10005;</button>
                                        </span>
                                    </form>
                                <?php endforeach; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
