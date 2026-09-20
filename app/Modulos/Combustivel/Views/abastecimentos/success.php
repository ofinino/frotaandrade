<?php
$record = $record ?? [];
$veiculo = $veiculo ?? [];
$combustivelNome = $combustivelNome ?? '';
?>
<div class="max-w-md mx-auto px-4 py-10">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-8 text-center space-y-4">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 text-3xl">✓</div>
        <h2 class="text-xl font-semibold text-slate-900">Novo abastecimento inserido com sucesso</h2>

        <dl class="text-left text-sm divide-y divide-slate-100 border border-slate-100 rounded-xl overflow-hidden">
            <div class="flex justify-between px-4 py-2 bg-slate-50">
                <dt class="text-slate-500">Data</dt>
                <dd class="font-medium text-slate-800"><?= sanitize(date('d/m/Y \à\s H:i', strtotime($record['data_hora']))) ?></dd>
            </div>
            <div class="flex justify-between px-4 py-2">
                <dt class="text-slate-500">Veículo</dt>
                <dd class="font-medium text-slate-800"><?= sanitize($veiculo['plate'] ?? '-') ?></dd>
            </div>
            <div class="flex justify-between px-4 py-2 bg-slate-50">
                <dt class="text-slate-500">Combustível</dt>
                <dd class="font-medium text-slate-800"><?= sanitize($combustivelNome) ?></dd>
            </div>
            <div class="flex justify-between px-4 py-2">
                <dt class="text-slate-500">Quantidade</dt>
                <dd class="font-medium text-slate-800"><?= number_format((float)$record['quantidade'], 2, ',', '.') ?> L</dd>
            </div>
            <div class="flex justify-between px-4 py-2 bg-slate-50">
                <dt class="text-slate-500">Odômetro</dt>
                <dd class="font-medium text-slate-800"><?= number_format((float)$record['odometro'], 1, ',', '.') ?> km</dd>
            </div>
            <div class="flex justify-between px-4 py-2">
                <dt class="text-slate-500">Custo</dt>
                <dd class="font-medium text-slate-800">R$ <?= number_format((float)($record['custo'] ?? 0), 2, ',', '.') ?></dd>
            </div>
        </dl>

        <div class="flex flex-col gap-2 pt-2">
            <a class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800" href="index.php?page=abastecimentos">Concluir</a>
            <a class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" href="index.php?mod=combustivel&ctrl=Abastecimentos&action=create">Novo Abastecimento</a>
        </div>
    </div>
</div>
