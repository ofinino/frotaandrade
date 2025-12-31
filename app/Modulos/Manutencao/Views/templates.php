<?php
// View de modelos de checklist (com revisão).
// Espera: $templates, $fieldsByTemplate, $groups, $editingTemplate, $editingFields.
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="bg-white border border-slate-100 shadow rounded-lg p-4">
        <div class="flex items-start justify-between mb-3">
            <div>
                <div class="font-semibold text-slate-900"><?= !empty($editingTemplate) ? 'Revisar modelo' : 'Novo modelo de checklist' ?></div>
                <p class="text-xs text-slate-500">Campos sempre em texto longo</p>
            </div>
            <button type="submit" form="template-form" class="hidden md:inline-flex items-center bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded">
                Salvar modelo
            </button>
        </div>
        <form method="post" class="space-y-3" id="template-form">
            <?php if (!empty($editingTemplate)): ?>
                <input type="hidden" name="edit_id" value="<?= (int)$editingTemplate['id'] ?>" />
            <?php endif; ?>
            <div>
                <label class="block text-sm text-slate-600 mb-1">Grupo (veículo, máquina, obra...)</label>
                <select name="grupo_id" class="w-full border border-slate-200 rounded px-3 py-2" required>
                    <option value="">Selecione</option>
                    <?php if (!empty($groups)): ?>
                        <?php foreach ($groups as $g): ?>
                            <option value="<?= $g['id'] ?>" <?= (!empty($editingTemplate['grupo_id']) && (int)$editingTemplate['grupo_id'] === (int)$g['id']) ? 'selected' : '' ?>>
                                <?= sanitize($g['nome']) ?> <?= !empty($g['tipo']) ? '(' . sanitize($g['tipo']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm text-slate-600 mb-1">Nome</label>
                <input class="w-full border border-slate-200 rounded px-3 py-2" name="name" required value="<?= !empty($editingTemplate) ? sanitize($editingTemplate['name']) : '' ?>" />
            </div>
            <div>
                <label class="block text-sm text-slate-600 mb-1">Descrição</label>
                <textarea class="w-full border border-slate-200 rounded px-3 py-2" name="description" rows="3"><?= !empty($editingTemplate['description']) ? sanitize($editingTemplate['description']) : '' ?></textarea>
            </div>
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <div class="font-semibold text-slate-800">Campos (sempre texto longo)</div>
                    <button type="button" id="add-field" class="text-amber-600 text-sm">+ campo</button>
                </div>
                <div id="fields" class="space-y-2"></div>
            </div>
            <div class="flex flex-col sm:flex-row gap-2">
                <?php if (!empty($editingTemplate)): ?>
                    <a class="w-full sm:w-auto text-center bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold px-4 py-2 rounded" href="index.php?page=templates">Cancelar revisão</a>
                <?php endif; ?>
                <button type="submit" class="md:hidden w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded">Salvar modelo</button>
            </div>
        </form>
    </div>
    <div class="lg:col-span-2 space-y-3">
        <?php if (empty($templates)): ?>
            <div class="bg-white border border-slate-100 shadow rounded-lg p-4 text-sm text-slate-600">
                Nenhum modelo cadastrado ainda.
            </div>
        <?php endif; ?>
        <?php foreach ($templates as $tpl): ?>
            <div class="bg-white border border-slate-100 shadow rounded-lg p-4">
                <div class="flex items-start justify-between">
                    <?php $countExec = $usageCounts[$tpl['id']] ?? 0; $inativo = ($tpl['status'] ?? 'ativo') === 'inativo'; ?>
                    <div>
                        <div class="flex items-center gap-2">
                            <div class="text-lg font-semibold text-slate-900"><?= sanitize($tpl['name']) ?></div>
                            <?php if ($inativo): ?>
                                <span class="text-xs px-2 py-1 rounded bg-slate-200 text-slate-700">Inativo</span>
                            <?php endif; ?>
                            <?php if ($countExec > 0): ?>
                                <span class="text-xs px-2 py-1 rounded bg-amber-100 text-amber-800"><?= $countExec ?> execuções</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($tpl['grupo_nome'])): ?>
                            <div class="text-xs text-slate-500">Grupo: <?= sanitize($tpl['grupo_nome']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($tpl['versao'])): ?>
                            <div class="text-xs text-slate-500"><?= sanitize($tpl['versao']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($tpl['description'])): ?>
                            <p class="text-sm text-slate-600 whitespace-pre-line mt-1"><?= sanitize($tpl['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="text-sm text-right space-x-2">
                        <a class="text-blue-600" href="index.php?page=templates&revise=<?= (int)$tpl['id'] ?>">Revisar</a>
                        <?php if ($inativo): ?>
                            <a class="text-emerald-600" href="index.php?page=templates&activate=<?= (int)$tpl['id'] ?>">Ativar</a>
                        <?php else: ?>
                            <a class="text-amber-600" href="index.php?page=templates&deactivate=<?= (int)$tpl['id'] ?>" onclick="return confirm('Inativar modelo? Não aparecerá em novas execuções.');">Inativar</a>
                        <?php endif; ?>
                        <?php if ($countExec === 0): ?>
                            <a class="text-rose-600" href="index.php?page=templates&delete=<?= (int)$tpl['id'] ?>" onclick="return confirm('Remover modelo? Todos os campos e respostas serão apagados.');">Excluir</a>
                        <?php else: ?>
                            <span class="text-slate-400 cursor-not-allowed" title="Possui execuções e não pode ser excluído">Excluir</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mt-3 text-sm text-slate-700">
                    <?php if (!empty($fieldsByTemplate[$tpl['id']])): ?>
                        <div class="flex items-center justify-between mb-2">
                            <div class="font-semibold text-slate-800">Campos</div>
                            <button type="button" class="toggle-fields text-amber-600 text-xs" data-target="fields-<?= (int)$tpl['id'] ?>">Mostrar</button>
                        </div>
                        <div id="fields-<?= (int)$tpl['id'] ?>" class="field-list hidden">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <?php foreach ($fieldsByTemplate[$tpl['id']] as $field): ?>
                                    <div class="border border-slate-100 rounded px-3 py-2">
                                        <?php $num = isset($field['item_num']) ? (int)$field['item_num'] : ((int)($field['position'] ?? 0) + 1); ?>
                                        <div class="font-semibold"><?= $num ?> - <?= sanitize($field['label']) ?></div>
                                        <div class="text-xs text-slate-500">
                                            Texto longo padrão<?= ($field['required'] ?? 0) ? ' | obrigatório' : '' ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-slate-500">Nenhum campo configurado.</p>
                    <?php endif; ?>
                </div>
                <?php if (!empty($revisionLogsByTemplate[$tpl['id']])): ?>
                    <div class="mt-4 border-t border-slate-100 pt-3 text-sm">
                        <div class="flex items-center justify-between">
                            <div class="font-semibold text-slate-800">Histórico de revisões</div>
                            <button type="button" class="toggle-history text-amber-600 text-xs" data-target="rev-<?= (int)$tpl['id'] ?>">Mostrar</button>
                        </div>
                        <div id="rev-<?= (int)$tpl['id'] ?>" class="revision-list hidden mt-2 space-y-2">
                            <?php foreach ($revisionLogsByTemplate[$tpl['id']] as $log): ?>
                                <div class="border border-slate-100 rounded px-3 py-2 bg-slate-50 text-xs">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="font-semibold text-slate-800 flex-1"><?= sanitize($log['resumo'] ?? '') ?></div>
                                        <span class="text-slate-500 whitespace-nowrap"><?= sanitize($log['created_at'] ?? '') ?></span>
                                    </div>
                                    <div class="flex flex-wrap gap-3 mt-1 text-slate-600">
                                        <?php if (!empty($log['versao_numero'])): ?>
                                            <span>Versão: <?= sanitize($log['versao_numero']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($log['user_name'])): ?>
                                            <span>Responsável: <?= sanitize($log['user_name']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<script>
(function() {
    const fields = document.getElementById('fields');
    const addBtn = document.getElementById('add-field');
    let index = 0;
    let dragItem = null;

    function updateOrderValues() {
        const items = Array.from(fields.querySelectorAll('.field-item'));
        items.forEach((item, order) => {
            const key = item.dataset.key;
            const orderInput = item.querySelector('.field-order');
            if (orderInput) orderInput.value = order;
            const labelInput = item.querySelector('.field-label');
            if (labelInput) labelInput.name = `fields[${key}][label]`;
            const requiredInput = item.querySelector('.field-required');
            if (requiredInput) requiredInput.name = `fields[${key}][required]`;
            const orderBadge = item.querySelector('.order-badge');
            if (orderBadge) orderBadge.textContent = order + 1;
        });
    }

    function addField(label = '', required = false) {
        const key = index++;
        const wrapper = document.createElement('div');
        wrapper.className = 'field-item border border-slate-200 rounded p-3 space-y-2 bg-slate-50';
        wrapper.dataset.key = key;
        wrapper.innerHTML = `
            <div class="flex items-start justify-between gap-2">
                <div class="flex items-start gap-2 flex-1">
                    <button type="button" class="drag-handle text-slate-700 hover:text-slate-900 cursor-move mt-2" data-drag-handle draggable="true" aria-label="Arraste para reordenar">
                        <span class="sr-only">Arraste para reordenar</span>
                        <span class="grid grid-rows-3 grid-cols-2 gap-[3px]">
                            <span class="block h-1.5 w-1.5 rounded-full bg-slate-700"></span>
                            <span class="block h-1.5 w-1.5 rounded-full bg-slate-700"></span>
                            <span class="block h-1.5 w-1.5 rounded-full bg-slate-700"></span>
                            <span class="block h-1.5 w-1.5 rounded-full bg-slate-700"></span>
                            <span class="block h-1.5 w-1.5 rounded-full bg-slate-700"></span>
                            <span class="block h-1.5 w-1.5 rounded-full bg-slate-700"></span>
                        </span>
                    </button>
                    <div class="flex-1">
                        <label class="block text-xs text-slate-600 mb-1">Nome do campo <span class="order-badge inline-block bg-slate-200 text-slate-700 rounded px-1 ml-1"></span></label>
                        <input class="field-label w-full border border-slate-200 rounded px-3 py-2" name="fields[${key}][label]" value="${label}" required />
                    </div>
                </div>
                <button type="button" class="text-rose-600 text-xs mt-1 remove-field">Remover</button>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-600">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" class="field-required rounded" name="fields[${key}][required]" value="1" ${required ? 'checked' : ''} /> Obrigatório
                </label>
                <span class="text-slate-400">Arraste para ordenar</span>
            </div>
            <input type="hidden" class="field-order" name="fields[${key}][order]" value="0" />
        `;
        fields.appendChild(wrapper);
        updateOrderValues();
    }

    addBtn.addEventListener('click', (e) => {
        e.preventDefault();
        addField();
    });

    fields.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-field')) {
            e.preventDefault();
            const box = e.target.closest('.field-item');
            if (box) {
                box.remove();
                updateOrderValues();
            }
        }
    });

    fields.addEventListener('dragstart', function(e) {
        const handle = e.target.closest('[data-drag-handle]');
        if (!handle) {
            e.preventDefault();
            return;
        }
        dragItem = handle.closest('.field-item');
        if (!dragItem) return;
        dragItem.classList.add('opacity-60');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', '');
    });

    fields.addEventListener('dragover', function(e) {
        if (!dragItem) return;
        const target = e.target.closest('.field-item');
        if (!target || target === dragItem) return;
        e.preventDefault();
        const rect = target.getBoundingClientRect();
        const after = (e.clientY - rect.top) > (rect.height / 2);
        fields.insertBefore(dragItem, after ? target.nextSibling : target);
    });

    fields.addEventListener('drop', function(e) {
        if (dragItem) {
            e.preventDefault();
        }
    });

    fields.addEventListener('dragend', function() {
        if (dragItem) {
            dragItem.classList.remove('opacity-60');
            dragItem = null;
            updateOrderValues();
        }
    });

    // Preenche campos em revisão
    <?php if (!empty($editingFields)): ?>
        <?php foreach ($editingFields as $f): ?>
            addField(<?= json_encode((string)$f['label']) ?>, <?= ($f['required'] ?? 0) ? 'true' : 'false' ?>);
        <?php endforeach; ?>
    <?php else: ?>
        addField();
    <?php endif; ?>
})();

(function() {
    const toggles = document.querySelectorAll('.toggle-history');
    toggles.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            const box = document.getElementById(targetId);
            if (!box) return;
            box.classList.toggle('hidden');
            btn.textContent = box.classList.contains('hidden') ? 'Mostrar' : 'Esconder';
        });
    });

    const fieldToggles = document.querySelectorAll('.toggle-fields');
    fieldToggles.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            const box = document.getElementById(targetId);
            if (!box) return;
            box.classList.toggle('hidden');
            btn.textContent = box.classList.contains('hidden') ? 'Mostrar' : 'Esconder';
        });
    });
})();
</script>
