<?php
namespace App\Modulos\Manutencao\Controllers;

use App\Core\View;
use App\Modulos\Manutencao\Models\OrdensServicoModel;
use App\Modulos\Manutencao\Models\SolicitacoesServicoModel;
use App\Modulos\Manutencao\Models\AnexosModel;
use App\Modulos\Manutencao\Models\AuditoriaModel;

class OrdensServicoController
{
    private OrdensServicoModel $osModel;
    private SolicitacoesServicoModel $ssModel;
    private AnexosModel $anexosModel;
    private AuditoriaModel $auditoria;
    private \PDO $db;

    public function __construct()
    {
        $this->db = db();
        $empresaId = current_company_id();
        $filialId = current_branch_id();
        $filiais = current_branch_ids();
        $this->osModel = new OrdensServicoModel($this->db, $empresaId, $filialId, $filiais);
        $this->ssModel = new SolicitacoesServicoModel($this->db, $empresaId, $filialId, $filiais);
        $this->anexosModel = new AnexosModel($this->db, $empresaId, $filialId, $filiais);
        $this->auditoria = new AuditoriaModel($this->db, $empresaId, $filialId, $filiais);
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function logScheduleDebug(array $data): void
    {
        try {
            $dir = __DIR__ . '/../../../../logs';
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $line = date('Y-m-d H:i:s') . ' ' . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
            file_put_contents($dir . '/os_schedule_debug.log', $line, FILE_APPEND);
        } catch (\Throwable $e) {
            // ignora erro de log
        }
    }

    private function normalizarProgramadaPara(?string $value): ?string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return null;
        }

        $formats = [
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd/m/Y',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i',
            'Y-m-d',
        ];

        foreach ($formats as $format) {
            $dt = \DateTime::createFromFormat($format, $raw);
            if (!$dt) {
                continue;
            }

            $errors = \DateTime::getLastErrors();
            $warningCount = is_array($errors) ? (int)($errors['warning_count'] ?? 0) : 0;
            $errorCount = is_array($errors) ? (int)($errors['error_count'] ?? 0) : 0;
            if ($warningCount > 0 || $errorCount > 0) {
                continue;
            }

            if ($dt->format($format) !== $raw) {
                continue;
            }

            if (in_array($format, ['Y-m-d', 'd/m/Y'], true)) {
                $dt->setTime(0, 0, 0);
            } elseif (in_array($format, ['Y-m-d\TH:i', 'Y-m-d H:i', 'd/m/Y H:i'], true)) {
                $dt->setTime((int)$dt->format('H'), (int)$dt->format('i'), 0);
            }

            return $dt->format('Y-m-d H:i:s');
        }

