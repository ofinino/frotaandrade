<?php
$ss = $ss ?? [];
$attachments = $attachments ?? [];
$timeline = $timeline ?? [];
$osList = $osList ?? [];
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-0">SS #<?= sanitize($ss['id']) ?> - <?= sanitize($ss['titulo']) ?></h3>
            <div class="text-muted"><?= ucfirst(sanitize($ss['status'])) ?> • <?= sanitize($ss['vehicle_plate'] ?? '') ?></div>
        </div>
        <a class="btn btn-light" href="index.php?page=servicos">Voltar</a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <p><strong>Prioridade:</strong> <?= ucfirst(sanitize($ss['prioridade'])) ?></p>
            <p><strong>Origem:</strong> <?= sanitize($ss['source_type']) ?> <?= sanitize($ss['source_table'] ?? '') ?></p>
            <p><strong>Descricao:</strong><br><?= nl2br(sanitize($ss['descricao'])) ?></p>
        </div>
    </div>

    <?php if (has_permission('ss.manage')): ?>
    <div class="card mb-3">
        <div class="card-header">Acoes</div>
        <div class="card-body d-flex gap-2 flex-wrap">
            <form method="post" action="index.php?page=servicos">
                <input type="hidden" name="convert_ss" value="<?= sanitize($ss['id']) ?>">
                <input class="form-control mb-2" name="obs_os" placeholder="Obs OS (opcional)">
                <button class="btn btn-primary">Converter em OS</button>
            </form>
            <form method="post" action="index.php?page=servicos">
                <input type="hidden" name="link_ss" value="1">
                <input type="hidden" name="ss_id" value="<?= sanitize($ss['id']) ?>">
                <select class="form-select mb-2" name="os_id">
                    <?php foreach ($osList as $os): ?>
                        <option value="<?= sanitize($os['id']) ?>"><?= sanitize($os['codigo']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-secondary">Vincular OS</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-header">Anexos</div>
        <div class="card-body">
            <div class="row g-2">
                <?php foreach ($attachments as $at): ?>
                    <div class="col-md-3">
                        <a href="<?= asset_url($at['file_path']) ?>" target="_blank">
                            <img class="img-fluid rounded" src="<?= asset_url($at['file_path']) ?>" alt="<?= sanitize($at['original_name']) ?>">
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (has_permission('ss.manage')): ?>
                <form class="mt-3" method="post" action="index.php?page=servicos&action=show&id=<?= sanitize($ss['id']) ?>" enctype="multipart/form-data">
                    <label class="form-label">Adicionar anexos</label>
                    <input class="form-control mb-2" type="file" name="anexos[]" multiple accept="image/*">
                    <button class="btn btn-outline-primary">Enviar</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Timeline</div>
        <div class="card-body">
            <?php foreach ($timeline as $log): ?>
                <div class="mb-2">
                    <strong><?= sanitize($log['action']) ?></strong>
                    <span class="text-muted"><?= sanitize($log['created_at']) ?></span>
                    <?php if ($log['after_json']): ?>
                        <pre class="small bg-light p-2"><?= sanitize($log['after_json']) ?></pre>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$timeline): ?>
                <div class="text-muted">Nenhum evento.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
