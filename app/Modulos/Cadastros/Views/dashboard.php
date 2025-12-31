<?php
// Dados do dashboard
$tables = $tables ?? [];
$counts = $counts ?? [];
$statusCounts = $statusCounts ?? [];
$pendentesPorExec = $pendentesPorExec ?? [];
$serieExecutadas = $serieExecutadas ?? [];
$period = $period ?? 'month';

$periodLabels = [
    'day' => 'Dia',
    'week' => 'Semana',
    'month' => 'Mês',
    'year' => 'Ano',
];
$statusLabels = [
    'pendente' => 'Pendentes',
    'em_andamento' => 'Em andamento',
    'pausado' => 'Pausados',
    'concluido' => 'Concluídos',
];
$statusColors = [
    'pendente' => 'bg-amber-50 border-amber-200 text-amber-700',
    'em_andamento' => 'bg-sky-50 border-sky-200 text-sky-700',
    'pausado' => 'bg-slate-50 border-slate-200 text-slate-700',
    'concluido' => 'bg-emerald-50 border-emerald-200 text-emerald-700',
];

// Prepara série para gráfico simples
$maxSerie = 0;
foreach ($serieExecutadas as $row) {
    $maxSerie = max($maxSerie, (int)($row['total'] ?? 0));
}
$maxSerie = max($maxSerie, 1);
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h1 class="text-xl font-semibold text-slate-900">Dashboard</h1>
    <div class="flex flex-wrap gap-2 text-sm">
        <?php foreach ($periodLabels as $key => $label): ?>
            <a href="?page=dashboard&period=<?= $key ?>"
               class="px-3 py-1 rounded border <?= $period === $key ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-200' ?>">
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Cards principais -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-4">
    <?php foreach ($tables as $table => $label): ?>
        <div class="bg-white shadow rounded-lg p-4 border border-slate-100">
            <div class="text-sm text-slate-500"><?= sanitize($label) ?></div>
            <div class="text-3xl font-semibold text-slate-900"><?= (int)($counts[$table] ?? 0) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Status das execuções -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <?php foreach ($statusLabels as $key => $label): ?>
        <div class="rounded-lg border p-4 shadow-sm <?= $statusColors[$key] ?? 'bg-white border-slate-200 text-slate-800' ?>">
            <div class="text-sm"><?= $label ?></div>
            <div class="text-2xl font-semibold"><?= (int)($statusCounts[$key] ?? 0) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-4">
    <!-- Pendentes por executante -->
    <div class="bg-white border border-slate-100 shadow rounded-lg p-4 xl:col-span-1">
        <div class="flex items-center justify-between mb-3">
            <div class="font-semibold text-slate-900">Pendentes por executante</div>
        </div>
        <?php if (empty($pendentesPorExec)): ?>
            <div class="text-sm text-slate-500">Nenhuma pendência.</div>
        <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($pendentesPorExec as $row): ?>
                    <div class="flex items-center justify-between rounded border border-slate-200 px-3 py-2">
                        <div class="text-sm text-slate-800"><?= sanitize($row['executante'] ?? 'Sem executante') ?></div>
                        <div class="text-sm font-semibold text-slate-900"><?= (int)($row['total'] ?? 0) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Série de concluídos -->
    <div class="bg-white border border-slate-100 shadow rounded-lg p-4 xl:col-span-2">
        <div class="flex items-center justify-between mb-3">
            <div class="font-semibold text-slate-900">Execuções concluídas no período</div>
        </div>
        <?php if (empty($serieExecutadas)): ?>
            <div class="text-sm text-slate-500">Sem execuções concluídas no período.</div>
        <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($serieExecutadas as $row): ?>
                    <?php
                        $dia = $row['dia'] ?? '';
                        $total = (int)($row['total'] ?? 0);
                        $percent = min(100, round(($total / $maxSerie) * 100, 1));
                    ?>
                    <div>
                        <div class="flex items-center justify-between text-xs text-slate-600 mb-1">
                            <span><?= sanitize($dia) ?></span>
                            <span><?= $total ?></span>
                        </div>
                        <div class="h-2 rounded bg-slate-100 overflow-hidden">
                            <div class="h-full bg-emerald-500" style="width: <?= $percent ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Atalhos -->
<div class="bg-white border border-slate-100 shadow rounded-lg p-4">
    <div class="font-semibold text-slate-900 mb-3">Atalhos rápidos</div>
    <div class="flex flex-wrap gap-3 text-sm">
        <a class="px-4 py-2 rounded bg-amber-500 text-white" href="index.php?page=checks">Nova execução</a>
        <a class="px-4 py-2 rounded bg-slate-900 text-white" href="index.php?page=templates">Criar modelo</a>
        <a class="px-4 py-2 rounded bg-slate-200 text-slate-800" href="index.php?page=vehicles">Cadastrar veículo</a>
        <a class="px-4 py-2 rounded bg-slate-200 text-slate-800" href="index.php?page=people">Cadastrar pessoa</a>
        <?php if (has_permission('users.view')): ?>
            <a class="px-4 py-2 rounded bg-slate-200 text-slate-800" href="index.php?page=users">Gerenciar usuários</a>
        <?php endif; ?>
    </div>
</div>
