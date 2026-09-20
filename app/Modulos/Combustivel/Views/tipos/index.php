<?php
$tipos = $tipos ?? [];
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
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-sm <?= $t['ativo'] ? 'text-slate-800 font-medium' : 'text-slate-400 line-through' ?>"><?= sanitize($t['nome']) ?></span>
                <?php if (has_permission('combustivel.manage')): ?>
                    <form method="post" action="index.php?mod=combustivel&ctrl=FuelTypes&action=toggle">
<?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= sanitize($t['id']) ?>">
                        <input type="hidden" name="ativo" value="<?= $t['ativo'] ? '0' : '1' ?>">
                        <button class="text-xs font-semibold text-blue-600 hover:underline"><?= $t['ativo'] ? 'Inativar' : 'Reativar' ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if (!$tipos): ?>
            <div class="px-4 py-6 text-sm text-slate-500 text-center">Nenhum tipo cadastrado.</div>
        <?php endif; ?>
    </div>
</div>
