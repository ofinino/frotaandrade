<?php
$plan = $plan ?? null;
$tasks = $plan['tasks'] ?? [];
?>

<style>
.prev-form-card { border:1px solid #eef2f7; border-radius:12px; box-shadow:0 6px 14px rgba(15,23,42,0.06); }
.prev-section-title { font-size:1rem; font-weight:600; color:#0f172a; margin-bottom:6px; }
.task-card { border:1px solid #e2e8f0; border-radius:10px; padding:12px; margin-bottom:12px; background:#f8fafc; }
.task-card input, .task-card textarea { background:#fff; }
.pill-badge { display:inline-flex; align-items:center; padding:4px 10px; border-radius:999px; font-size:0.82rem; background:#e2e8f0; color:#334155; }
.form-hint { font-size:0.85rem; color:#6b7280; }
</style>

<div class="container py-4">
    <div class="card prev-form-card border-0">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <div>
                    <div class="pill-badge mb-2"><?= $plan ? 'Editar plano' : 'Novo plano' ?></div>
                    <h5 class="mb-1"><?= $plan ? sanitize($plan['nome']) : 'Criar plano de preventiva' ?></h5>
                    <div class="text-muted small">Defina intervalos de KM/tempo e tarefas para gerar SS automáticas.</div>
                </div>
                <div>
                    <a class="btn btn-outline-secondary btn-sm" href="index.php?page=planos_preventiva">Voltar</a>
                </div>
            </div>

            <form method="post" action="index.php?page=planos_preventiva&action=save" class="row g-3">
                <input type="hidden" name="id" value="<?= sanitize($plan['id'] ?? '') ?>">

                <div class="col-md-6">
                    <label class="form-label">Nome do plano</label>
                    <input class="form-control" name="nome" value="<?= sanitize($plan['nome'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Veículo (ID)</label>
                    <input class="form-control" name="veiculo_id" value="<?= sanitize($plan['veiculo_id'] ?? '') ?>" placeholder="ID do veículo">
                    <div class="form-hint">Use a placa/ID existente.</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Tipo</label>
                    <select class="form-select" name="tipo">
                        <?php foreach (['km'=>'Por KM','tempo'=>'Por tempo','km_tempo'=>'KM + tempo'] as $val => $label): ?>
                            <option value="<?= $val ?>" <?= ($plan['tipo'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Intervalo KM</label>
                    <input class="form-control" name="km_intervalo" type="number" value="<?= sanitize($plan['km_intervalo'] ?? '') ?>" placeholder="ex.: 10000">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Intervalo dias</label>
                    <input class="form-control" name="dias_intervalo" type="number" value="<?= sanitize($plan['dias_intervalo'] ?? '') ?>" placeholder="ex.: 180">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Due soon KM</label>
                    <input class="form-control" name="due_soon_km" type="number" value="<?= sanitize($plan['due_soon_km'] ?? 0) ?>" placeholder="ex.: 1000">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Due soon dias</label>
                    <input class="form-control" name="due_soon_dias" type="number" value="<?= sanitize($plan['due_soon_dias'] ?? 0) ?>" placeholder="ex.: 7">
                </div>

                <div class="col-12">
                    <label class="form-label">Descrição</label>
                    <textarea class="form-control" name="descricao" rows="3" placeholder="Observações gerais do plano"><?= sanitize($plan['descricao'] ?? '') ?></textarea>
                </div>

                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="prev-section-title">Tarefas</span>
                    </div>
                    <div id="tasks-wrap">
                        <?php $i = 0; foreach ($tasks as $t): ?>
                            <div class="task-card">
                                <input type="hidden" name="tasks[<?= $i ?>][id]" value="<?= sanitize($t['id']) ?>">
                                <input class="form-control mb-2" name="tasks[<?= $i ?>][nome]" value="<?= sanitize($t['nome']) ?>" placeholder="Nome da tarefa">
                                <textarea class="form-control mb-2" name="tasks[<?= $i ?>][descricao]" rows="2" placeholder="Descrição"><?= sanitize($t['descricao']) ?></textarea>
                                <div class="row g-2">
                                    <div class="col-md-6"><input class="form-control" type="number" name="tasks[<?= $i ?>][km_intervalo]" value="<?= sanitize($t['km_intervalo']) ?>" placeholder="Km"></div>
                                    <div class="col-md-6"><input class="form-control" type="number" name="tasks[<?= $i ?>][dias_intervalo]" value="<?= sanitize($t['dias_intervalo']) ?>" placeholder="Dias"></div>
                                </div>
                            </div>
                        <?php $i++; endforeach; ?>
                        <div class="task-card">
                            <input class="form-control mb-2" name="tasks[<?= $i ?>][nome]" placeholder="Nome da tarefa">
                            <textarea class="form-control mb-2" name="tasks[<?= $i ?>][descricao]" rows="2" placeholder="Descrição"></textarea>
                            <div class="row g-2">
                                <div class="col-md-6"><input class="form-control" type="number" name="tasks[<?= $i ?>][km_intervalo]" placeholder="Km"></div>
                                <div class="col-md-6"><input class="form-control" type="number" name="tasks[<?= $i ?>][dias_intervalo]" placeholder="Dias"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 form-check">
                    <input class="form-check-input" type="checkbox" name="ativo" id="ativo" <?= ($plan['ativo'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="ativo">Ativo</label>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary">Salvar</button>
                    <a class="btn btn-light" href="index.php?page=planos_preventiva">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
