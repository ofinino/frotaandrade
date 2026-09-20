<?php
// Dados do dashboard
$tables = $tables ?? [];
$counts = $counts ?? [];
$statusCounts = $statusCounts ?? [];
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
$statusAccent = [
    'pendente' => '#d97706',
    'em_andamento' => '#0284c7',
    'pausado' => '#64748b',
    'concluido' => '#16a34a',
];

$osStatusLabels = [
    'solicitacao' => 'Solicitação',
    'aguardando_agendamento' => 'Aguardando/Agendado',
    'em_execucao' => 'Em execução',
    'analise_aprovacao' => 'Análise e Aprovação',
    'encerrada' => 'Encerrada',
    'cancelada' => 'Cancelada',
];
$osStatusAccent = [
    'solicitacao' => '#1e293b',
    'aguardando_agendamento' => '#7c3aed',
    'em_execucao' => '#16a34a',
    'analise_aprovacao' => '#ea580c',
    'encerrada' => '#059669',
    'cancelada' => '#6b7280',
];

// Prepara série de combustível para gráfico simples
$maxSerieFuel = 0.0;
foreach ($serieCombustivel as $row) {
    $maxSerieFuel = max($maxSerieFuel, (float)($row['total'] ?? 0));
}
$maxSerieFuel = max($maxSerieFuel, 1.0);

$fmtMoeda = static fn(float $v): string => 'R$ ' . number_format($v, 2, ',', '.');
$fmtLitros = static fn(float $v): string => number_format($v, 1, ',', '.') . ' L';

/**
 * Card de estatística padrão do painel: mesmo tamanho/tipografia em toda a
 * página, só o traço lateral muda de cor conforme o status/contexto.
 */
$statCard = static function (string $label, string $value, ?string $accent = null, ?string $caption = null): string {
    $borderStyle = $accent ? "border-left:4px solid {$accent};" : '';
    $html = '<div class="os-card-surface h-full flex flex-col justify-between p-4" style="' . $borderStyle . '">';
    $html .= '<div class="text-sm text-slate-500">' . sanitize($label) . '</div>';
    $html .= '<div class="text-2xl font-semibold text-slate-900 os-mono mt-1">' . $value . '</div>';
    if ($caption !== null) {
        $html .= '<div class="text-xs text-slate-500 mt-1">' . sanitize($caption) . '</div>';
    }
    $html .= '</div>';
    return $html;
};
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
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6 items-stretch">
    <?php foreach ($tables as $table => $label): ?>
        <?= $statCard($label, (string)(int)($counts[$table] ?? 0)) ?>
    <?php endforeach; ?>
</div>

<!-- Checklists -->
<div class="os-section-title text-slate-900 mb-3">Checklists no período</div>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6 items-stretch">
    <?php foreach ($statusLabels as $key => $label): ?>
        <?= $statCard($label, (string)(int)($statusCounts[$key] ?? 0), $statusAccent[$key] ?? null) ?>
    <?php endforeach; ?>
</div>

<!-- Ordens de serviço -->
<div class="os-section-title text-slate-900 mb-3">Ordens de serviço (estado atual)</div>
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6 items-stretch">
    <?php foreach ($osStatusLabels as $key => $label): ?>
        <?= $statCard($label, (string)(int)($osStatusCounts[$key] ?? 0), $osStatusAccent[$key] ?? null) ?>
    <?php endforeach; ?>
</div>

<!-- Combustível -->
<div class="os-section-title text-slate-900 mb-3">Combustível no período</div>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4 items-stretch">
    <?= $statCard('Total gasto', $fmtMoeda($fuelSummary['custo_total']), null, $fuelSummary['total_abastecimentos'] . ' abastecimento(s)') ?>
    <?= $statCard('Litros abastecidos', $fmtLitros($fuelSummary['quantidade_total'])) ?>
    <?= $statCard('Preço médio por litro', $fmtMoeda($fuelSummary['preco_medio'])) ?>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-6 items-stretch">
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
