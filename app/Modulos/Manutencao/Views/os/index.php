<?php
$orders = $orders ?? [];
$veiculos = $veiculos ?? [];
$executores = $executores ?? [];
$filters = $filters ?? [];
$agendaDate = $agendaDate ?? date('Y-m-d');

$statusLabels = [
    'rascunho' => 'Rascunho',
    'aprovada' => 'Aprovada',
    'programada' => 'Programada',
    'em_execucao' => 'Em execucao',
    'aguardando_pecas' => 'Aguardando pecas',
    'concluida' => 'Concluida',
    'encerrada' => 'Encerrada',
    'cancelada' => 'Cancelada',
];
$statusColors = [
    'rascunho' => 'bg-slate-100 text-slate-700',
    'aprovada' => 'bg-blue-100 text-blue-800',
    'programada' => 'bg-indigo-100 text-indigo-800',
    'em_execucao' => 'bg-amber-100 text-amber-800',
    'aguardando_pecas' => 'bg-rose-100 text-rose-800',
    'concluida' => 'bg-emerald-100 text-emerald-800',
    'encerrada' => 'bg-slate-200 text-slate-800',
    'cancelada' => 'bg-gray-200 text-gray-700',
];

$getProgramadaDate = static function (?string $programada): ?string {
    $raw = trim((string)$programada);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $m)) {
        return $m[1];
    }
    return null;
};

$getProgramadaTime = static function (?string $programada): ?string {
    $raw = trim((string)$programada);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}[ T](\d{2}:\d{2})/', $raw, $m)) {
        return $m[1];
    }
    return null;
};

$formatProgramada = static function (?string $programada) use ($getProgramadaDate, $getProgramadaTime): string {
    $date = $getProgramadaDate($programada);
    if (!$date) {
        return '';
    }

    $time = $getProgramadaTime($programada);
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    $dateLabel = $dt ? $dt->format('d/m/Y') : $date;

    if ($time) {
        return $dateLabel . ' ' . $time;
    }

    return $dateLabel;
};

$columns = [
    ['id' => 'none', 'title' => 'Sem executante', 'executor_id' => null],
];
foreach ($executores as $ex) {
    $columns[] = [
        'id' => 'exec_' . (int)$ex['id'],
        'title' => $ex['name'],
        'executor_id' => (int)$ex['id'],
    ];
}

$cardsByColumn = [];
foreach ($columns as $col) {
    $cardsByColumn[$col['id']] = [];
}

foreach ($orders as $os) {
    $osDate = $getProgramadaDate($os['programada_para'] ?? null);
    if ($osDate && $osDate !== $agendaDate) {
        continue;
    }
    $cardCol = 'none';
    if (!empty($os['executor_id'])) {
        $candidate = 'exec_' . (int)$os['executor_id'];
        if (isset($cardsByColumn[$candidate])) {
            $cardCol = $candidate;
        }
    }
    $cardsByColumn[$cardCol][] = $os;
}

$weekStart = new DateTime($agendaDate);
$weekdayNum = (int)$weekStart->format('N');
$weekStart->modify('-' . ($weekdayNum - 1) . ' days');
$weekdayNames = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sab', 7 => 'Dom'];
$weekDays = [];
for ($i = 0; $i < 7; $i++) {
    $d = (clone $weekStart)->modify('+' . $i . ' days');
    $weekDays[] = [
        'date' => $d->format('Y-m-d'),
        'label' => $weekdayNames[(int)$d->format('N')] . ' ' . $d->format('d/m'),
    ];
}

$weekCardsByDate = ['sem_data' => []];
foreach ($weekDays as $w) {
    $weekCardsByDate[$w['date']] = [];
}
foreach ($orders as $os) {
    $d = $getProgramadaDate($os['programada_para'] ?? null);
    if ($d && isset($weekCardsByDate[$d])) {
        $weekCardsByDate[$d][] = $os;
    } else {
        $weekCardsByDate['sem_data'][] = $os;
    }
}
?>

