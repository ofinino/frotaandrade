<?php
// Dados do dashboard
$tables = $tables ?? [];
$counts = $counts ?? [];
$statusCounts = $statusCounts ?? [];
$pendentesPorExec = $pendentesPorExec ?? [];
$serieExecutadas = $serieExecutadas ?? [];
$fuelSummary = $fuelSummary ?? ['total_abastecimentos' => 0, 'custo_total' => 0.0, 'quantidade_total' => 0.0, 'preco_medio' => 0.0];
$serieCombustivel = $serieCombustivel ?? [];
$tanques = $tanques ?? [];
$osStatusCounts = $osStatusCounts ?? [];
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

$osStatusLabels = [
    'solicitacao' => 'Solicitação',
    'aguardando_agendamento' => 'Aguardando/Agendado',
    'em_execucao' => 'Em execução',
    'analise_aprovacao' => 'Análise e Aprovação',
    'encerrada' => 'Encerrada',
    'cancelada' => 'Cancelada',
];
$osStatusColors = [
    'solicitacao' => ['bg' => '#1e293b', 'wash' => '#f8fafc'],
    'aguardando_agendamento' => ['bg' => '#7c3aed', 'wash' => '#f5f3ff'],
    'em_execucao' => ['bg' => '#16a34a', 'wash' => '#f0fdf4'],
    'analise_aprovacao' => ['bg' => '#ea580c', 'wash' => '#fff7ed'],
    'encerrada' => ['bg' => '#059669', 'wash' => '#ecfdf5'],
    'cancelada' => ['bg' => '#6b7280', 'wash' => '#f9fafb'],
];

// Prepara série de checklists para gráfico simples
$maxSerie = 0;
foreach ($serieExecutadas as $row) {
    $maxSerie = max($maxSerie, (int)($row['total'] ?? 0));
}
$maxSerie = max($maxSerie, 1);

// Prepara série de combustível para gráfico simples
$maxSerieFuel = 0.0;
foreach ($serieCombustivel as $row) {
    $maxSerieFuel = max($maxSerieFuel, (float)($row['total'] ?? 0));
}
$maxSerieFuel = max($maxSerieFuel, 1.0);

$fmtMoeda = static fn(float $v): string => 'R$ ' . number_format($v, 2, ',', '.');
$fmtLitros = static fn(float $v): string => number_format($v, 1, ',', '.') . ' L';
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h1 class="text-xl font-semibold text-slate-900">Painel</h1>
    <div class="flex flex-wrap gap-2 text-sm">
        <?php foreach ($periodLabels as $key => $label): ?>
            <a href="?page=dashboard&period=<?= $key ?>"
               class="px-3 py-1 rounded border <?= $period === $key ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-200' ?>">
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Cadastros: cards principais -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-6">
    <?php foreach ($tables as $table => $label): ?>
        <div class="os-card-surface p-4">
            <div class="text-sm text-slate-500"><?= sanitize($label) ?></div>
            <div class="text-3xl font-semibold text-slate-900 os-mono"><?= (int)($counts[$table] ?? 0) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Checklists -->
<div class="os-section-title text-slate-900 mb-3">Checklists no período</div>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <?php foreach ($statusLabels as $key => $label): ?>
        <div class="rounded-lg border p-4 <?= $statusColors[$key] ?? 'bg-white border-slate-200 text-slate-800' ?>">
            <div class="text-sm"><?= $label ?></div>
            <div class="text-2xl font-semibold os-mono"><?= (int)($statusCounts[$key] ?? 0) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-6">
    <!-- Pendentes por executante -->
    <div class="os-card-surface p-4 xl:col-span-1">
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
                        <div class="text-sm font-semibold text-slate-900 os-mono"><?= (int)($row['total'] ?? 0) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Série de concluídos -->
    <div class="os-card-surface p-4 xl:col-span-2">
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
                            <span class="os-mono"><?= sanitize($dia) ?></span>
                            <span class="os-mono"><?= $total ?></span>
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

