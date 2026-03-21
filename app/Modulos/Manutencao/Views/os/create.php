<?php
$veiculos = $veiculos ?? [];
$ssList = $ssList ?? [];
?>
<div class="container py-4">
    <h3>Nova Ordem de Servico</h3>
    <form method="post" action="index.php?mod=manutencao&ctrl=OrdensServico&action=store" class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Veiculo</label>
            <select class="form-select" name="veiculo_id">
                <?php foreach ($veiculos as $v): ?>
                    <option value="<?= sanitize($v['id']) ?>"><?= sanitize($v['plate']) ?> <?= sanitize($v['model']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
                <?php foreach (['aprovada','programada','em_execucao','aguardando_pecas','concluida'] as $s): ?>
                    <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Odometro abertura</label>
            <input class="form-control" name="odometro_abertura" type="number">
        </div>
        <div class="col-12">
            <label class="form-label">Observacoes</label>
            <textarea class="form-control" name="observacoes" rows="3"></textarea>
        </div>
        <div class="col-12">
            <label class="form-label">Vincular SS (opcional)</label>
            <select class="form-select" multiple name="ss_ids[]">
                <?php foreach ($ssList as $ss): ?>
                    <option value="<?= sanitize($ss['id']) ?>">#<?= sanitize($ss['id']) ?> - <?= sanitize($ss['titulo']) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">Segure CTRL para selecionar multiplas.</small>
        </div>
        <div class="col-12">
            <button class="btn btn-primary">Criar OS</button>
            <a class="btn btn-light" href="index.php?page=os">Cancelar</a>
        </div>
    </form>
</div>