        return null;
    }
    public function index(): void
    {
        if (!has_permission('os.view')) {
            flash('error', 'Sem permissao para ver OS.');
            header('Location: index.php');
            exit;
        }
        $filters = [
            'status' => $_GET['status'] ?? null,
            'veiculo_id' => $_GET['veiculo_id'] ?? null,
            'q' => trim($_GET['q'] ?? ''),
        ];
        $agendaDate = $_GET['agenda_date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $agendaDate)) {
            $agendaDate = date('Y-m-d');
        }

        try {
            $orders = $this->osModel->listar($filters);
            $veiculos = $this->osModel->listarVeiculos();
            $executores = $this->osModel->listarExecutantes();
        } catch (\Throwable $e) {
            flash('error', 'Erro ao carregar OS: ' . $e->getMessage());
            $orders = $veiculos = $executores = [];
        }
        View::render('Manutencao', 'os/index', [
            'title' => 'Planejamento de Ordens de Servico',
            'orders' => $orders,
            'veiculos' => $veiculos,
            'executores' => $executores,
            'filters' => $filters,
            'agendaDate' => $agendaDate,
        ]);
    }

    public function create(): void
    {
        if (!has_permission('os.manage')) {
            flash('error', 'Sem permissao para criar OS.');
            header('Location: index.php?page=os');
            return;
        }
        $veiculos = $this->osModel->listarVeiculos();
        $ssList = $this->ssModel->listar(['status' => 'aberta']);
        View::render('Manutencao', 'os/create', [
            'title' => 'Nova OS',
            'veiculos' => $veiculos,
            'ssList' => $ssList,
        ]);
    }

    public function store(): void
    {
        if (!has_permission('os.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=os');
            return;
        }
        $userId = current_user()['id'] ?? null;
        $payload = [
            'veiculo_id' => $_POST['veiculo_id'] ?? null,
            'status' => $_POST['status'] ?? 'aprovada',
            'odometro_abertura' => $_POST['odometro_abertura'] ?? null,
            'observacoes' => $_POST['observacoes'] ?? null,
            'aberta_por' => $userId,
            'aberta_em' => date('Y-m-d H:i:s'),
        ];
        $osId = $this->osModel->criar($payload);
        $ssIds = $_POST['ss_ids'] ?? [];
        $this->osModel->vincularServiceRequests($osId, $ssIds);
        foreach ($ssIds as $sid) {
            $this->ssModel->mudarStatus((int)$sid, 'convertida', null);
            $this->auditoria->registrar('ss', (int)$sid, 'linked_os', null, ['os_id' => $osId], $userId);
        }
        $this->auditoria->registrar('os', $osId, 'create', null, $payload, $userId);
        flash('success', 'OS criada.');
        header('Location: index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=' . $osId);
    }

    public function show(): void
    {
        if (!has_permission('os.view')) {
            flash('error', 'Sem permissao para ver OS.');
            header('Location: index.php');
            return;
        }
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $os = $this->osModel->obter($id);
        if (!$os) {
            flash('error', 'OS nao encontrada.');
            header('Location: index.php?page=os');
            return;
        }
        $attachments = $this->anexosModel->listar('os', $id);
        $timeline = $this->auditoria->listar('os', $id);
        $ssList = $this->ssModel->listar(['status' => 'aberta']);
        View::render('Manutencao', 'os/show', [
            'title' => 'OS ' . $os['codigo'],
            'os' => $os,
            'attachments' => $attachments,
            'timeline' => $timeline,
            'ssList' => $ssList,
        ]);
    }

    public function assignExecutor(): void
    {
        if (!has_permission('os.manage')) {
            $this->json(['ok' => false, 'message' => 'Sem permissao.'], 403);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['ok' => false, 'message' => 'Metodo invalido.'], 405);
        }

        $osId = (int)($_POST['os_id'] ?? 0);
        $executorId = (int)($_POST['executor_id'] ?? 0);
        $programadaParaRaw = trim((string)($_POST['programada_para'] ?? ''));
        $programadaPara = $this->normalizarProgramadaPara($programadaParaRaw);
        $actorUserId = current_user()['id'] ?? null;

        $this->logScheduleDebug([
            'stage' => 'request',
            'os_id' => $osId,
            'executor_id_raw' => $_POST['executor_id'] ?? null,
            'executor_id_norm' => $executorId > 0 ? $executorId : null,
            'programada_raw' => $programadaParaRaw,
            'programada_norm' => $programadaPara,
            'user_id' => $actorUserId,
        ]);

        if ($osId <= 0) {
            $this->json(['ok' => false, 'message' => 'OS invalida.'], 422);
        }

        if ($programadaParaRaw !== '' && $programadaPara === null) {
            $this->logScheduleDebug([
                'stage' => 'invalid_datetime',
                'os_id' => $osId,
                'programada_raw' => $programadaParaRaw,
            ]);
            $this->json(['ok' => false, 'message' => 'Data/hora programada invalida.'], 422);
        }

        $ok = $this->osModel->agendarExecutor(
            $osId,
            $executorId > 0 ? $executorId : null,
            $programadaPara,
            $actorUserId
        );

        if (!$ok) {
            $this->logScheduleDebug([
                'stage' => 'save_failed',
                'os_id' => $osId,
                'executor_id_norm' => $executorId > 0 ? $executorId : null,
                'programada_norm' => $programadaPara,
            ]);
            $this->json(['ok' => false, 'message' => 'Nao foi possivel salvar planejamento.'], 500);
        }

        $savedSchedule = $this->osModel->obterAgendamento($osId);
        $requestedExecutor = $executorId > 0 ? $executorId : null;
        $savedExecutor = $savedSchedule['executor_id'] ?? null;
        $savedExecutorNome = $savedSchedule['executor_nome'] ?? null;
        $savedProgramada = $savedSchedule['programada_para'] ?? null;

        if (($programadaPara !== null && $savedProgramada !== $programadaPara)
            || (($savedExecutor !== null ? (int)$savedExecutor : null) !== $requestedExecutor)) {
            $this->logScheduleDebug([
                'stage' => 'mismatch_after_save',
                'os_id' => $osId,
                'requested_programada' => $programadaPara,
                'saved_programada' => $savedProgramada,
                'requested_executor' => $requestedExecutor,
                'saved_executor' => $savedExecutor,
            ]);
            $this->json([
                'ok' => false,
                'message' => 'Planejamento salvo com divergencia. Recarregue a pagina e tente novamente.',
                'requested_programada' => $programadaPara,
                'saved_programada' => $savedProgramada,
                'requested_executor' => $requestedExecutor,
                'saved_executor' => $savedExecutor,
            ], 409);
        }

        $this->auditoria->registrar('os', $osId, 'schedule_assign', null, [
            'executor_id' => $savedExecutor,
            'executor_nome' => $savedExecutorNome,
            'programada_para' => $savedProgramada,
        ], $actorUserId);

        $this->logScheduleDebug([
            'stage' => 'saved',
            'os_id' => $osId,
            'executor_id' => $savedExecutor,
            'executor_nome' => $savedExecutorNome,
            'programada_para' => $savedProgramada,
        ]);

        $this->json([
            'ok' => true,
            'executor_id' => $savedExecutor,
            'executor_nome' => $savedExecutorNome,
            'programada_para' => $savedProgramada,
        ]);
    }

    public function changeStatus(): void
    {
        if (!has_permission('os.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=os');
            return;
        }
        $id = (int)($_POST['os_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $this->osModel->mudarStatus($id, $status);
        $this->auditoria->registrar('os', $id, 'status', null, ['status' => $status], current_user()['id'] ?? null);
        flash('success', 'Status atualizado.');
        header('Location: index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=' . $id);
    }

    public function addItem(): void
    {
        if (!has_permission('os.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=os');
            return;
        }
        $osId = (int)($_POST['os_id'] ?? 0);
        $titulo = trim($_POST['titulo'] ?? '');
        if ($osId && $titulo !== '') {
            $this->osModel->addItem($osId, [
                'titulo' => $titulo,
                'descricao' => $_POST['descricao'] ?? '',
                'prioridade' => $_POST['prioridade'] ?? 'media',
            ]);
            $this->auditoria->registrar('os', $osId, 'add_item', null, ['titulo' => $titulo], current_user()['id'] ?? null);
        }
        header('Location: index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=' . $osId);
    }

    public function addLabor(): void
    {
        if (!has_permission('os.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=os');
            return;
        }
        $osId = (int)($_POST['os_id'] ?? 0);
        $desc = trim($_POST['descricao'] ?? '');
        if ($osId && $desc !== '') {
            $this->osModel->addMaoDeObra($osId, [
                'descricao' => $desc,
                'executor_id' => $_POST['executor_id'] ?? null,
                'horas' => $_POST['horas'] ?? 0,
                'valor_hora' => $_POST['valor_hora'] ?? 0,
            ]);
            $this->auditoria->registrar('os', $osId, 'add_labor', null, ['descricao' => $desc], current_user()['id'] ?? null);
        }
        header('Location: index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=' . $osId);
    }

    public function addPart(): void
    {
        if (!has_permission('os.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=os');
            return;
        }
        $osId = (int)($_POST['os_id'] ?? 0);
        $desc = trim($_POST['descricao'] ?? '');
        if ($osId && $desc !== '') {
            $this->osModel->addPeca($osId, [
                'descricao' => $desc,
                'part_number' => $_POST['part_number'] ?? null,
                'quantidade' => $_POST['quantidade'] ?? 1,
                'custo_unit' => $_POST['custo_unit'] ?? 0,
                'unidade' => $_POST['unidade'] ?? 'un',
            ]);
            $this->auditoria->registrar('os', $osId, 'add_part', null, ['descricao' => $desc], current_user()['id'] ?? null);
        }
        header('Location: index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=' . $osId);
    }
}



