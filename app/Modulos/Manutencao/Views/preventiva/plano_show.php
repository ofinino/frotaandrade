<?php $plan = $plan ?? []; $tasks = $plan['tasks'] ?? []; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Plano <?= sanitize($plan['nome'] ?? '') ?></h3>
        <a class="btn btn-light" href="index.php?page=planos_preventiva">Voltar</a>
    </div>
    <div class="card mb-3">
        <div class="card-body">
            <p><strong>Veiculo:</strong> <?= sanitize($plan['vehicle_plate'] ?? '') ?></p>
            <p><strong>Tipo:</strong> <?= sanitize($plan['tipo'] ?? '') ?></p>
            <p><strong>Intervalos:</strong> <?= sanitize($plan['km_intervalo']) ?> km / <?= sanitize($plan['dias_intervalo']) ?> dias</p>
            <p><strong>Descricao:</strong><br><?= nl2br(sanitize($plan['descricao'] ?? '')) ?></p>
        </div>
    </div>
    <div class="card">
        <div class="card-header">Tarefas</div>
        <div class="card-body">
            <?php foreach ($tasks as $t): ?>
                <div class="border rounded p-2 mb-2">
                    <div><strong><?= sanitize($t['nome']) ?></strong></div>
                    <div class="text-muted small"><?= nl2br(sanitize($t['descricao'])) ?></div>
                    <div class="small">KM: <?= sanitize($t['km_intervalo']) ?> | Dias: <?= sanitize($t['dias_intervalo']) ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$tasks): ?><div class="text-muted">Nenhuma tarefa.</div><?php endif; ?>
        </div>
    </div>
</div>
