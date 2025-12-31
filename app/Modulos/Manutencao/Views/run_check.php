<?php
$run = $run ?? null;
$fields = $fields ?? [];
$answers = $answers ?? [];
$mediaByField = $mediaByField ?? [];
$readOnly = $readOnly ?? false; // concluido e nao admin
$statusLabels = $statusLabels ?? [
    'pendente' => 'Pendente',
    'em_andamento' => 'Em andamento',
    'pausado' => 'Pausado',
    'aguardando_assinatura' => 'Aguardando conferencia',
    'concluido' => 'Concluido',
];
if (!$run) {
    echo '<p>Execucao nao encontrada.</p>';
    return;
}
?>
<style>
    html[data-page-theme="dark"] .run-card,
    .main-shell.page-dark .run-card {
        border-color: #475569;
        background-color: #0f172a;
    }
    .run-card img { image-orientation: from-image; }
</style>
<div class="bg-white border border-slate-100 shadow rounded-lg p-4 space-y-4">
    <?php if ($readOnly): ?>
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-3 py-2 rounded text-sm">
            Checklist concluido. Edicao apenas por administrador.
        </div>
    <?php endif; ?>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <div class="text-lg font-semibold text-slate-900"><?= sanitize($run['title'] ?: 'Checklist #' . $run['id']) ?></div>
            <div class="text-sm text-slate-600">Modelo: <?= sanitize($run['template_name']) ?> | Veiculo: <?= sanitize($run['vehicle_plate'] ?: '-') ?></div>
            <div class="text-sm text-slate-600">Atribuido: <?= sanitize($run['assigned_name'] ?: '-') ?> | Status: <?= sanitize($statusLabels[$run['status']] ?? $run['status']) ?></div>
            <div class="text-xs text-slate-500">Prazo: <?= $run['prazo_em'] ? sanitize(date('d/m/Y H:i', strtotime($run['prazo_em']))) : '-' ?></div>
        </div>
        <div class="flex flex-col items-end gap-2 text-xs text-slate-500">
            <div>Criado em <?= sanitize(date('d/m/Y H:i', strtotime($run['created_at']))) ?></div>
            <a class="text-sky-600" href="index.php?page=report&id=<?= (int)$run['id'] ?>" target="_blank">Gerar relatorio</a>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data" class="space-y-4" id="run-form">
        <input type="hidden" name="action" id="action-field" value="continuar" />
        <input type="hidden" name="signature_executante" id="signature_executante" />
        <?php $fieldIndex = 0; ?>
        <?php foreach ($fields as $field): ?>
            <?php
            $fieldIndex++;
            $num = isset($field['item_num']) ? (int)$field['item_num'] : $fieldIndex;
            $fieldId = (int) $field['id'];
            $raw = $answers[$fieldId]['answer'] ?? '';
            $decoded = json_decode($raw, true);
            $statusVal = is_array($decoded) && isset($decoded['status']) ? $decoded['status'] : $raw;
            $obsVal = is_array($decoded) && isset($decoded['obs']) ? $decoded['obs'] : '';
            $statusVal = in_array($statusVal, ['conforme','nao_conforme','nao_se_aplica'], true) ? $statusVal : '';
            ?>
            <div class="border-2 border-slate-300 rounded-lg p-4 bg-slate-50 shadow-sm run-card">
                <div class="font-semibold text-slate-900 text-lg leading-snug">
                    <span class="mr-2"><?= $num ?> -</span><span><?= sanitize($field['label']) ?><?= $field['required'] ? ' *' : '' ?></span>
                </div>
                <input type="hidden" name="field_<?= $fieldId ?>" id="field_hidden_<?= $fieldId ?>" value="<?= sanitize($raw) ?>" />
                <div class="mt-3 space-y-3">
                    <div class="space-y-2 text-base">
                        <?php
                        $optionsRadio = [
                            ['value' => 'conforme', 'label' => 'Conforme', 'color' => 'text-emerald-600'],
                            ['value' => 'nao_conforme', 'label' => 'Nao conforme', 'color' => 'text-rose-700'],
                            ['value' => 'nao_se_aplica', 'label' => 'Nao se aplica', 'color' => 'text-sky-700'],
                        ];
                        foreach ($optionsRadio as $opt):
                        ?>
                        <label class="flex items-center gap-2 text-lg">
                            <input type="radio" class="h-5 w-5" name="status_<?= $fieldId ?>" value="<?= $opt['value'] ?>" <?= $statusVal === $opt['value'] ? 'checked' : '' ?> onchange="syncFieldValue(<?= $fieldId ?>)" <?= $readOnly ? 'disabled' : '' ?> />
                            <span class="<?= $opt['color'] ?> font-medium"><?= $opt['label'] ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="space-y-1">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <span>Observacao (obrigatoria em "Nao conforme")</span>
                        </label>
                        <textarea class="w-full border border-slate-200 rounded px-3 py-2" rows="2" id="obs_<?= $fieldId ?>" name="obs_<?= $fieldId ?>" placeholder="Descreva a nao conformidade" oninput="syncFieldValue(<?= $fieldId ?>)" <?= $readOnly ? 'disabled' : '' ?>><?= sanitize($obsVal) ?></textarea>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-xs text-slate-600">Anexar foto/video (ate 3 por item)</label>
                        <div id="media-wrapper-<?= $fieldId ?>" class="space-y-1">
                            <input type="file" name="media_<?= $fieldId ?>[]" accept="image/*,video/*" capture="environment" multiple class="block w-full text-sm media-input" data-field="<?= $fieldId ?>" <?= $readOnly ? 'disabled' : '' ?> />
                        </div>
                        <div id="media-selected-<?= $fieldId ?>" class="text-[11px] text-slate-500 space-y-1"></div>
                        <?php if (!empty($mediaByField[$fieldId])): ?>
                            <div class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-2">
                                <?php foreach ($mediaByField[$fieldId] as $media): ?>
                                    <?php
                                    $cleanPath = ltrim(str_replace('\\', '/', $media['file_path']), '/');
                                    $segments = array_map('rawurlencode', explode('/', $cleanPath));
                                    $mediaParam = implode('/', $segments);
                                    $mediaUrl = asset_url('index.php?page=media&f=' . $mediaParam);
                                    ?>
                                    <div class="border border-slate-200 rounded p-2 bg-white space-y-1" id="media-box-<?= (int)$media['id'] ?>">
                                        <?php if ($media['media_type'] === 'photo'): ?>
                                            <a href="<?= $mediaUrl ?>" target="_blank" rel="noopener noreferrer" class="block">
                                                <img src="<?= $mediaUrl ?>" alt="foto" class="w-full h-32 object-cover rounded cursor-pointer" onerror="this.src=''; this.alt='(imagem indisponivel)';" />
                                            </a>
                                        <?php else: ?>
                                            <video controls class="w-full h-32 rounded">
                                                <source src="<?= $mediaUrl ?>" />
                                            </video>
                                            <a class="text-xs text-sky-600 block" href="<?= $mediaUrl ?>" target="_blank" rel="noopener noreferrer">Abrir video</a>
                                        <?php endif; ?>
                                        <div class="text-[11px] text-slate-500 truncate"><?= sanitize($media['original_name']) ?></div>
                                        <?php if (!$readOnly): ?>
                                            <button type="button" class="text-[11px] text-rose-600 underline" onclick="deleteMedia(<?= (int)$media['id'] ?>)">Remover</button>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (!$readOnly): ?>
            <div class="flex flex-col sm:flex-row gap-2 justify-end">
                <button type="button" class="bg-slate-200 hover:bg-slate-300 text-slate-800 font-semibold px-5 py-2 rounded" onclick="setActionAndSubmit('pausar')">Pausar</button>
                <button type="button" class="bg-sky-600 hover:bg-sky-700 text-white font-semibold px-5 py-2 rounded" onclick="setActionAndSubmit('continuar')">Salvar e continuar</button>
                <button type="button" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2 rounded" onclick="openSignatureModal()">Concluir</button>
            </div>
        <?php endif; ?>
    </form>
