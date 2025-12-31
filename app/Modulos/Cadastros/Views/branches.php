<?php
$branches = $branches ?? [];
$currentBranch = $currentBranch ?? null;
?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-white border border-slate-100 shadow rounded-lg p-4">
        <div class="font-semibold text-slate-900 mb-2">Filiais</div>
        <div class="text-sm divide-y divide-slate-100">
            <?php foreach ($branches as $branch): ?>
                <div class="py-2 flex items-center justify-between">
                    <div>
                        <div class="font-semibold"><?= sanitize($branch['name']) ?></div>
                        <div class="text-xs text-slate-500">ID: <?= sanitize($branch['id']) ?></div>
                    </div>
                    <div class="text-xs">
                        <?php if ((int)$currentBranch === (int)$branch['id']): ?>
                            <span class="px-3 py-1 rounded-full bg-emerald-100 text-emerald-700">Atual</span>
                        <?php else: ?>
                            <a class="px-3 py-1 rounded bg-slate-900 text-white" href="index.php?page=branches&set=<?= $branch['id'] ?>">Usar</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="bg-white border border-slate-100 shadow rounded-lg p-4">
        <div class="font-semibold text-slate-900 mb-2">Nova filial</div>
        <form method="post" class="space-y-3">
            <div>
                <label class="block text-sm text-slate-600 mb-1">Nome</label>
                <input class="w-full border border-slate-200 rounded px-3 py-2" name="name" required />
            </div>
            <button class="bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded" type="submit">Criar</button>
        </form>
    </div>
</div>
