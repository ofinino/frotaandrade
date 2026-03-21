<?php $veiculos = $veiculos ?? []; ?>
<div class="container py-4">
    <h3>Nova SS manual</h3>
    <form method="post" action="index.php?page=servicos" enctype="multipart/form-data" class="row g-3">
        <input type="hidden" name="create_ss" value="1">
        <div class="col-12">
            <label class="form-label">Titulo</label>
            <input class="form-control" name="titulo" required>
        </div>
        <div class="col-12">
            <label class="form-label">Descricao</label>
            <textarea class="form-control" name="descricao" rows="3"></textarea>
        </div>
        <div class="col-md-4">
            <label class="form-label">Prioridade</label>
            <select class="form-select" name="prioridade">
                <?php foreach (['baixa','media','alta','critica'] as $p): ?>
                    <option value="<?= $p ?>"><?= ucfirst($p) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Veiculo</label>
            <select class="form-select" name="veiculo_id">
                <option value="">Selecione</option>
                <?php foreach ($veiculos as $v): ?>
                    <option value="<?= sanitize($v['id']) ?>"><?= sanitize($v['plate']) ?> <?= sanitize($v['model']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label">Anexos (imagens)</label>
            <input class="form-control" type="file" name="anexos[]" multiple accept="image/*">
        </div>
        <div class="col-12">
            <button class="btn btn-primary">Criar</button>
            <a class="btn btn-light" href="index.php?page=servicos">Cancelar</a>
        </div>
    </form>
</div>