</div>

<?php if (!$readOnly): ?>
<div id="signature-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 px-2 sm:px-4">
    <div class="bg-white w-full max-w-4xl rounded-lg shadow-xl p-3 sm:p-4 flex flex-col gap-4 max-h-[95vh]">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900">Assinaturas</h3>
            <button type="button" class="text-slate-500 hover:text-slate-700 text-xl leading-none" onclick="closeSignatureModal()">&times;</button>
        </div>
        <p class="text-sm text-slate-600">Executante assina para concluir.</p>
        <div class="flex-1 overflow-y-auto space-y-4">
            <div>
                <div class="text-sm text-slate-700 mb-1">Assinatura Executante (obrigatoria)</div>
                <div class="border border-slate-300 rounded bg-white p-2">
                    <canvas id="sig_auditor" width="400" height="200" style="touch-action:none; width:100%; max-width:400px; height:200px;"></canvas>
                </div>
                <div class="flex gap-2 mt-2 text-xs justify-end">
                    <button type="button" class="px-3 py-1 rounded border border-slate-300" onclick="clearSig()">Limpar</button>
                </div>
            </div>
        </div>
        <div class="flex flex-col sm:flex-row gap-2 justify-end sticky bottom-0 bg-white pt-2">
            <div class="flex-1 text-xs text-slate-500" id="sig-status"></div>
            <button type="button" class="bg-slate-200 hover:bg-slate-300 text-slate-800 font-semibold px-4 py-2 rounded w-full sm:w-auto" onclick="closeSignatureModal()">Cancelar</button>
            <button type="button" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2 rounded w-full sm:w-auto" onclick="finalizeConclude()">Salvar e concluir</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const requiredObsMsg = 'Para marcacao "Nao Conforme" e obrigatorio informar uma observacao.';
