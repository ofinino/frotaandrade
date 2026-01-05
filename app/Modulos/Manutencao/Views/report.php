<?php
$run = $run ?? null;
$fields = $fields ?? [];
$answers = $answers ?? [];
$mediaByField = $mediaByField ?? [];
$config = $GLOBALS['config'];
$company = current_company();
if (!$run) {
    echo '<p>Relatorio nao encontrado ou sem permissao.</p>';
    return;
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        $len = strlen($needle);
        if ($len === 0) {
            return true;
        }
        return substr($haystack, -$len) === $needle;
    }
}

function formatDateTime(?string $val): string
{
    if (!$val) return '-';
    try {
        $dt = new DateTime($val, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
        return $dt->format('d/m/Y H:i');
    } catch (Throwable $e) {
        return date('d/m/Y H:i', strtotime($val));
    }
}

$tempoSegundos = (int)($run['tempo_execucao_segundos'] ?? 0);
if (!empty($run['executando_desde'])) {
    $tempoSegundos += max(0, time() - strtotime($run['executando_desde']));
}
function formatSeconds(int $seconds): string
{
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    $s = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}
$tempoFormatado = formatSeconds($tempoSegundos);

$logo = $company['logo_url'] ?? ($config['report_logo'] ?? '');
$companyName = $company['display_name'] ?? ($config['company_display_name'] ?? 'Empresa');

$sigRelPath = null;
$sigBase = 'uploads/signatures/exec_' . (int)$run['id'] . '_executante';
$sigCandidates = [$sigBase . '.png', $sigBase . '.jpg'];
$rootPath = realpath(__DIR__ . '/../../../../') ?: (__DIR__ . '/../../../../');
foreach ($sigCandidates as $rel) {
    $abs = $rootPath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
    if (is_file($abs)) {
        $sigRelPath = $rel;
        break;
    }
}
$sigUrl = '';
if ($sigRelPath) {
    $sigPath = ltrim(str_replace('\\', '/', $sigRelPath), '/');
    $sigSegments = array_map('rawurlencode', explode('/', $sigPath));
    $sigParam = implode('/', $sigSegments);
    $sigUrl = asset_url('index.php?page=media&f=' . $sigParam);
}
$sigDataUri = '';
if ($sigRelPath) {
    $abs = $rootPath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sigRelPath);
    if (is_file($abs)) {
        $mime = str_ends_with($sigRelPath, '.jpg') ? 'image/jpeg' : 'image/png';
        $data = @file_get_contents($abs);
        if ($data !== false) {
            $sigDataUri = 'data:' . $mime . ';base64,' . base64_encode($data);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        :root { color-scheme: light; }
        body { font-family: Arial, sans-serif; color: #0f172a; margin: 24px; }
        .header { display:flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #cbd5e1; padding-bottom: 12px; margin-bottom: 16px;}
        .logo { max-height: 60px; }
        .title { font-size: 20px; font-weight: 700; }
        .subtitle { color:#475569; font-size:13px; }
        .meta-grid { display:grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap:8px; margin-bottom:12px; }
        .meta-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:8px; font-size:12px; display:flex; flex-direction:column; gap:2px;}
        .meta-label { font-weight:700; color:#0f172a; }
        .meta-value { color:#334155; }
        table { width:100%; border-collapse: collapse; margin-top:12px; }
        th, td { border:1px solid #e2e8f0; padding:8px; font-size:13px; vertical-align: top;}
        th { background:#f1f5f9; text-align:left; }
        .attachments { font-size:12px; color:#475569; display:grid; grid-template-columns: repeat(auto-fit, minmax(140px,1fr)); gap:6px;}
        .attachment-item { border:1px solid #e2e8f0; border-radius:6px; padding:6px; background:#fff; }
        .attachment-item img { width:100%; height:120px; object-fit: cover; border-radius:4px; }
        img { image-orientation: from-image; }
        @media print { body { margin: 12mm; } .no-print { display:none; } }
    </style>
    <title>Relatorio checklist</title>
</head>
<body>
    <div class="header" style="border-bottom:1px solid #cbd5e1;">
        <div>
            <div class="title"><?= sanitize($companyName) ?></div>
            <div class="subtitle"><?= sanitize($config['app_name'] ?? 'Checklist') ?></div>
            <div class="subtitle"><?= sanitize($run['template_name']) ?> - <?= sanitize($run['title'] ?: ('Checklist #' . $run['id'])) ?></div>
        </div>
        <?php if (!empty($logo)): ?>
            <div><img src="<?= sanitize($logo) ?>" alt="logo" class="logo" /></div>
        <?php endif; ?>
    </div>
    <h2 style="margin:4px 0;">Relatorio de Execucao</h2>
    <div class="meta-grid">
        <div class="meta-box">
            <div class="meta-label">Checklist</div>
            <div class="meta-value"><?= sanitize($run['title'] ?: ('#'.$run['id'])) ?></div>
        </div>
        <div class="meta-box">
            <div class="meta-label">Executante</div>
            <div class="meta-value"><?= sanitize($run['assigned_name'] ?: $run['performer'] ?: '-') ?></div>
        </div>
        <div class="meta-box">
            <div class="meta-label">Status</div>
            <div class="meta-value"><?= sanitize($run['status']) ?></div>
        </div>
        <div class="meta-box">
            <div class="meta-label">Veiculo</div>
            <div class="meta-value"><?= sanitize(trim(($run['vehicle_plate'] ?? '') . ' ' . ($run['vehicle_model'] ?? ''))) ?: '-' ?></div>
        </div>
        <div class="meta-box">
            <div class="meta-label">Data inicio</div>
            <div class="meta-value"><?= sanitize(formatDateTime($run['iniciado_em'] ?: $run['created_at'])) ?></div>
        </div>
        <div class="meta-box">
            <div class="meta-label">Data termino</div>
            <div class="meta-value"><?= sanitize(formatDateTime($run['finalizado_em'])) ?></div>
        </div>
        <div class="meta-box">
            <div class="meta-label">Prazo</div>
            <div class="meta-value"><?= sanitize(formatDateTime($run['prazo_em'])) ?></div>
        </div>
        <div class="meta-box">
            <div class="meta-label">Tempo de execucao</div>
            <div class="meta-value"><?= sanitize($tempoFormatado) ?></div>
        </div>
    </div>
    <div class="no-print" style="margin:8px 0;">
        <button onclick="window.print()" style="padding:8px 12px; background:#0f172a; color:white; border:none; border-radius:6px;">Salvar como PDF</button>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:35%;">Pergunta</th>
                <th style="width:15%;">Resposta</th>
                <th style="width:50%;">Anexos</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($fields as $field): ?>
                <?php
                    $fid = (int)$field['id'];
                    $rawAnswer = $answers[$fid]['answer'] ?? '';
                    $decoded = json_decode($rawAnswer, true);
                    $map = [
                        'conforme' => 'Conforme',
                        'nao_conforme' => 'Nao Conforme',
                        'nao_se_aplica' => 'Nao se aplica',
                    ];
                    $parts = [];
                    if (is_array($decoded)) {
                        $st = $decoded['status'] ?? '';
                        $obs = trim($decoded['obs'] ?? '');
                        if ($st) {
                            $parts[] = $map[$st] ?? $st;
                        }
                        if ($obs !== '') {
                            $parts[] = 'Obs: ' . $obs;
                        }
                    } elseif ($rawAnswer !== '') {
                        $parts[] = $rawAnswer;
                    }
                    $answerText = $parts ? implode(' | ', $parts) : '-';
                    $attachments = $mediaByField[$fid] ?? [];
                ?>
                <tr>
                    <td><?= sanitize($field['label']) ?></td>
                    <td><?= sanitize($answerText) ?></td>
                    <td>
                        <?php if ($attachments): ?>
                            <div class="attachments">
                                <?php foreach ($attachments as $att): ?>
                                    <?php
                                    $attPath = ltrim(str_replace('\\', '/', $att['file_path']), '/');
                                    $attSegments = array_map('rawurlencode', explode('/', $attPath));
                                    $attParam = implode('/', $attSegments);
                                    $attUrl = asset_url('index.php?page=media&f=' . $attParam);
                                    ?>
                                    <div class="attachment-item">
                                        <?php if ($att['media_type'] === 'photo'): ?>
                                            <a href="<?= $attUrl ?>" target="_blank" rel="noopener noreferrer" class="block">
                                                <img src="<?= $attUrl ?>" alt="foto" style="cursor:pointer;" onerror="this.src='';this.alt='(imagem indisponivel)';">
                                            </a>
                                        <?php else: ?>
                                            <div><strong>Video:</strong> <?= sanitize($att['original_name']) ?></div>
                                            <a href="<?= $attUrl ?>" target="_blank" rel="noopener noreferrer" class="text-amber-600 text-xs">Abrir video</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (!empty($run['notes'])): ?>
        <div style="margin-top:12px; padding:8px; border:1px solid #e2e8f0; border-radius:6px; background:#f8fafc;">
            <strong>Observacoes:</strong><br>
            <?= nl2br(sanitize($run['notes'])) ?>
        </div>
    <?php endif; ?>
    <?php if ($sigUrl || $sigDataUri): ?>
        <div style="margin-top:16px; display:flex; justify-content:center;">
            <div style="text-align:center;">
                <div style="font-size:12px; color:#475569; margin-bottom:6px;">Assinatura do Executante</div>
                <div style="display:inline-block; padding:8px; border:1px solid #e2e8f0; border-radius:6px; background:#fff;">
                    <img src="<?= $sigDataUri ? $sigDataUri : sanitize($sigUrl) ?>" alt="Assinatura do executante" style="max-width:120px; max-height:65px; display:block; object-fit:contain;" />
                </div>
                <div style="font-size:12px; margin-top:4px; color:#0f172a; font-weight:600;"><?= sanitize($run['assigned_name'] ?: $run['performer'] ?: 'Executante') ?></div>
            </div>
        </div>
    <?php endif; ?>
    <div style="margin-top:12px; text-align:right; font-size:12px; color:#475569;">
        <?php if (!empty($run['versao_numero'])): ?>
            Versao: <?= sanitize($run['versao_numero']) ?>
            <?php if (!empty($run['versao_data'])): ?>
                | Data da versao: <?= sanitize(formatDateTime($run['versao_data'])) ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</body>
</html>
