<?php
$users = $users ?? [];
$editUser = $editUser ?? null;
$roles = $roles ?? ['admin','gestor','lider','executante','membro'];
$current = $current ?? current_user();
?>
<div class="space-y-4">
    <div class="bg-white border border-slate-100 shadow rounded-lg p-4">
        <div class="flex items-start justify-between mb-3">
            <div>
                <div class="font-semibold text-slate-900"><?= $editUser ? 'Editar usuário' : 'Novo usuário' ?></div>
                <p class="text-xs text-slate-500">Defina o tipo e credenciais</p>
            </div>
            <button type="submit" form="user-form" class="hidden md:inline-flex items-center bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded">
                Salvar
            </button>
        </div>
        <form method="post" id="user-form" class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div>
                    <label class="block text-sm text-slate-600 mb-1">Nome</label>
                    <input class="w-full h-10 border border-slate-200 rounded px-3" name="name" required value="<?= sanitize($editUser['name'] ?? '') ?>" />
                </div>
                <div>
                    <label class="block text-sm text-slate-600 mb-1">Email</label>
                    <input class="w-full h-10 border border-slate-200 rounded px-3" type="email" name="email" required value="<?= sanitize($editUser['email'] ?? '') ?>" />
                </div>
                <div>
                    <label class="block text-sm text-slate-600 mb-1">Tipo</label>
                    <select name="role" class="w-full h-10 border border-slate-200 rounded px-3">
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= $role ?>" <?= (($editUser['role'] ?? '') === $role) ? 'selected' : '' ?>><?= format_role($role) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!is_admin()): ?>
                        <p class="text-xs text-slate-500 mt-1">Somente admin pode definir tipos acima de Membro.</p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm text-slate-600 mb-1">Senha <?= $editUser ? '(preencha para trocar)' : '' ?></label>
                    <input class="w-full h-10 border border-slate-200 rounded px-3" type="password" name="password" <?= $editUser ? '' : 'required' ?> />
                </div>
            </div>
            <button type="submit" class="md:hidden w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded">Salvar</button>
        </form>
    </div>

    <div class="bg-white border border-slate-100 shadow rounded-lg p-4">
        <div class="font-semibold text-slate-900 mb-3">Usuários</div>
        <div class="overflow-x-auto text-sm">
            <table class="min-w-full">
                <thead>
                    <tr class="text-left text-slate-500">
                        <th class="py-2">Nome</th>
                        <th class="py-2">Email</th>
                        <th class="py-2">Tipo</th>
                        <th class="py-2 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td class="py-2"><?= sanitize($user['name']) ?></td>
                        <td class="py-2"><?= sanitize($user['email']) ?></td>
                        <td class="py-2"><span class="px-2 py-1 rounded text-xs bg-slate-100 text-slate-700"><?= format_role($user['role']) ?></span></td>
                        <td class="py-2 text-right space-x-2">
                            <?php if (can_manage_role($user['role'])): ?>
                                <a class="text-amber-600" href="index.php?page=users&edit=<?= $user['id'] ?>">Editar</a>
                                <?php if ($user['id'] !== $current['id']): ?>
                                    <a class="text-rose-600" href="index.php?page=users&delete=<?= $user['id'] ?>" onclick="return confirm('Remover usuário?');">Excluir</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-slate-400">Restrito</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