const READ_ONLY = <?= $readOnly ? 'true' : 'false' ?>;
let sigPads = {};
const CLIENT_PHOTO_MAX = { w: 800, h: 800, quality: 0.6 };

function syncFieldValue(fieldId) {
    const statusEl = document.querySelector('input[name="status_' + fieldId + '"]:checked');
    const status = statusEl ? statusEl.value : '';
    const obs = document.getElementById('obs_' + fieldId)?.value || '';
    toggleObs(fieldId, status);
    const hidden = document.getElementById('field_hidden_' + fieldId);
    if (hidden) {
        hidden.value = JSON.stringify({status: status, obs: obs});
    }
}

function validateForm(action) {
    if (READ_ONLY) return false;
    let valid = true;
    document.querySelectorAll('[id^="field_hidden_"]').forEach(el => {
        const id = el.id.replace('field_hidden_', '');
        syncFieldValue(id);
        const val = el.value ? JSON.parse(el.value) : {};
        if (val.status === 'nao_conforme' && (!val.obs || val.obs.trim() === '')) {
            valid = false;
        }
    });
    if (!valid) {
        alert(requiredObsMsg);
        return false;
    }
    return true;
}

function clearSig() {
    const pad = sigPads['auditor'];
    if (!pad) return;
    pad.ctx.clearRect(0, 0, pad.canvas.width, pad.canvas.height);
    const target = document.getElementById('signature_executante');
    if (target) target.value = '';
}

function initSignatureCanvas() {
    if (sigPads['auditor']) return;
    const canvas = document.getElementById('sig_auditor');
    if (!canvas) return;
    canvas.width = 400;
    canvas.height = 200;
    const ctx = canvas.getContext('2d');
    let drawing = false;
    const start = (e) => { drawing = true; ctx.beginPath(); ctx.moveTo(getX(e), getY(e)); };
    const end = () => { drawing = false; };
    const move = (e) => {
        if (!drawing) return;
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#0f172a';
        ctx.lineTo(getX(e), getY(e));
        ctx.stroke();
    };
    const getX = (e) => (e.touches ? e.touches[0].clientX : e.clientX) - canvas.getBoundingClientRect().left;
    const getY = (e) => (e.touches ? e.touches[0].clientY : e.clientY) - canvas.getBoundingClientRect().top;
    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('touchstart', start);
    canvas.addEventListener('mouseup', end);
    canvas.addEventListener('mouseleave', end);
    canvas.addEventListener('touchend', end);
    canvas.addEventListener('mousemove', move);
    canvas.addEventListener('touchmove', function(e){ move(e); e.preventDefault(); }, {passive:false});
    sigPads['auditor'] = {canvas, ctx};
}

function exportSignatures(requireExec) {
    const target = document.getElementById('signature_executante');
    let ok = true;
    const pad = sigPads['auditor'];
    if (!pad) {
        if (requireExec) ok = false;
    } else {
        const img = pad.ctx.getImageData(0, 0, pad.canvas.width, pad.canvas.height).data;
        const hasPixels = img.some(v => v !== 0);
        if (requireExec && !hasPixels) {
            ok = false;
        } else {
            if (target) {
                target.value = hasPixels ? pad.canvas.toDataURL('image/png') : '';
            }
        }
    }
    return ok;
}

function setActionAndSubmit(action) {
    const field = document.getElementById('action-field');
    field.value = action;
    if (!validateForm(action)) return;
    submitRunForm();
}

function openSignatureModal() {
    if (!validateForm('concluir')) return;
    document.getElementById('signature-modal').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    document.documentElement.classList.add('overflow-hidden');
    initSignatureCanvas();
}

function closeSignatureModal() {
    document.getElementById('signature-modal').classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    document.documentElement.classList.remove('overflow-hidden');
}

function finalizeConclude() {
    if (!exportSignatures(true)) {
        alert('Assinatura do Executante e obrigatoria para concluir.');
        return;
    }
    document.getElementById('action-field').value = 'concluir';
    submitRunForm();
}

