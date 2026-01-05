<?php
namespace App\Modulos\Manutencao\Controllers;

use App\Core\View;
use App\Modulos\Manutencao\Models\ExecucoesModel;

class ExecucoesController
{
    private ExecucoesModel $execModel;
    private \PDO $db;

    public function __construct()
    {
        $db = db();
        $this->db = $db;
        $empresaId = current_company_id();
        $filialId = current_branch_id();
        $filiais = current_branch_ids();
        $this->execModel = new ExecucoesModel($db, $empresaId, $filialId, $filiais);
    }

    public function index(): void
    {
        if (!has_permission('checks.view')) {
            flash('error', 'Sem permissao para ver execucoes.');
            header('Location: index.php');
            exit;
        }

        // excluir execucao pendente
        if (isset($_GET['delete_run'])) {
            if (!has_permission('checks.view')) {
                flash('error', 'Sem permissao para excluir execucao.');
                header('Location: index.php?page=checks');
                return;
            }
            $deleted = $this->execModel->excluirExecucaoPendente((int) $_GET['delete_run']);
            flash($deleted > 0 ? 'success' : 'error', $deleted > 0 ? 'Execucao excluida.' : 'Nao foi possivel excluir. Verifique se ja foi iniciada.');
            header('Location: index.php?page=checks');
            return;
        }

        $editingRun = null;
        if (isset($_GET['edit_run'])) {
            if (!has_permission('checks.view')) {
                flash('error', 'Sem permissao para editar execucao.');
                header('Location: index.php?page=checks');
                return;
            }
            $editingRun = $this->execModel->buscarExecucao((int) $_GET['edit_run']);
            if (!$editingRun) {
                flash('error', 'Execucao nao encontrada.');
                header('Location: index.php?page=checks');
                return;
            }
            if ((!empty($editingRun['status']) && $editingRun['status'] !== 'pendente') || !empty($editingRun['executado_por'])) {
                flash('error', 'Somente execucoes pendentes e nao iniciadas podem ser editadas.');
                header('Location: index.php?page=checks');
                return;
            }
        }

        // criar/editar execucao
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_permission('checks.view')) {
            $templateId = (int) ($_POST['template_id'] ?? 0);
            $vehicleId = $_POST['vehicle_id'] ?? '';
            $vehicleId = $vehicleId === '' ? null : (int) $vehicleId;
            $title = trim($_POST['title'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $assignedTo = $_POST['assigned_to'] ?? '';
            $assignedTo = $assignedTo === '' ? null : (int) $assignedTo;
            $dueAt = trim($_POST['due_at'] ?? '');
            $dueAt = $dueAt === '' ? null : $dueAt;
            $startedAt = trim($_POST['started_at'] ?? '');
            $finishedAt = trim($_POST['finished_at'] ?? '');
            $editId = isset($_POST['edit_id']) ? (int) $_POST['edit_id'] : 0;

            if (!$templateId) {
                flash('error', 'Escolha um modelo.');
                header('Location: index.php?page=checks');
                return;
            }

            $tplInfo = $this->execModel->obterChecklistInfo($templateId);
            if (!$tplInfo) {
                flash('error', 'Modelo n????o encontrado ou sem acesso.');
                header('Location: index.php?page=checks');
                return;
            }
            if (!$editId && isset($tplInfo['status']) && $tplInfo['status'] === 'inativo') {
                flash('error', 'Modelo inativo n????o pode ser usado em novas execu????????es.');
                header('Location: index.php?page=checks');
                return;
            }
            $grupoId = (int)$tplInfo['grupo_id'];
            $versaoId = (int)$tplInfo['versao_atual'];

            $payload = [
                'grupo_id' => $grupoId,
                'checklist_id' => $templateId,
                'versao_id' => $versaoId,
                'filial_id' => current_branch_id(),
                'veiculo_id' => $vehicleId,
                'atribuido_para' => $assignedTo,
                'prazo_em' => $dueAt,
                'title' => $title,
                'notes' => $notes,
                'iniciado_em' => $startedAt ?: null,
                'finalizado_em' => $finishedAt ?: null,
            ];

            if ($editId) {
                $updated = $this->execModel->atualizarExecucaoPendente($editId, $payload);
                flash($updated > 0 ? 'success' : 'error', $updated > 0 ? 'Execucao atualizada.' : 'Nao foi possivel editar. Verifique se ja foi iniciada.');
                header('Location: index.php?page=checks');
                return;
            }

            $this->execModel->criarExecucao($payload);
            flash('success', 'Execucao criada/atribuida. O executante iniciara conforme programado.');
            header('Location: index.php?page=checks');
            return;
        }

        $user = current_user();
        $role = $user['role'] ?? 'membro';
        $statusPermitidos = ['pendente', 'concluido'];

        $rawStatus = $_GET['status'] ?? '';
        $statusFiltro = in_array($rawStatus, $statusPermitidos, true) ? $rawStatus : null;
        $busca = trim($_GET['q'] ?? '');
        $designado = isset($_GET['designado']) ? (int) $_GET['designado'] : null;
        $modelo = isset($_GET['modelo']) ? (int) $_GET['modelo'] : null;
        $veiculo = isset($_GET['veiculo']) && $_GET['veiculo'] !== '' ? (int) $_GET['veiculo'] : null;
        $prazoMode = $_GET['prazo_mode'] ?? '';
        $prazoMode = in_array($prazoMode, ['hoje','semana','atrasados','intervalo'], true) ? $prazoMode : null;
        $prazoDe = trim($_GET['prazo_de'] ?? '');
        $prazoAte = trim($_GET['prazo_ate'] ?? '');
        $prazoDe = preg_match('/^\d{4}-\d{2}-\d{2}$/', $prazoDe) ? $prazoDe : null;
        $prazoAte = preg_match('/^\d{4}-\d{2}-\d{2}$/', $prazoAte) ? $prazoAte : null;

        $filters = [
            'q' => $busca ?: null,
            'status' => $statusFiltro,
            'designado' => $designado ?: null,
            'modelo' => $modelo ?: null,
            'veiculo' => $veiculo,
            'prazo_mode' => $prazoMode,
            'prazo_de' => $prazoDe,
            'prazo_ate' => $prazoAte,
        ];

        try {
            // Executante nao deve ver concluidos
            $mostrarSomenteNaoConcluido = ($role === 'executante');
            $runs = $this->execModel->listarExecutoes($user['id'], $role, $mostrarSomenteNaoConcluido, $filters);
            $templates = $this->execModel->listarTemplates();
            if ($editingRun) {
                $hasTpl = false;
                foreach ($templates as $tpl) {
                    if ((int)$tpl['id'] === (int)$editingRun['checklist_id']) {
                        $hasTpl = true;
                        break;
                    }
                }
                if (!$hasTpl) {
                    $stmtTpl = $this->db->prepare('SELECT id, name, grupo_id FROM man_checklists WHERE empresa_id = ? AND id = ?');
                    $stmtTpl->execute([current_company_id(), (int)$editingRun['checklist_id']]);
                    if ($extraTpl = $stmtTpl->fetch()) {
                        $extraTpl['inativo'] = true;
                        $templates[] = $extraTpl;
                    }
                }
            }
            $vehicles = $this->execModel->listarVeiculos();
            $executantes = $this->execModel->listarExecutantes();
            $groups = $this->execModel->listarGrupos();
        } catch (\Throwable $e) {
            flash('error', 'Erro ao carregar execucoes: ' . $e->getMessage());
            $runs = $templates = $vehicles = $executantes = $groups = [];
        }

        View::render('Manutencao', 'checks', [
            'title' => 'Execucoes',
            'runs' => $runs,
            'templates' => $templates,
            'vehicles' => $vehicles,
            'executantes' => $executantes,
            'groups' => $groups ?? [],
            'user' => $user,
            'editingRun' => $editingRun,
            'filters' => $filters,
        ]);
    }

    public function run(): void
    {
        $runId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $run = $this->execModel->obterExecucao($runId);
        if (!$run) {
            flash('error', 'Execucao nao encontrada.');
            header('Location: index.php?page=checks');
            return;
        }

        $readOnly = ($run['status'] === 'concluido' && !is_admin());

        // remocao de midia via ajax
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_media_id'])) {
            header('Content-Type: application/json');
            if ($readOnly) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'msg' => 'Checklist concluido, somente administrador pode alterar.']);
                exit;
            }
            if (!has_permission('checks.view')) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'msg' => 'Sem permissao']);
                exit;
            }
            $mid = (int)$_POST['delete_media_id'];
            $this->execModel->removerMidias([$mid], $runId);
            echo json_encode(['ok' => true]);
            exit;
        }

        if (!has_permission('checks.view')) {
            flash('error', 'Sem permissao para executar checklist.');
            header('Location: index.php?page=checks');
            return;
        }
        $user = current_user();
        if ($user['role'] === 'executante' && $run['atribuido_para'] !== $user['id'] && $run['executado_por'] !== $user['id']) {
            flash('error', 'Execucao nao atribuida a voce.');
            header('Location: index.php?page=checks');
            return;
        }

        if ($readOnly && $_SERVER['REQUEST_METHOD'] === 'POST') {
            flash('error', 'Checklist ja concluido. Somente administrador pode editar.');
            header('Location: index.php?page=run_check&id=' . $runId);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $this->salvarExecucao($run);
            } catch (\Throwable $e) {
                flash('error', 'Erro ao salvar: ' . $e->getMessage());
                header('Location: index.php?page=run_check&id=' . $runId);
            }
            return;
        }

        $versaoId = (int)$run['versao_id'];
        $fields = $this->execModel->obterCamposVersao($versaoId);
        $answers = $this->execModel->obterRespostas($runId);
        $mediaByField = $this->execModel->obterMidiasPorCampo($runId);

        $run['status'] = $run['status'] ?: 'pendente';
        if ($run['status'] === 'pendente' && !empty($run['iniciado_em']) && empty($run['finalizado_em'])) {
            $run['status'] = 'em_andamento';
        }

        $statusLabels = [
            'pendente' => 'Pendente',
            'em_andamento' => 'Em andamento',
            'pausado' => 'Pausado',
            'concluido' => 'Concluido',
        ];

        View::render('Manutencao', 'run_check', [
            'title' => 'Executar checklist',
            'run' => $run,
            'fields' => $fields,
            'answers' => $answers,
            'mediaByField' => $mediaByField,
            'statusLabels' => $statusLabels,
            'readOnly' => $readOnly,
        ]);
    }

    private function salvarExecucao(array $run): void
    {
        $execId = (int)$run['id'];
        $user = current_user();
        $allowedPhoto = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $allowedVideo = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska'];
        // Limite de fotos por campo
        $maxPhotoPerField = 4;
        $maxUploadBytes = (int) (1.5 * 1024 * 1024); // alvo ~1.5MB por arquivo apos compressao
        $maxPhotoWidth = 800;
        $maxPhotoHeight = 800;
        $gdAvailable = extension_loaded('gd');
        $action = $_POST['action'] ?? 'continuar';
        $status = $run['status'] ?: 'pendente';
        $now = date('Y-m-d H:i:s');

        $performedBy = $run['executado_por'];
        if (!$performedBy && $user['role'] === 'executante') {
            $performedBy = $user['id'];
        }
        $startedAt = trim($_POST['started_at'] ?? '') ?: ($run['iniciado_em'] ?? null);
        $finishedAt = trim($_POST['finished_at'] ?? '') ?: ($run['finalizado_em'] ?? null);

        $tempo = (int)($run['tempo_execucao_segundos'] ?? 0);
        $executandoDesde = $run['executando_desde'] ?? null;

        $versaoId = (int)$run['versao_id'];
        $fields = $this->execModel->obterCamposVersao($versaoId);
        $answersExisting = $this->execModel->obterRespostas($execId);
        $uploadDir = ensure_upload_dir();
        $photoCountByField = $this->execModel->contarMidiasPorCampo($execId, 'photo');
        $statusPermitidos = ['conforme', 'nao_conforme', 'nao_se_aplica'];
        $skippedPhotos = 0;
        $skippedSize = 0;
        $limitWarnings = [];

        // exclusao de midias marcadas
        if (!empty($_POST['delete_media']) && is_array($_POST['delete_media'])) {
            $idsToDelete = array_map('intval', $_POST['delete_media']);
            if ($idsToDelete) {
                $this->execModel->removerMidias($idsToDelete, $execId);
            }
        }

        // validacao basica das respostas
        foreach ($fields as $field) {
            $fid = (int)$field['id'];
            $statusVal = trim($_POST['status_' . $fid] ?? '');
            $obsVal = trim($_POST['obs_' . $fid] ?? '');
            if ($field['required'] && !in_array($statusVal, $statusPermitidos, true)) {
                flash('error', 'Selecione uma resposta para o campo obrigatorio: ' . $field['label']);
                header('Location: index.php?page=run_check&id=' . $execId);
                return;
            }
            if ($statusVal === 'nao_conforme' && $obsVal === '') {
                flash('error', 'Para "Nao Conforme" informe a observacao no campo: ' . $field['label']);
                header('Location: index.php?page=run_check&id=' . $execId);
                return;
            }
        }

        foreach ($fields as $field) {
            $fieldId = (int) $field['id'];
            $statusVal = trim($_POST['status_' . $fieldId] ?? '');
            $obsVal = trim($_POST['obs_' . $fieldId] ?? '');
            if (!in_array($statusVal, $statusPermitidos, true)) {
                $statusVal = '';
            }
            if ($statusVal !== 'nao_conforme') {
                $obsVal = '';
            }
            $value = json_encode(['status' => $statusVal, 'obs' => $obsVal]);

            $answerId = $this->execModel->upsertResposta($execId, $fieldId, $value, $answersExisting);

            $mediaKey = 'media_' . $fieldId;
            if (isset($_FILES[$mediaKey]) && !empty($_FILES[$mediaKey]['name'][0])) {
                // Normaliza os arquivos enviados
                $files = [];
                $names = $_FILES[$mediaKey]['name'];
                $tmpNames = $_FILES[$mediaKey]['tmp_name'];
                $types = $_FILES[$mediaKey]['type'];
                $errors = $_FILES[$mediaKey]['error'];
                $sizes = $_FILES[$mediaKey]['size'];
                $count = is_array($names) ? count($names) : 0;
                for ($i = 0; $i < $count; $i++) {
                    if ($errors[$i] !== UPLOAD_ERR_OK || empty($names[$i]) || empty($tmpNames[$i])) {
                        continue;
                    }
                    $files[] = [
                        'name' => $names[$i],
                        'tmp' => $tmpNames[$i],
                        'type' => $types[$i] ?? '',
                        'size' => $sizes[$i] ?? 0,
                    ];
                }

                $existingPhotos = (int)($photoCountByField[$fieldId] ?? 0);
                $labelField = $field['label'] ?? ('Campo ' . $fieldId);
                $availablePhotoSlots = max(0, $maxPhotoPerField - $existingPhotos);
                $processedPhotos = 0;

                foreach ($files as $file) {
                    $mime = $file['type'];
                    $original = $file['name'];
                    $tmp = $file['tmp'];
                    $mediaType = null;
                    if (in_array($mime, $allowedPhoto, true) || strpos($mime, 'image/') === 0) {
                        $mediaType = 'photo';
                    } elseif (in_array($mime, $allowedVideo, true) || strpos($mime, 'video/') === 0) {
                        $mediaType = 'video';
                    } else {
                        continue;
                    }

                    if ($mediaType === 'photo') {
                        if ($processedPhotos >= $availablePhotoSlots) {
                            $skippedPhotos++;
                            $limitWarnings[$fieldId] = $labelField;
                            continue;
                        }
                        $filename = uniqid('photo_', true) . '.jpg';
                        $targetPath = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
                        $saved = $gdAvailable
                            ? $this->processPhotoUpload($tmp, $targetPath, $maxPhotoWidth, $maxPhotoHeight, $maxUploadBytes)
                            : (@filesize($tmp) <= $maxUploadBytes && $this->moveUploadedFile($tmp, $targetPath));
                        if (!$saved) {
                            $skippedSize++;
                            continue;
                        }
                        $relative = 'uploads/' . $filename;
                        $this->execModel->inserirMidia($answerId, $relative, $mediaType, $original);
                        $processedPhotos++;
                    } else {
                        if (@filesize($tmp) > $maxUploadBytes) {
                            $skippedSize++;
                            continue;
                        }
                        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
                        $safeExt = preg_replace('/[^a-z0-9]+/', '', $ext);
                        $filename = uniqid($mediaType . '_', true) . ($safeExt ? '.' . $safeExt : '');
                        $targetPath = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
                        $moved = $this->moveUploadedFile($tmp, $targetPath);
                        if ($moved) {
                            $relative = 'uploads/' . $filename;
                            $this->execModel->inserirMidia($answerId, $relative, $mediaType, $original);
                        }
                    }
                }

                if ($processedPhotos < count($files) && ($processedPhotos + $existingPhotos) >= $maxPhotoPerField) {
                    $limitWarnings[$fieldId] = $labelField;
                }
            }
        }

        if ($executandoDesde && in_array($action, ['pausar', 'concluir'], true)) {
            $tempo += max(0, strtotime($now) - strtotime($executandoDesde));
            $executandoDesde = null;
        }

        $signatureExec = $_POST['signature_executante'] ?? '';
        $signaturePaths = $this->salvarAssinaturas($execId, $signatureExec, '');

        if ($action === 'concluir') {
            if ($signaturePaths['executante']) {
                $status = 'concluido';
            } else {
                flash('error', 'Para concluir e necessario a assinatura do Executante.');
                header('Location: index.php?page=run_check&id=' . $execId);
                return;
            }
            if (!$finishedAt) {
                $finishedAt = $now;
            }
        } elseif ($action === 'pausar') {
            $status = 'pausado';
        } else {
            $status = 'em_andamento';
            if (!$executandoDesde) {
                $executandoDesde = $now;
            }
        }

        if (!$startedAt) {
            $startedAt = $now;
        }

        $this->execModel->atualizarExecucao($execId, [
            'status' => $status,
            'executado_por' => $performedBy,
            'iniciado_em' => $startedAt ?: null,
            'finalizado_em' => $finishedAt ?: null,
            'tempo_execucao_segundos' => $tempo,
            'executando_desde' => $executandoDesde,
        ]);
        // Gera SS para itens nao conformes desta execucao
        (new \App\Modulos\Manutencao\Models\SolicitacoesServicoModel($this->db, current_company_id(), current_branch_id(), current_branch_ids()))
            ->criarSSNaoConformePorExecucao($execId, $user['id'] ?? null);

        $msg = 'Respostas salvas.';
        if ($skippedPhotos > 0) {
            $msg .= ' ' . $skippedPhotos . ' foto(s) ignorada(s) (limite de ' . $maxPhotoPerField . ' por item)';
            if ($limitWarnings) {
                $msg .= ' em: ' . implode(', ', array_values($limitWarnings));
            }
            $msg .= '.';
        }
        if ($skippedSize > 0) {
            $msg .= ' ' . $skippedSize . ' arquivo(s) ignorado(s) por tamanho.';
        }
        flash('success', $msg);
        header('Location: index.php?page=checks');
        exit;
    }

    private function salvarAssinaturas(int $execId, string $sigExec, string $sigResp): array
    {
        $result = ['executante' => null, 'responsavel' => null];
        $dir = ensure_upload_dir() . DIRECTORY_SEPARATOR . 'signatures';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $salvar = function (string $dataUri, string $role) use ($dir, $execId): ?string {
            if (!$dataUri) {
                return null;
            }
            if (!preg_match('#^data:image/(png|jpeg);base64,#', $dataUri)) {
                return null;
            }
            [$meta, $data] = explode(',', $dataUri, 2);
            $ext = strpos($meta, 'jpeg') !== false ? 'jpg' : 'png';
            $path = $dir . DIRECTORY_SEPARATOR . "exec_{$execId}_{$role}." . $ext;
            $bytes = base64_decode($data);
            if ($bytes === false) {
                return null;
            }
            file_put_contents($path, $bytes);
            return 'uploads/signatures/' . basename($path);
        };
        $result['executante'] = $salvar($sigExec, 'executante');
        $result['responsavel'] = $salvar($sigResp, 'responsavel');
        return $result;
    }

    private function processPhotoUpload(string $tmp, string $dest, int $maxW, int $maxH, int $maxBytes): bool
    {
        if (!function_exists('getimagesize') || !function_exists('imagecreatetruecolor')) {
            return false;
        }
        $info = @getimagesize($tmp);
        if (!$info) {
            return false;
        }
        [$w, $h, $type] = $info;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $src = function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($tmp) : null;
                break;
            case IMAGETYPE_PNG:
                $src = function_exists('imagecreatefrompng') ? @imagecreatefrompng($tmp) : null;
                break;
            case IMAGETYPE_WEBP:
                $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : null;
                break;
            case IMAGETYPE_GIF:
                $src = function_exists('imagecreatefromgif') ? @imagecreatefromgif($tmp) : null;
                break;
            default:
                $src = null;
        }
        if (!$src) {
            return false;
        }
        // Corrige orientacao com base no EXIF quando disponivel
        $src = $this->applyExifOrientation($src, $tmp, $w, $h);
        // Reprocessa sempre para comprimir e padronizar
        $ratio = min($maxW / max($w, 1), $maxH / max($h, 1), 1);
        $newW = max(1, (int) floor($w * $ratio));
        $newH = max(1, (int) floor($h * $ratio));
        $canvas = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($canvas, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
        $ok = imagejpeg($canvas, $dest, 60);
        imagedestroy($canvas);
        imagedestroy($src);
        if (!$ok) {
            return false;
        }
        if (filesize($dest) > $maxBytes) {
            @unlink($dest);
            return false;
        }
        return true;

    }

    private function applyExifOrientation($src, string $filePath, int &$w, int &$h)
    {
        $orientation = null;
        // Tenta EXIF se dispon????vel
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($filePath);
            if ($exif && !empty($exif['Orientation'])) {
                $orientation = (int)$exif['Orientation'];
            }
        }
        // Fallback: getimagesize com info
        if ($orientation === null) {
            $info = [];
            @getimagesize($filePath, $info);
            if (!empty($info['Orientation'])) {
                $orientation = (int)$info['Orientation'];
            }
        }
        if ($orientation === null) {
            return $src;
        }
        switch ($orientation) {
            case 3:
                $src = imagerotate($src, 180, 0);
                break;
            case 6:
                $src = imagerotate($src, -90, 0);
                [$w, $h] = [$h, $w];
                break;
            case 8:
                $src = imagerotate($src, 90, 0);
                [$w, $h] = [$h, $w];
                break;
            default:
                return $src;
        }
        return $src;
    }

    private function moveUploadedFile(string $tmp, string $targetPath): bool
    {
        $moved = @move_uploaded_file($tmp, $targetPath);
        if (!$moved) {
            $data = @file_get_contents($tmp);
            if ($data !== false) {
                $moved = @file_put_contents($targetPath, $data) !== false;
            }
        }
        return $moved;
    }
}
