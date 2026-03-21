<?php
$groups = $groups ?? [];
?>

<div class="container py-4">
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <h5 class="mb-2">Limpeza de dados (ambiente de desenvolvimento)</h5>
            <p class="text-muted mb-3 small">
                Use este formulário para apagar dados de teste. Pessoas, usuários, empresa, filiais e acessos não serão removidos.
                Digite <strong>LIMPAR</strong> para confirmar.
            </p>
            <form method="post" action="index.php?page=cleanup" class="d-flex flex-column gap-3">
                <div class="row g-2">
                    <?php foreach ($groups as $key => $g): ?>
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <label class="form-check-label fw-semibold d-flex align-items-start gap-2">
                                    <input type="checkbox" class="form-check-input mt-1" name="groups[<?= sanitize($key) ?>]" value="1">
                                    <span><?= sanitize($g['label']) ?></span>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div>
                    <label class="form-label">Confirmação</label>
                    <input type="text" name="confirm" class="form-control" placeholder="Digite LIMPAR para confirmar">
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-danger" type="submit" onclick="return confirm('Apagar dados selecionados?');">Apagar selecionados</button>
                    <a class="btn btn-light" href="index.php">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