<!-- Combustível -->
<div class="os-section-title text-slate-900 mb-3">Combustível no período</div>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
    <div class="os-card-surface p-4">
        <div class="text-sm text-slate-500">Total gasto</div>
        <div class="text-2xl font-semibold text-slate-900 os-mono"><?= $fmtMoeda($fuelSummary['custo_total']) ?></div>
        <div class="text-xs text-slate-500 mt-1"><?= (int)$fuelSummary['total_abastecimentos'] ?> abastecimento(s)</div>
    </div>
    <div class="os-card-surface p-4">
        <div class="text-sm text-slate-500">Litros abastecidos</div>
        <div class="text-2xl font-semibold text-slate-900 os-mono"><?= $fmtLitros($fuelSummary['quantidade_total']) ?></div>
    </div>
    <div class="os-card-surface p-4">
        <div class="text-sm text-slate-500">Preço médio por litro</div>
        <div class="text-2xl font-semibold text-slate-900 os-mono"><?= $fmtMoeda($fuelSummary['preco_medio']) ?></div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-6">
    <!-- Gasto por dia -->
    <div class="os-card-surface p-4 xl:col-span-2">
        <div class="flex items-center justify-between mb-3">
            <div class="font-semibold text-slate-900">Gasto com combustível por dia</div>
        </div>
        <?php if (empty($serieCombustivel)): ?>
            <div class="text-sm text-slate-500">Sem abastecimentos no período.</div>
        <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($serieCombustivel as $row): ?>
                    <?php
                        $dia = $row['dia'] ?? '';
                        $total = (float)($row['total'] ?? 0);
                        $percent = min(100, round(($total / $maxSerieFuel) * 100, 1));
                    ?>
                    <div>
                        <div class="flex items-center justify-between text-xs text-slate-600 mb-1">
                            <span class="os-mono"><?= sanitize($dia) ?></span>
                            <span class="os-mono"><?= $fmtMoeda($total) ?></span>
                        </div>
                        <div class="h-2 rounded bg-slate-100 overflow-hidden">
                            <div class="h-full" style="width: <?= $percent ?>%; background: var(--os-signal);"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Saldo dos tanques -->
    <div class="os-card-surface p-4 xl:col-span-1">
        <div class="flex items-center justify-between mb-3">
            <div class="font-semibold text-slate-900">Saldo dos tanques</div>
        </div>
        <?php if (empty($tanques)): ?>
            <div class="text-sm text-slate-500">Nenhum tanque ativo cadastrado.</div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($tanques as $tanque): ?>
                    <?php
                        $capacidade = (float)($tanque['capacidade_maxima'] ?? 0);
                        $estoque = (float)($tanque['estoque_atual'] ?? 0);
                        $percentTanque = $capacidade > 0 ? min(100, round(($estoque / $capacidade) * 100, 1)) : 0;
                        $corTanque = $percentTanque < 20 ? '#dc2626' : ($percentTanque < 50 ? '#e2711d' : '#16a34a');
                    ?>
                    <div>
                        <div class="flex items-center justify-between text-xs text-slate-600 mb-1">
                            <span class="font-medium text-slate-800"><?= sanitize($tanque['nome'] ?? '-') ?></span>
                            <span class="os-mono"><?= $fmtLitros($estoque) ?> / <?= $fmtLitros($capacidade) ?></span>
                        </div>
                        <div class="h-2 rounded bg-slate-100 overflow-hidden">
                            <div class="h-full" style="width: <?= $percentTanque ?>%; background: <?= $corTanque ?>;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Ordens de serviço -->
<div class="os-section-title text-slate-900 mb-3">Ordens de serviço (estado atual)</div>
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
    <?php foreach ($osStatusLabels as $key => $label): ?>
        <?php $cor = $osStatusColors[$key] ?? ['bg' => '#334155', 'wash' => '#f8fafc']; ?>
        <div class="rounded-lg p-4 border" style="background: <?= $cor['wash'] ?>; border-color: <?= $cor['bg'] ?>33;">
            <div class="text-xs font-medium" style="color: <?= $cor['bg'] ?>;"><?= sanitize($label) ?></div>
            <div class="text-2xl font-semibold os-mono text-slate-900"><?= (int)($osStatusCounts[$key] ?? 0) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Atalhos -->
<div class="os-card-surface p-4">
    <div class="font-semibold text-slate-900 mb-3">Atalhos rápidos</div>
    <div class="flex flex-wrap gap-3 text-sm">
        <a class="px-4 py-2 rounded os-btn-primary" href="index.php?page=checks">Nova execução</a>
        <a class="px-4 py-2 rounded bg-slate-200 text-slate-800" href="index.php?page=templates">Criar modelo</a>
        <a class="px-4 py-2 rounded bg-slate-200 text-slate-800" href="index.php?page=os">Ordens de serviço</a>
        <a class="px-4 py-2 rounded bg-slate-200 text-slate-800" href="index.php?page=abastecimentos">Novo abastecimento</a>
        <a class="px-4 py-2 rounded bg-slate-200 text-slate-800" href="index.php?page=vehicles">Cadastrar veículo</a>
        <a class="px-4 py-2 rounded bg-slate-200 text-slate-800" href="index.php?page=people">Cadastrar pessoa</a>
        <?php if (has_permission('users.view')): ?>
            <a class="px-4 py-2 rounded bg-slate-200 text-slate-800" href="index.php?page=users">Gerenciar usuários</a>
        <?php endif; ?>
    </div>
</div>
