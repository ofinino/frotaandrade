<?php
$people = $people ?? [];
$editPerson = $editPerson ?? null;
?>
<div class="space-y-4">
    <div class="bg-white border border-slate-100 shadow rounded-lg p-4">
        <div class="flex items-start justify-between mb-3">
            <div>
                <div class="font-semibold text-slate-900"><?= $editPerson ? 'Editar pessoa' : 'Nova pessoa' ?></div>
                <p class="text-xs text-slate-500">Preencha os dados e salve</p>
            </div>
            <button type="submit" form="people-form" class="hidden md:inline-flex items-center bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded">
                Salvar
            </button>
        </div>
        <form method="post" id="people-form" class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="sm:col-span-2">
                    <label class="block text-sm text-slate-600 mb-1">Nome completo</label>
                    <input class="w-full h-10 border border-slate-200 rounded px-3" name="nome_completo" value="<?= sanitize($editPerson['nome_completo'] ?? '') ?>" required />
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm text-slate-600 mb-1">Nome abreviado</label>
                    <input class="w-full h-10 border border-slate-200 rounded px-3" name="nome_abreviado" value="<?= sanitize($editPerson['nome_abreviado'] ?? '') ?>" />
                </div>
                <div>
                    <label class="block text-sm text-slate-600 mb-1">Email</label>
                    <input class="w-full h-10 border border-slate-200 rounded px-3" name="email_func" value="<?= sanitize($editPerson['email_func'] ?? '') ?>" />
                </div>
                <div>
                    <label class="block text-sm text-slate-600 mb-1">Telefone</label>
                    <input class="w-full h-10 border border-slate-200 rounded px-3" name="telefone_func" value="<?= sanitize($editPerson['telefone_func'] ?? '') ?>" />
                </div>
                <div>
                    <label class="block text-sm text-slate-600 mb-1">CPF</label>
                    <input class="w-full h-10 border border-slate-200 rounded px-3" name="cpf" value="<?= sanitize($editPerson['cpf'] ?? '') ?>" />
                </div>
                <div>
                    <label class="block text-sm text-slate-600 mb-1">RG</label>
                    <input class="w-full h-10 border border-slate-200 rounded px-3" name="rg" value="<?= sanitize($editPerson['rg'] ?? '') ?>" />
                </div>
                <div>
                    <label class="block text-sm text-slate-600 mb-1">Sexo</label>
                    <select name="sexo" class="w-full h-10 border border-slate-200 rounded px-3">
                        <option value="">-</option>
                        <option value="1" <?= isset($editPerson['sexo']) && $editPerson['sexo']=='1' ? 'selected' : '' ?>>Masculino</option>
                        <option value="2" <?= isset($editPerson['sexo']) && $editPerson['sexo']=='2' ? 'selected' : '' ?>>Feminino</option>
                        <option value="0" <?= isset($editPerson['sexo']) && $editPerson['sexo']=='0' ? 'selected' : '' ?>>Não informar</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-slate-600 mb-1">Função ID (opcional)</label>
                    <input class="w-full h-10 border border-slate-200 rounded px-3" name="funcao_id" value="<?= sanitize($editPerson['funcao_id'] ?? '') ?>" />
                </div>
                <div>
                    <label class="block text-sm text-slate-600 mb-1">Data de nascimento</label>
                    <input type="date" class="w-full h-10 border border-slate-200 rounded px-3" name="data_nascimento" value="<?= isset($editPerson['data_nascimento']) ? substr($editPerson['data_nascimento'],0,10) : '' ?>" />
                </div>
            </div>
            <button type="submit" class="md:hidden w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded">Salvar</button>
        </form>
    </div>

    <div class="bg-white border border-slate-100 shadow rounded-lg p-4">
        <div class="font-semibold text-slate-900 mb-3">Pessoas</div>
        <?php if (empty($people)): ?>
            <p class="text-sm text-slate-600">Nenhuma pessoa cadastrada.</p>
        <?php else: ?>
            <div class="overflow-x-auto text-sm">
                <table class="min-w-full">
                    <thead>
                        <tr class="text-left text-slate-500">
                            <th class="py-2">Nome</th>
                            <th class="py-2">Email</th>
                            <th class="py-2">Telefone</th>
                            <th class="py-2">CPF</th>
                            <th class="py-2">RG</th>
                            <th class="py-2 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($people as $p): ?>
                            <tr>
                                <td class="py-2 font-semibold text-slate-800"><?= sanitize($p['nome_completo'] ?? '') ?></td>
                                <td class="py-2 text-slate-600"><?= sanitize($p['email_func'] ?? '') ?></td>
                                <td class="py-2 text-slate-600"><?= sanitize($p['telefone_func'] ?? '') ?></td>
                                <td class="py-2 text-slate-600"><?= sanitize($p['cpf'] ?? '') ?></td>
                                <td class="py-2 text-slate-600"><?= sanitize($p['rg'] ?? '') ?></td>
                                <td class="py-2 text-right space-x-2">
                                    <a class="text-amber-600" href="index.php?page=people&edit=<?= $p['id'] ?>">Editar</a>
                                    <a class="text-rose-600" href="index.php?page=people&delete=<?= $p['id'] ?>" onclick="return confirm('Excluir pessoa?');">Excluir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