function toggleObs(fieldId, statusVal) {
    if (READ_ONLY) return;
    const obsEl = document.getElementById('obs_' + fieldId);
    if (!obsEl) return;
    if (statusVal === 'nao_conforme') {
        obsEl.removeAttribute('disabled');
        obsEl.classList.remove('bg-slate-100');
    } else {
        obsEl.setAttribute('disabled', 'disabled');
        obsEl.classList.add('bg-slate-100');
        obsEl.value = '';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[name^="status_"]').forEach(el => {
        const fid = el.name.replace('status_', '');
        if (el.checked) {
            toggleObs(fid, el.value);
        }
        el.addEventListener('change', () => toggleObs(fid, el.value));
    });
});

document.addEventListener('DOMContentLoaded', initMediaInputs);

function initMediaInputs() {
    document.querySelectorAll('.media-input').forEach(input => setupMediaInput(input));
}

function setupMediaInput(input) {
    const fieldId = input.getAttribute('data-field');
    const wrapper = document.getElementById('media-wrapper-' + fieldId);
    const selected = document.getElementById('media-selected-' + fieldId);
    if (!wrapper || !selected) return;
    const handler = function () {
        if (!input.files || input.files.length === 0) return;
        Array.from(input.files).forEach(f => {
            const div = document.createElement('div');
            div.textContent = f.name;
            selected.appendChild(div);
        });
        input.classList.add('hidden');
        input.style.display = 'none';
        input.removeEventListener('change', handler);
        const fresh = document.createElement('input');
        fresh.type = 'file';
        fresh.name = input.name;
        fresh.accept = input.accept;
        fresh.multiple = true;
        const cap = input.getAttribute('capture');
        if (cap) fresh.setAttribute('capture', cap);
        fresh.className = input.className.replace('hidden', '').trim();
        fresh.setAttribute('data-field', fieldId);
        if (input.disabled) fresh.disabled = true;
        wrapper.appendChild(fresh);
        setupMediaInput(fresh);
    };
    input.addEventListener('change', handler);
}

function deleteMedia(id) {
    if (READ_ONLY) return;
    if (!confirm('Remover este anexo?')) return;
    const fd = new FormData();
    fd.append('delete_media_id', id);
    fetch(window.location.href, {
        method: 'POST',
        body: fd
    }).then(r => r.ok ? r.json() : Promise.reject()).then(data => {
        if (data && data.ok) {
            const el = document.getElementById('media-box-' + id);
            if (el) el.remove();
        } else {
            alert('Nao foi possivel remover o anexo.');
        }
    }).catch(() => alert('Erro ao remover anexo.'));
}

async function submitRunForm() {
    if (READ_ONLY) return;
    const form = document.getElementById('run-form');
    if (!form) return;
    try {
        const formData = new FormData();
        // Campos que nao sao arquivos
        const raw = new FormData(form);
        raw.forEach((value, key) => {
            if (!(value instanceof File)) {
                formData.append(key, value);
            }
        });
        // Arquivos (com compressao de imagem no cliente)
        const inputs = form.querySelectorAll('.media-input');
        for (const input of inputs) {
            const name = input.name;
            const files = input.files ? Array.from(input.files) : [];
            for (const file of files) {
                if (file && file.type && file.type.startsWith('image/')) {
                    const compressed = await compressImage(file, CLIENT_PHOTO_MAX.w, CLIENT_PHOTO_MAX.h, CLIENT_PHOTO_MAX.quality);
                    formData.append(name, compressed || file, file.name);
                } else if (file) {
                    formData.append(name, file, file.name);
                }
            }
        }
        const resp = await fetch(window.location.href, { method: 'POST', body: formData });
        if (resp.redirected) {
            window.location.href = resp.url;
            return;
        }
        const html = await resp.text();
        document.open();
        document.write(html);
        document.close();
    } catch (e) {
        console.error(e);
        alert('Erro ao salvar. Tente novamente.');
    }
}

function compressImage(file, maxW, maxH, quality) {
    return new Promise((resolve) => {
        const img = new Image();
        img.onload = () => {
            const ratio = Math.min(maxW / Math.max(img.width, 1), maxH / Math.max(img.height, 1), 1);
            const w = Math.max(1, Math.floor(img.width * ratio));
            const h = Math.max(1, Math.floor(img.height * ratio));
            const canvas = document.createElement('canvas');
            canvas.width = w;
            canvas.height = h;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, w, h);
            canvas.toBlob(
                (blob) => resolve(blob),
                'image/jpeg',
                quality
            );
        };
        img.onerror = () => resolve(null);
        img.src = URL.createObjectURL(file);
    });
}
</script>
