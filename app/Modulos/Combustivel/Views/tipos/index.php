<?php
$tipos = $tipos ?? [];
$temUso = $temUso ?? [];
?>
<div class="max-w-3xl mx-auto px-4 py-6 space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-semibold text-slate-900">Tipos de combustível</h2>
        <a class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" href="index.php?page=abastecimentos">Voltar aos abastecimentos</a>
    </div>

    <?php if (has_permission('combustivel.manage')): ?>
        <form class="flex items-center gap-2 bg-white border border-slate-200 rounded-2xl p-4 shadow-sm" method="post" action="index.php?mod=combustivel&ctrl=FuelTypes&action=store">
<?= csrf_field() ?>
            <input class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm" name="nome" placeholder="Ex: Diesel S10" required>
            <button class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Adicionar</button>
        </form>
    <?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm divide-y divide-slate-100">
        <?php foreach ($tipos as $t): ?>
            <?php $editId = 'tipo-edit-' . $t['id']; $usado = $temUso[$t['id']] ?? false; ?>
            <div class="px-4 py-3">
                <div id="tipo-view-<?= sanitize($t['id']) ?>" class="flex items-center justify-between">
                    <span class="text-sm <?= $t['ativo'] ? 'text-slate-800 font-medium' : 'text-slate-400 line-through' ?>"><?= sanitize($t['nome']) ?></span>
                    <?php if (has_permission('combustivel.manage')): ?>
                        <div class="flex items-center gap-3">
                            <button type="button" onclick="document.getElementById('tipo-view-<?= sanitize($t['id']) ?>').classList.add('hidden'); document.getElementById('<?= $editId ?>').classList.remove('hidden');" class="text-xs font-semibold text-blue-600 hover:underline">Editar</button>
                            <form method="post" action="index.php?mod=combustivel&ctrl=FuelTypes&action=toggle">
<?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= sanitize($t['id']) ?>">
                                <input type="hidden" name="ativo" value="<?= $t['ativo'] ? '0' : '1' ?>">
                                <button class="text-xs font-semibold text-blue-600 hover:underline"><?= $t['ativo'] ? 'Inativar' : 'Reativar' ?></button>
                            </form>
                            <?php if (!$usado): ?>
                                <form method="post" action="index.php?mod=combustivel&ctrl=FuelTypes&action=destroy" onsubmit="return confirm('Excluir este tipo de combustível?');">
<?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= sanitize($t['id']) ?>">
                                    <button class="text-xs text-red-500 hover:underline">Excluir</button>
                                </form>
                            <?php else: ?>
                                <span class="text-xs text-slate-400" title="Ja usado em abastecimentos">Em uso</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (has_permission('combustivel.manage')): ?>
                    <div id="<?= $editId ?>" class="hidden mt-2 flex items-center gap-2">
                        <form method="post" action="index.php?mod=combustivel&ctrl=FuelTypes&action=update" class="flex flex-1 items-center gap-2">
<?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= sanitize($t['id']) ?>">
                            <input class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm" name="nome" value="<?= sanitize($t['nome']) ?>" required>
                            <button class="inline-flex items-center rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Salvar</button>
                        </form>
                        <button type="button" onclick="document.getElementById('<?= $editId ?>').classList.add('hidden'); document.getElementById('tipo-view-<?= sanitize($t['id']) ?>').classList.remove('hidden');" class="text-xs text-slate-500 hover:underline">Cancelar</button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if (!$tipos): ?>
            <div class="px-4 py-6 text-sm text-slate-500 text-center">Nenhum tipo cadastrado.</div>
        <?php endif; ?>
    </div>
</div>