<style>
.os-page-wrap { padding:0 6px 4px; margin-top:-12px; display:flex; flex-direction:column; min-height:0; }
html.os-agenda-lock,
html.os-agenda-lock body {
    height: 100%;
    overflow: hidden;
}
html.os-agenda-lock .os-page-wrap {
    height: calc(100dvh - 66px);
    overflow: hidden;
}
@media (max-width: 991px) {
    html.os-agenda-lock .os-page-wrap {
        height: calc(100dvh - 52px);
    }
}
.os-header-sticky { position:sticky; top:56px; z-index:25; }
.os-header-sticky .card-body { padding-top:0 !important; }
.os-header-sticky #os-filter-panel { margin-top:0 !important; }
.os-compact-card .card-body { padding:0 10px 8px; }
.os-toolbar { display:flex; align-items:center; justify-content:flex-start; margin-bottom:6px; }
.os-toolbar-title { margin:0; font-size:1.05rem; font-weight:700; color:#0f172a; }

.os-filterbar { display:flex; flex-direction:column; gap:8px; margin-top:0; }
 .os-filter-topline { display:flex; flex-wrap:wrap; align-items:center; justify-content:flex-start; gap:8px; width:100%; }

.os-filter-segments { display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
.os-head-actions { display:flex; align-items:center; gap:8px; margin-top:2px; }
.os-filter-bottomline { display:flex; flex-wrap:nowrap; align-items:center; gap:10px; min-width:0; }
.os-filter-clear { white-space:nowrap; border:1px solid #cbd5e1; background:#f8fafc; color:#334155; display:inline-flex; align-items:center; }
.os-search { flex:1 1 240px; min-width:200px; max-width:340px; }
.os-pill { padding:5px 10px; border-radius:8px; border:1px solid #d6deea; background:#f8fafc; color:#334155; font-size:0.82rem; line-height:1.2; text-decoration:none; white-space:nowrap; }
.os-pill.active { background:#0f172a; color:#fff; border-color:#0f172a; }
.os-pill-group { display:flex; align-items:center; justify-content:flex-start; gap:6px; flex-wrap:nowrap; flex:1 1 auto; min-width:0; overflow-x:auto; padding-bottom:2px; }
.os-chip { display:inline-flex; align-items:center; padding:0.2rem 0.65rem; border-radius:999px; font-size:0.85rem; }
.os-table thead th { padding:12px; }
.os-table tbody td { padding:14px 12px; vertical-align:middle; }
.os-table tbody tr + tr { border-top:1px solid #eef2f7; }

.os-view-switch { display:inline-flex; gap:4px; flex-wrap:nowrap; }
.os-view-switch .btn { min-width:96px; }
.os-mode-switch { display:inline-flex; gap:4px; flex-wrap:nowrap; }

.os-board-container {
    display:flex;
    flex-direction:column;
    min-height:0;
    flex:1 1 auto;
}
#os-agenda-view {
    display:flex;
    flex-direction:column;
    min-height:0;
    flex:1 1 auto;
}
#os-agenda-view .card {
    display:flex;
    flex-direction:column;
    min-height:0;
    flex:1 1 auto;
}
#os-agenda-view .card-body {
    display:flex;
    flex-direction:column;
    min-height:0;
    flex:1 1 auto;
    overflow:hidden;
}
#os-board-executor, #os-board-week {
    flex:1 1 auto;
    min-height:0;
    display:flex;
    flex-direction:column;
    overflow:hidden;
}
.os-board-wrap {
    flex:1 1 auto;
    min-height:0;
    display:flex;
    align-items:stretch;
    overflow-x:auto;
    overflow-y:hidden;
    border-top:1px solid #eef2f7;
    padding-top: 8px;
}
.os-board-shell {
    flex:1 1 auto;
    min-width:max-content;
    min-height:0;
    display:flex;
    flex-direction:column;
}
.os-board-heads {
    display:grid;
    gap:12px;
    min-width:max-content;
    margin-bottom:12px;
    flex:0 0 auto;
}
.os-board-scroll {
    flex:1 1 auto;
    min-height:0;
    overflow-y:auto;
    overflow-x:hidden;
}
.os-board-scroll .os-column-header {
    display:none;
}
.os-board-container {
    position: relative;
    flex:1 1 auto;
}
.os-board {
    display:grid;
    grid-template-columns: repeat(<?= max(2, count($columns)) ?>, minmax(270px, 1fr));
    gap:12px;
    min-width: max-content;
    min-height:0;
    align-items:start;
}
.os-week-board {
    display:grid;
    grid-template-columns: repeat(8, minmax(250px, 1fr));
    gap:12px;
    min-width: max-content;
    min-height:0;
    align-items:start;
}
.os-column {
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:14px;
    min-height:0;
    height:auto;
    display:flex;
    flex-direction:column;
    overflow:hidden;
}
.os-column-header {
    position: sticky;
    top: 0;
    z-index: 3;
    background:#f8fafc;
    padding:10px 12px;
    border-bottom:1px solid #e2e8f0;
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.os-column-title { font-weight:700; color:#1e293b; font-size:0.95rem; }
.os-column-count { font-size:0.8rem; color:#64748b; }
.os-dropzone {
    flex:1;
    padding:10px;
    display:flex;
    flex-direction:column;
    overflow:hidden;
    gap:10px;
}
.os-dropzone.drag-over {
    background:#e2e8f0;
    border-radius:12px;
}
.os-card {
    background:#fff;
    border:1px solid #dbe3ee;
    border-radius:12px;
    padding:10px;
    box-shadow:0 1px 2px rgba(15,23,42,.06);
    cursor:grab;
}
.os-card:active { cursor:grabbing; }
.os-card.dragging { opacity:0.55; }
.os-card-code { font-weight:700; color:#0f172a; }
.os-card-meta { color:#64748b; font-size:0.82rem; }
.os-empty {
    border:1px dashed #cbd5e1;
    border-radius:10px;
    padding:14px;
    color:#64748b;
    text-align:center;
    font-size:0.85rem;
    background:#ffffff8f;
}
.os-card-actions { display:flex; justify-content:space-between; align-items:center; gap:8px; }
.os-open-btn { font-weight:600; }
.os-mode-switch, .os-view-switch {
    background:#f1f5f9;
    border:1px solid #dbe4ef;
    border-radius:10px;
    padding:2px;
}
.os-mode-switch .btn, .os-view-switch .btn {
    border:0 !important;
    background:transparent !important;
    color:#334155 !important;
    border-radius:8px;
    padding:5px 10px;
    font-size:0.85rem;
    font-weight:600;
    box-shadow:none !important;
}
.os-mode-switch .btn.btn-primary, .os-view-switch .btn.btn-primary {
    background:#0f172a !important;
    color:#ffffff !important;
}
.os-filter-topline .form-select,
.os-filter-topline .form-control,
.os-filter-topline .btn {
    height:34px;
}
.os-filter-ctlline .form-select,
.os-filter-ctlline .form-control,
.os-filter-ctlline .btn {
    height:34px;
}
.os-filter-ctlline .form-select { min-width:170px; }
.os-filter-ctlline .form-control[type="date"] { min-width:150px; }
.os-apply-btn {
    background:#0f172a;
    border-color:#0f172a;
    color:#fff;
}
.os-apply-btn:hover { background:#1e293b; border-color:#1e293b; color:#fff; }
.os-filter-bottomline .os-filter-clear {
    height:34px;
    display:inline-flex;
    align-items:center;
}
.os-filter-panel { overflow-y:hidden; overflow-x:visible; transition:opacity .18s ease, margin-top .18s ease; opacity:1; margin-top:4px; }
.os-filter-panel.is-collapsed { display:none; }
.os-filter-toggle { min-width:120px; height:32px; font-size:0.85rem; }

.os-schedule-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    z-index: 1100;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 14px;
}
.os-schedule-modal-backdrop.open { display: flex; }
.os-schedule-modal {
    width: min(420px, 100%);
    background: #fff;
    border: 1px solid #dbe3ee;
    border-radius: 14px;
    box-shadow: 0 10px 35px rgba(15, 23, 42, 0.25);
    padding: 14px;
}
.os-schedule-title {
    margin: 0 0 10px;
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
}
.os-schedule-help {
    margin: 0 0 10px;
    font-size: 0.82rem;
    color: #64748b;
}
.os-schedule-label {
    display: block;
    margin-bottom: 8px;
    font-size: 0.82rem;
    color: #334155;
}
.os-schedule-input {
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    height: 36px;
    padding: 0 10px;
    font-size: 0.9rem;
    color: #0f172a;
    background: #fff;
}
.os-schedule-error {
    min-height: 18px;
    margin-top: 6px;
    color: #b91c1c;
    font-size: 0.78rem;
}
.os-schedule-actions {
    margin-top: 10px;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
@media (max-width: 1450px) {
  .os-filter-topline { width:100%; align-items:center; justify-content:flex-start; }
  .os-filter-segments { width:100%; }
  .os-toolbar { align-items:flex-start; }
  .os-bottom-right { width:auto; justify-content:flex-start; }
  .os-filterbar { align-items:flex-start; }
  .os-pill-group { width:100%; overflow-x:auto; flex-wrap:nowrap; padding-bottom:2px; }
  .os-pill { white-space:nowrap; }
}
@media (max-width: 991px) {
  .os-header-sticky { top:52px; }
  .os-filter-topline, .os-filter-bottomline { width:100%; }
  .os-filter-topline { flex-direction:column; align-items:stretch; }
  .os-mode-switch, .os-view-switch { width:100%; }
  .os-mode-switch .btn, .os-view-switch .btn { flex:1 1 auto; min-width:0; }
  .os-filter-toggle { width:100%; }
  .os-search { min-width:160px; flex:1 1 100%; }
  .os-pill-group { width:100%; overflow-x:auto; flex-wrap:nowrap; padding-bottom:2px; }
  .os-pill { white-space:nowrap; }
  .os-filterbar > * { max-width:100%; }
  .os-filter-clear { flex:0 0 auto; }
  .os-filter-bottomline { flex-direction:column; align-items:stretch; }
  #os-agenda-view .card-body { min-height:0; }
  .os-column { min-height:0; height:auto; }
}


/* os-header-layout */
.os-compact-card .card-body {
    padding: 0 10px 8px;
}

.os-page-actions {
    display: flex;
    width: 100%;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    padding: 0 0 2px;
    margin-top: -2px;
}


.os-title-row {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 12px;
    flex-wrap: wrap;
    padding-bottom: 6px;
    border-bottom: 1px solid #e5e7eb;
}
.os-title-left {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}
.os-title-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-left: auto;
}
.os-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.15;
}

.os-mini-nav {
    width: 40px;
    height: 40px;
    border: 1px solid #d5dde8;
    border-radius: 10px;
    background: #f8fafc;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}
.os-mini-nav span {
    display: block;
    width: 16px;
    height: 2px;
    background: #334155;
    border-radius: 2px;
}
.os-mini-nav span + span {
    margin-top: 3px;
}

.os-filterbar {
    margin-top: 4px;
    gap: 6px;
}
.os-filter-topline {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px 8px;
    width: 100%;
    justify-content: flex-start;
}
.os-filter-segments {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    flex: 0 0 auto;
}
.os-search {
    flex: 0 1 200px;
    min-width: 170px;
    max-width: 240px;
    padding-left: 8px;
    border-left: 1px solid #e2e8f0;
}
.os-filter-topline label {
    font-size: 0.85rem;
    color: #64748b;
    margin: 0 2px 0 0;
}

.os-filter-topline .form-select,
.os-filter-topline .form-control,
.os-filter-topline .btn,
.os-title-actions .btn,
.os-filter-toggle {
    height: 34px;
    min-height: 34px;
    font-size: 0.85rem;
    line-height: 1;
}
.os-filter-topline .form-select {
    min-width: 120px;
}
.os-filter-topline .form-control[type="date"] {
    min-width: 128px;
}
.os-filter-topline .form-select,
.os-filter-topline .form-control {
    padding-top: 0.3rem;
    padding-bottom: 0.3rem;
}

.os-apply-btn {
    background: #0f172a;
    border-color: #0f172a;
    color: #fff;
    padding-inline: 14px;
}
.os-apply-btn:hover {
    background: #1e293b;
    border-color: #1e293b;
    color: #fff;
}
.os-filter-clear {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #334155;
    padding-inline: 14px;
}
.os-filter-clear:hover {
    background: #f1f5f9;
    color: #1f2937;
}
.os-filter-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-left: 6px;
    flex: 0 0 auto;
}

.os-filter-bottomline {
    margin-top: 4px;
    padding-top: 4px;
    border-top: 1px solid #eef2f7;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 10px;
    flex-wrap: wrap;
}
.os-pill-group {
    flex: 0 0 auto;
    flex-wrap: wrap;
    overflow-x: visible;
    gap: 6px;
}

@media (max-width: 1400px) {
    .os-title-actions {
        margin-left: auto;
        width: 100%;
        justify-content: flex-start;
    }
    .os-filter-actions {
        margin-left: auto;
        width: 100%;
        justify-content: flex-start;
    }
    .os-search {
        border-left: 0;
        padding-left: 0;
        min-width: 100%;
        flex-basis: 100%;
    }
}
@media (max-width: 991px) {
    .os-title-row {
        align-items: flex-start;
    }
    .os-title-actions {
        width: 100%;
    }
    .os-filter-actions {
        width: 100%;
    }
    .os-filter-topline {
        align-items: stretch;
    }
    .os-search {
        max-width: 100%;
    }
    .os-filter-topline .form-select,
    .os-filter-topline .form-control,
    .os-filter-topline .btn,
    .os-title-actions .btn,
    .os-filter-actions .btn,
    .os-filter-toggle {
        width: 100%;
    }
}
/* /os-header-layout */</style>

<div class="container-fluid os-page-wrap">
    <div class="card shadow-sm border-0 os-compact-card mb-2 os-header-sticky">
        <div class="card-body">
            <div id="os-filter-panel" class="os-filter-panel">
            <form class="os-filterbar" method="get" action="index.php">
                <input type="hidden" name="page" value="os">

                <div class="os-filter-topline">
                    <div class="os-filter-segments">
                        <div class="os-mode-switch">
                            <button type="button" class="btn btn-sm btn-primary" data-board-mode="executor">Por executante</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-board-mode="week">Semana</button>
                        </div>
                        <div class="os-view-switch">
                            <button type="button" class="btn btn-sm btn-primary" data-view="agenda">Agenda</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-view="lista">Lista</button>
                        </div>
                    </div>
                    <div class="os-search">
                        <input class="form-control form-control-sm" name="q" placeholder="Buscar codigo ou placa" value="<?= sanitize($filters['q'] ?? '') ?>">
                    </div>
                    <select class="form-select form-select-sm" name="veiculo_id">
                        <option value="">Veiculo</option>
                        <?php foreach ($veiculos as $v): ?>
                            <option value="<?= sanitize($v['id']) ?>" <?= ($filters['veiculo_id'] ?? '') == $v['id'] ? 'selected' : '' ?>><?= sanitize($v['plate']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="small text-muted mb-0">Data</label>
                    <input class="form-control form-control-sm" type="date" name="agenda_date" value="<?= sanitize($agendaDate) ?>">
                    <button class="btn btn-sm os-apply-btn" type="submit">Aplicar</button>
                    <a class="btn btn-sm btn-light os-filter-clear" href="index.php?page=os">Limpar</a>
                </div>

                <div class="os-filter-bottomline">
                    <div class="os-pill-group">
                        <?php foreach (['' => 'Todos'] + $statusLabels as $key => $label): ?>
                            <button type="submit" name="status" value="<?= sanitize($key) ?>" class="os-pill <?= ($filters['status'] ?? '') === $key ? 'active' : '' ?>"><?= sanitize($label) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
            </div>
        </div>
    </div>

    <div id="os-agenda-view" class="card shadow-sm border-0 os-compact-card mb-2">
        <div class="card-body pb-1">
            <div id="os-board-executor" class="os-board-container">
                <div class="os-board-wrap">
                    
                    <div class="os-board-shell">
                        <div class="os-board-heads os-board" data-os-board-heads="executor"></div>
                        <div class="os-board-scroll">
                            <div class="os-board">
                        <?php foreach ($columns as $col): ?>
                            <?php $cards = $cardsByColumn[$col['id']] ?? []; ?>
                            <section class="os-column">
                                <div class="os-column-header">
                                    <div class="os-column-title"><?= sanitize($col['title']) ?></div>
                                    <div class="os-column-count" data-col-count="<?= sanitize($col['id']) ?>"><?= count($cards) ?> OS</div>
                                </div>
                                <div class="os-dropzone" data-dropzone="<?= sanitize($col['id']) ?>" data-executor-id="<?= sanitize((string)($col['executor_id'] ?? '')) ?>" data-programada="<?= sanitize($agendaDate) ?>">
                                    <?php if (!$cards): ?>
                                        <div class="os-empty">Sem OS nesta coluna.</div>
                                    <?php endif; ?>
                                    <?php foreach ($cards as $os): ?>
                                        <article class="os-card" draggable="true" data-os-id="<?= sanitize($os['id']) ?>" data-executor-id="<?= sanitize((string)($os['executor_id'] ?? '')) ?>" data-programada="<?= sanitize((string)($os['programada_para'] ?? '')) ?>">
                                            <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                                <div class="os-card-code"><?= sanitize($os['codigo']) ?></div>
                                                <span class="os-chip <?= $statusColors[$os['status'] ?? 'rascunho'] ?? '' ?>">
                                                    <?= sanitize($statusLabels[$os['status']] ?? $os['status']) ?>
                                                </span>
                                            </div>
                                            <div class="os-card-meta mb-1">Veiculo: <?= sanitize($os['vehicle_plate'] ?? '-') ?></div>
                                            <div class="os-card-meta mb-1">Aberta em: <?= sanitize(substr((string)($os['aberta_em'] ?? ''), 0, 10)) ?></div>
                                            <div class="os-card-meta mb-2" data-card-programada>Programada: <?= sanitize($formatProgramada($os['programada_para'] ?? null) ?: '-') ?></div>
                                            <div class="os-card-actions">
                                                <a class="btn btn-sm btn-outline-primary os-open-btn" href="index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=<?= sanitize($os['id']) ?>"><?= has_permission('os.manage') ? 'Abrir/Editar OS' : 'Abrir OS' ?></a>
                                                <small class="text-muted">SS: <?= sanitize($os['total_ss'] ?? 0) ?></small>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>                    </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="os-board-week" class="os-board-container" style="display:none;">
                <div class="os-board-wrap">
                    
                    <div class="os-board-shell">
                        <div class="os-board-heads os-week-board" data-os-board-heads="week"></div>
                        <div class="os-board-scroll">
                            <div class="os-week-board">
                        <?php foreach ($weekDays as $w): ?>
                            <?php $cards = $weekCardsByDate[$w['date']] ?? []; ?>
                            <section class="os-column">
                                <div class="os-column-header">
                                    <div class="os-column-title"><?= sanitize($w['label']) ?></div>
                                    <div class="os-column-count" data-col-count="week_<?= sanitize($w['date']) ?>"><?= count($cards) ?> OS</div>
                                </div>
                                <div class="os-dropzone" data-dropzone="week_<?= sanitize($w['date']) ?>" data-programada="<?= sanitize($w['date']) ?>" data-keep-executor="1">
                                    <?php if (!$cards): ?>
                                        <div class="os-empty">Sem OS neste dia.</div>
                                    <?php endif; ?>
                                    <?php foreach ($cards as $os): ?>
                                        <article class="os-card" draggable="true" data-os-id="<?= sanitize($os['id']) ?>" data-executor-id="<?= sanitize((string)($os['executor_id'] ?? '')) ?>" data-programada="<?= sanitize((string)($os['programada_para'] ?? '')) ?>">
                                            <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                                <div class="os-card-code"><?= sanitize($os['codigo']) ?></div>
                                                <span class="os-chip <?= $statusColors[$os['status'] ?? 'rascunho'] ?? '' ?>">
                                                    <?= sanitize($statusLabels[$os['status']] ?? $os['status']) ?>
                                                </span>
                                            </div>
                                            <div class="os-card-meta mb-1">Veiculo: <?= sanitize($os['vehicle_plate'] ?? '-') ?></div>
                                            <div class="os-card-meta mb-1" data-card-executor>Executor: <?= sanitize($os['executor_nome'] ?? 'Sem executante') ?></div>
                                            <div class="os-card-meta mb-2" data-card-programada>Programada: <?= sanitize($formatProgramada($os['programada_para'] ?? null) ?: '-') ?></div>
                                            <div class="os-card-actions">
                                                <a class="btn btn-sm btn-outline-primary os-open-btn" href="index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=<?= sanitize($os['id']) ?>"><?= has_permission('os.manage') ? 'Abrir/Editar OS' : 'Abrir OS' ?></a>
                                                <small class="text-muted">SS: <?= sanitize($os['total_ss'] ?? 0) ?></small>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>

                        <?php $semData = $weekCardsByDate['sem_data'] ?? []; ?>
                        <section class="os-column">
                            <div class="os-column-header">
                                <div class="os-column-title">Sem data</div>
                                <div class="os-column-count" data-col-count="week_sem_data"><?= count($semData) ?> OS</div>
                            </div>
                            <div class="os-dropzone" data-dropzone="week_sem_data" data-programada="" data-keep-executor="1">
                                <?php if (!$semData): ?>
                                    <div class="os-empty">Sem OS sem data.</div>
                                <?php endif; ?>
                                <?php foreach ($semData as $os): ?>
                                    <article class="os-card" draggable="true" data-os-id="<?= sanitize($os['id']) ?>" data-executor-id="<?= sanitize((string)($os['executor_id'] ?? '')) ?>" data-programada="<?= sanitize((string)($os['programada_para'] ?? '')) ?>">
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                            <div class="os-card-code"><?= sanitize($os['codigo']) ?></div>
                                            <span class="os-chip <?= $statusColors[$os['status'] ?? 'rascunho'] ?? '' ?>">
                                                <?= sanitize($statusLabels[$os['status']] ?? $os['status']) ?>
                                            </span>
                                        </div>
                                        <div class="os-card-meta mb-1">Veiculo: <?= sanitize($os['vehicle_plate'] ?? '-') ?></div>
                                        <div class="os-card-meta mb-1" data-card-executor>Executor: <?= sanitize($os['executor_nome'] ?? 'Sem executante') ?></div>
                                        <div class="os-card-meta mb-2" data-card-programada>Programada: <?= sanitize($formatProgramada($os['programada_para'] ?? null) ?: '-') ?></div>
                                        <div class="os-card-actions">
                                            <a class="btn btn-sm btn-outline-primary os-open-btn" href="index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=<?= sanitize($os['id']) ?>"><?= has_permission('os.manage') ? 'Abrir/Editar OS' : 'Abrir OS' ?></a>
                                            <small class="text-muted">SS: <?= sanitize($os['total_ss'] ?? 0) ?></small>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>                    </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="os-lista-view" class="card shadow-sm border-0 os-compact-card" style="display:none;">
        <div class="table-responsive">
            <table class="table align-middle mb-0 os-table">
                <thead class="table-light">
                    <tr>
                        <th>Codigo</th>
                        <th>Veiculo</th>
                        <th>Executante</th>
                        <th>Programada</th>
                        <th>Status</th>
                        <th>Aberta em</th>
                        <th>SS</th>
                        <th class="text-nowrap">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $os): ?>
                        <tr>
                            <td class="fw-semibold"><?= sanitize($os['codigo']) ?></td>
                            <td><?= sanitize($os['vehicle_plate'] ?? '') ?></td>
                            <td><?= sanitize($os['executor_nome'] ?? 'Sem executante') ?></td>
                            <td><?= sanitize($formatProgramada($os['programada_para'] ?? null)) ?></td>
                            <td><span class="os-chip <?= $statusColors[$os['status'] ?? 'rascunho'] ?? '' ?>"><?= sanitize($statusLabels[$os['status']] ?? $os['status']) ?></span></td>
                            <td><?= sanitize($os['aberta_em'] ?? '') ?></td>
                            <td><?= sanitize($os['total_ss'] ?? 0) ?></td>
                            <td><a class="btn btn-sm btn-outline-primary os-open-btn" href="index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=<?= sanitize($os['id']) ?>"><?= has_permission('os.manage') ? 'Abrir/Editar OS' : 'Abrir OS' ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$orders): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Nenhuma OS encontrada.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function() {
    const agendaView = document.getElementById('os-agenda-view');
    const listaView = document.getElementById('os-lista-view');
    const viewButtons = document.querySelectorAll('[data-view]');
    const modeButtons = document.querySelectorAll('[data-board-mode]');
    const boardExecutor = document.getElementById('os-board-executor');
    const boardWeek = document.getElementById('os-board-week');
    const filterPanel = document.getElementById('os-filter-panel');
    const filterToggle = document.getElementById('os-toggle-filters');
    const miniNav = document.getElementById('os-mini-nav');
    function setPageScrollLock(enabled) {
        document.documentElement.classList.toggle('os-agenda-lock', !!enabled);
        if (document.body) {
            document.body.classList.toggle('os-agenda-lock', !!enabled);
        }
    }
    function setView(view) {
        const isAgenda = view === 'agenda';
        agendaView.style.display = isAgenda ? '' : 'none';
        listaView.style.display = isAgenda ? 'none' : '';
        viewButtons.forEach((btn) => {
            const active = btn.dataset.view === view;
            btn.classList.toggle('btn-primary', active);
            btn.classList.toggle('btn-outline-secondary', !active);
        });
        setPageScrollLock(isAgenda);
    }

    function setMode(mode) {
        const isWeek = mode === 'week';
        boardExecutor.style.display = isWeek ? 'none' : '';
        boardWeek.style.display = isWeek ? '' : 'none';
        modeButtons.forEach((btn) => {
            const active = btn.dataset.boardMode === mode;
            btn.classList.toggle('btn-primary', active);
            btn.classList.toggle('btn-outline-secondary', !active);
        });
    }

    function setFilterPanel(collapsed) {
        if (!filterPanel || !filterToggle) {
            return;
        }
        filterPanel.classList.toggle('is-collapsed', collapsed);
        document.documentElement.classList.toggle('os-filters-collapsed', collapsed);
        filterToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        filterToggle.textContent = collapsed ? 'Mostrar filtros' : 'Ocultar filtros';
        try {
            localStorage.setItem('osFiltersCollapsed', collapsed ? '1' : '0');
        } catch (e) {
            /* ignore */
        }
    }

    function buildPinnedHeaders() {
        document.querySelectorAll('[data-os-board-heads]').forEach((heads) => {
            if (heads.dataset.headersBuilt === '1') {
                return;
            }

            const scroll = heads.nextElementSibling;
            const sourceBoard = scroll?.querySelector('.os-board, .os-week-board');
            if (!sourceBoard) {
                return;
            }

            heads.innerHTML = '';
            Array.from(sourceBoard.children).forEach((column) => {
                if (!column.classList || !column.classList.contains('os-column')) {
                    return;
                }
                const header = column.querySelector('.os-column-header');
                if (!header) {
                    return;
                }
                heads.appendChild(header.cloneNode(true));
            });
            heads.dataset.headersBuilt = '1';
        });
    }

    viewButtons.forEach((btn) => {
        btn.addEventListener('click', function() {
            setView(this.dataset.view || 'agenda');
        });
    });

    modeButtons.forEach((btn) => {
        btn.addEventListener('click', function() {
            setMode(this.dataset.boardMode || 'executor');
        });
    });

    setView('agenda');
    setMode('executor');
    buildPinnedHeaders();

    let storedFiltersCollapsed = false;
    try {
        storedFiltersCollapsed = localStorage.getItem('osFiltersCollapsed') === '1';
    } catch (e) {
        /* ignore */
    }
    setFilterPanel(storedFiltersCollapsed);

    miniNav?.addEventListener('click', function() {
        const nav = document.getElementById('nav-open-main');
        nav?.click();
    });

    filterToggle?.addEventListener('click', function() {
        const nextCollapsed = filterPanel ? !filterPanel.classList.contains('is-collapsed') : true;
        setFilterPanel(nextCollapsed);
    });
    let draggedCard = null;

    document.querySelectorAll('.os-card[draggable="true"]').forEach((card) => {
        card.addEventListener('dragstart', () => {
            draggedCard = card;
            card.classList.add('dragging');
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
            draggedCard = null;
        });
    });

    function refreshCounts() {
        document.querySelectorAll('[data-dropzone]').forEach((zone) => {
            const zoneId = zone.dataset.dropzone;
            const count = zone.querySelectorAll('.os-card').length;
            document.querySelectorAll('[data-col-count="' + zoneId + '"]').forEach((badge) => {
                badge.textContent = count + ' OS';
            });

            const existingEmpty = zone.querySelector('.os-empty');
            if (count === 0 && !existingEmpty) {
                const empty = document.createElement('div');
                empty.className = 'os-empty';
                empty.textContent = zone.dataset.dropzone.startsWith('week_') ? 'Sem OS neste dia.' : 'Sem OS nesta coluna.';
                if (zone.dataset.dropzone === 'week_sem_data') {
                    empty.textContent = 'Sem OS sem data.';
                }
                zone.appendChild(empty);
            } else if (count > 0 && existingEmpty) {
                existingEmpty.remove();
            }
        });
    }

    function normalizeScheduleValue(value) {
        const raw = (value || '').trim();
        if (!raw) {
            return '';
        }

        const isoMatch = raw.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/);
        if (isoMatch) {
            const hh = isoMatch[4] ?? '00';
            const mm = isoMatch[5] ?? '00';
            const ss = isoMatch[6] ?? '00';
            return `${isoMatch[1]}-${isoMatch[2]}-${isoMatch[3]} ${hh}:${mm}:${ss}`;
        }

        const brMatch = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?$/);
        if (brMatch) {
            const hh = brMatch[4] ?? '00';
            const mm = brMatch[5] ?? '00';
            const ss = brMatch[6] ?? '00';
            return `${brMatch[3]}-${brMatch[2]}-${brMatch[1]} ${hh}:${mm}:${ss}`;
        }

        return '';
    }

    function toScheduleLabel(value) {
        const normalized = normalizeScheduleValue(value);
        if (!normalized) {
            return '-';
        }

        const date = normalized.slice(0, 10);
        const time = normalized.slice(11, 16);
        const parts = date.split('-');
        if (parts.length === 3) {
            return `${parts[2]}/${parts[1]}/${parts[0]} ${time}`;
        }
        return `${date} ${time}`;
    }

    function refreshCardProgramada(card, programadaPara) {
        const labelEl = card.querySelector('[data-card-programada]');
        if (!labelEl) {
            return;
        }
        labelEl.textContent = 'Programada: ' + toScheduleLabel(programadaPara);
    }

    function refreshCardExecutor(card, executorNome) {
        const labelEl = card.querySelector('[data-card-executor]');
        if (!labelEl) {
            return;
        }
        labelEl.textContent = 'Executor: ' + (executorNome || 'Sem executante');
    }

    function syncOsCards(osId, payload) {
        document.querySelectorAll('.os-card[draggable="true"]').forEach((candidate) => {
            if ((candidate.dataset.osId || '') !== String(osId)) {
                return;
            }

            if (Object.prototype.hasOwnProperty.call(payload, 'executorId')) {
                candidate.dataset.executorId = payload.executorId || '';
            }
            if (Object.prototype.hasOwnProperty.call(payload, 'executorNome')) {
                candidate.dataset.executorNome = payload.executorNome || '';
                refreshCardExecutor(candidate, payload.executorNome || '');
            }
            if (Object.prototype.hasOwnProperty.call(payload, 'programadaPara')) {
                candidate.dataset.programada = payload.programadaPara || '';
                refreshCardProgramada(candidate, candidate.dataset.programada);
            }
        });
    }

    function defaultScheduleForExecutor(cardProgramada, zoneDate) {
        const normalized = normalizeScheduleValue(cardProgramada);
        if (normalized) {
            return normalized;
        }
        if (zoneDate) {
            return `${zoneDate} 08:00:00`;
        }
        return '';
    }

    function normalizeDateValue(value) {
        const raw = (value || '').trim();
        if (!raw) {
            return '';
        }

        const iso = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (iso) {
            return `${iso[1]}-${iso[2]}-${iso[3]}`;
        }

        const br = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (br) {
            return `${br[3]}-${br[2]}-${br[1]}`;
        }

        return '';
    }

    function normalizeTimeValue(value) {
        const raw = (value || '').trim();
        const match = raw.match(/^(\d{2}):(\d{2})$/);
        if (!match) {
            return '';
        }

        const hh = Number(match[1]);
        const mm = Number(match[2]);
        if (hh < 0 || hh > 23 || mm < 0 || mm > 59) {
            return '';
        }

        return `${match[1]}:${match[2]}`;
    }

    function toPromptDateValue(value) {
        const normalized = normalizeScheduleValue(value);
        if (normalized) {
            return `${normalized.slice(8, 10)}/${normalized.slice(5, 7)}/${normalized.slice(0, 4)}`;
        }

        const dateOnly = normalizeDateValue(value);
        if (!dateOnly) {
            return '';
        }

        return `${dateOnly.slice(8, 10)}/${dateOnly.slice(5, 7)}/${dateOnly.slice(0, 4)}`;
    }

    function toPromptTimeValue(value, fallback = '08:00') {
        const normalized = normalizeScheduleValue(value);
        if (normalized) {
            return normalized.slice(11, 16);
        }

        const time = normalizeTimeValue(value);
        return time || fallback;
    }

    function buildScheduleValue(dateIso, hourMinute) {
        return `${dateIso} ${hourMinute}:00`;
    }

    function maskHourText(value) {
        const digits = (value || '').replace(/\D/g, '').slice(0, 4);
        if (digits.length <= 2) {
            return digits;
        }
        return `${digits.slice(0, 2)}:${digits.slice(2)}`;
    }

    function createScheduleDialog() {
        const backdrop = document.createElement('div');
        backdrop.className = 'os-schedule-modal-backdrop';
        backdrop.innerHTML = `
            <div class="os-schedule-modal" role="dialog" aria-modal="true" aria-label="Programar execucao">
                <h3 class="os-schedule-title" data-os-schedule-title></h3>
                <p class="os-schedule-help" data-os-schedule-help></p>
                <label class="os-schedule-label" data-os-schedule-date-wrap>
                    Data (DD/MM/AAAA)
                    <input type="text" class="os-schedule-input" data-os-schedule-date placeholder="DD/MM/AAAA" autocomplete="off">
                </label>
                <div class="os-schedule-label" data-os-schedule-fixed-wrap style="display:none;">
                    Data
                    <div class="os-schedule-input" data-os-schedule-fixed style="display:flex; align-items:center; background:#f8fafc;"></div>
                </div>
                <label class="os-schedule-label">
                    Hora (HH:MM)
                    <input type="text" class="os-schedule-input" data-os-schedule-hour placeholder="HH:MM" maxlength="5" inputmode="numeric" autocomplete="off">
                </label>
                <div class="os-schedule-error" data-os-schedule-error></div>
                <div class="os-schedule-actions">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-os-schedule-cancelar>Cancelar</button>
                    <button type="button" class="btn btn-sm btn-primary" data-os-schedule-salvar>Salvar</button>
                </div>
            </div>
        `;
        document.body.appendChild(backdrop);

        const titleEl = backdrop.querySelector('[data-os-schedule-title]');
        const helpEl = backdrop.querySelector('[data-os-schedule-help]');
        const dateWrap = backdrop.querySelector('[data-os-schedule-date-wrap]');
        const dateInput = backdrop.querySelector('[data-os-schedule-date]');
        const fixedWrap = backdrop.querySelector('[data-os-schedule-fixed-wrap]');
        const fixedEl = backdrop.querySelector('[data-os-schedule-fixed]');
        const hourInput = backdrop.querySelector('[data-os-schedule-hour]');
        const errorEl = backdrop.querySelector('[data-os-schedule-error]');
        const cancelBtn = backdrop.querySelector('[data-os-schedule-cancelar]');
        const saveBtn = backdrop.querySelector('[data-os-schedule-salvar]');

        let resolver = null;
        let modalState = { askDate: true, fixedDate: '' };

        function close(result) {
            if (!resolver) {
                return;
            }
            const done = resolver;
            resolver = null;
            backdrop.classList.remove('open');
            document.removeEventListener('keydown', onKeyDown);
            done(result);
        }

        function onKeyDown(e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                close(null);
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                saveBtn.click();
            }
        }

        hourInput.addEventListener('input', () => {
            hourInput.value = maskHourText(hourInput.value);
        });

        cancelBtn.addEventListener('click', () => close(null));
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) {
                close(null);
            }
        });

        saveBtn.addEventListener('click', () => {
            errorEl.textContent = '';

            let dateIso = modalState.fixedDate || '';
            if (modalState.askDate) {
                dateIso = normalizeDateValue(dateInput.value);
                if (!dateIso) {
                    errorEl.textContent = 'Informe a data no formato DD/MM/AAAA.';
                    dateInput.focus();
                    return;
                }
            }

            const time = normalizeTimeValue(hourInput.value);
            if (!time) {
                errorEl.textContent = 'Informe a hora no formato HH:MM (somente numeros).';
                hourInput.focus();
                return;
            }

            close({ dateIso, time });
        });

        return {
            open(options) {
                const opts = options || {};
                modalState = {
                    askDate: !!opts.askDate,
                    fixedDate: normalizeDateValue(opts.fixedDate || ''),
                };

                titleEl.textContent = opts.title || 'Programar execucao';
                helpEl.textContent = opts.help || '';
                errorEl.textContent = '';

                if (modalState.askDate) {
                    dateWrap.style.display = '';
                    fixedWrap.style.display = 'none';
                    dateInput.value = opts.date || '';
                } else {
                    dateWrap.style.display = 'none';
                    fixedWrap.style.display = '';
                    fixedEl.textContent = toPromptDateValue(modalState.fixedDate);
                    dateInput.value = '';
                }

                hourInput.value = maskHourText(opts.time || '08:00');

                backdrop.classList.add('open');
                document.addEventListener('keydown', onKeyDown);
                if (modalState.askDate) {
                    dateInput.focus();
                } else {
                    hourInput.focus();
                }

                return new Promise((resolve) => {
                    resolver = resolve;
                });
            },
        };
    }

    const scheduleDialog = createScheduleDialog();

    function getAssignExecutorUrl() {
        const url = new URL(window.location.href);
        url.search = 'mod=manutencao&ctrl=OrdensServico&action=assignExecutor';
        return url.toString();
    }

    async function persistSchedule(osId, executorId, programadaPara) {
        const body = new URLSearchParams();
        body.append('os_id', osId);
        if (executorId) {
            body.append('executor_id', executorId);
        }
        if (programadaPara !== undefined) {
            body.append('programada_para', programadaPara);
        }

        const res = await fetch(getAssignExecutorUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        });

        const contentType = (res.headers.get('content-type') || '').toLowerCase();
        let data = null;
        let raw = '';

        if (contentType.includes('application/json')) {
            data = await res.json();
        } else {
            raw = await res.text();
            try {
                data = JSON.parse(raw);
            } catch (e) {
                data = null;
            }
        }

        if (!res.ok) {
            const httpMessage = data && data.message
                ? data.message
                : (raw ? ('Resposta inesperada do servidor: ' + raw.slice(0, 180)) : 'Falha ao salvar');
            throw new Error(httpMessage);
        }
        if (!data || !data.ok) {
            throw new Error((data && data.message) ? data.message : 'Erro ao salvar');
        }

        return data;
    }

    document.querySelectorAll('[data-dropzone]').forEach((zone) => {
        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            zone.classList.add('drag-over');
        });

        zone.addEventListener('dragleave', () => {
            zone.classList.remove('drag-over');
        });

        zone.addEventListener('drop', async (e) => {
            e.preventDefault();
            zone.classList.remove('drag-over');
            if (!draggedCard) {
                return;
            }

            const card = draggedCard;
            const oldZone = card.closest('[data-dropzone]');
            if (!oldZone || oldZone === zone) {
                return;
            }

            const osId = card.dataset.osId;
            const previousExecutorId = card.dataset.executorId || '';
            const previousProgramada = card.dataset.programada || '';

            let executorId = zone.dataset.executorId || '';
            let programadaPara = zone.dataset.programada || '';

            if (zone.dataset.keepExecutor === '1') {
                executorId = previousExecutorId;
                const targetDate = normalizeDateValue(zone.dataset.programada || '');
                if (!targetDate) {
                    programadaPara = '';
                } else {
                    const modalHour = await scheduleDialog.open({
                        askDate: false,
                        fixedDate: targetDate,
                        time: toPromptTimeValue(previousProgramada, '08:00'),
                        title: 'Troca de data da execucao',
                        help: 'Data definida pela coluna de destino. Informe a hora de execucao.',
                    });
                    if (!modalHour) {
                        return;
                    }

                    programadaPara = buildScheduleValue(targetDate, modalHour.time);
                }
            } else if (executorId) {
                const suggested = defaultScheduleForExecutor(previousProgramada, zone.dataset.programada || '');
                const modalSchedule = await scheduleDialog.open({
                    askDate: true,
                    date: toPromptDateValue(suggested),
                    time: toPromptTimeValue(suggested, '08:00'),
                    title: 'Troca de executante',
                    help: 'Defina data e hora para a execucao com o novo executante.',
                });
                if (!modalSchedule) {
                    return;
                }

                programadaPara = buildScheduleValue(modalSchedule.dateIso, modalSchedule.time);
            } else {
                programadaPara = normalizeScheduleValue(previousProgramada);
            }

            zone.appendChild(card);
            refreshCounts();

            try {
                const resp = await persistSchedule(osId, executorId, programadaPara);
                const savedExecutor = (resp.executor_id !== null && resp.executor_id !== undefined) ? String(resp.executor_id) : (executorId || '');
                syncOsCards(osId, {
                    executorId: savedExecutor,
                    executorNome: resp.executor_nome || '',
                    programadaPara: resp.programada_para || programadaPara || '',
                });
            } catch (err) {
                oldZone.appendChild(card);
                refreshCounts();
                const msg = (err && err.message) ? err.message : 'Nao foi possivel salvar o planejamento da OS.';
                window.alert(msg);
            }
        });
    });
})();
</script>




















