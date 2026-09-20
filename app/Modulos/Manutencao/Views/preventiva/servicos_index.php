<?php
$servicos = $servicos ?? [];
?>
<div class="max-w-3xl mx-auto px-4 py-6 space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-semibold text-slate-900">Serviços de manutenção</h2>
        <a class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" href="index.php?page=planos_preventiva">Voltar aos planos</a>
    </div>

    <?php if (has_permission('preventiva.manage')): ?>
        <form class="flex items-center gap-2 bg-white border border-slate-200 rounded-2xl p-4 shadow-sm" method="post" action="index.php?mod=manutencao&ctrl=ManutencaoServicos&action=store">
<?= csrf_field() ?>
            <input class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm" name="nome" placeholder="Ex: Troca de óleo" required>
            <button class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Adicionar</button>
        </form>
    <?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm divide-y divide-slate-100">
        <?php foreach ($servicos as $s): ?>
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-sm <?= $s['ativo'] ? 'text-slate-800 font-medium' : 'text-slate-400 line-through' ?>"><?= sanitize($s['nome']) ?></span>
                <?php if (has_permission('preventiva.manage')): ?>
                    <form method="post" action="index.php?mod=manutencao&ctrl=ManutencaoServicos&action=toggle">
<?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= sanitize($s['id']) ?>">
                        <input type="hidden" name="ativo" value="<?= $s['ativo'] ? '0' : '1' ?>">
                        <button class="text-xs font-semibold text-blue-600 hover:underline"><?= $s['ativo'] ? 'Inativar' : 'Reativar' ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if (!$servicos): ?>
            <div class="px-4 py-6 text-sm text-slate-500 text-center">Nenhum serviço cadastrado.</div>
        <?php endif; ?>
    </div>
</div>
