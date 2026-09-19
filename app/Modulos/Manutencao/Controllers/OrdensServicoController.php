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
            flash_error('Erro ao carregar OS.', $e);
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
        $responsaveis = $this->osModel->listarExecutantes();
        View::render('Manutencao', 'os/create', [
            'title' => 'Nova OS',
            'veiculos' => $veiculos,
            'ssList' => $ssList,
            'responsaveis' => $responsaveis,
        ]);
    }

    public function store(): void
    {
        if (!has_permission('os.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=os');
            return;
        }
        $veiculoId = (int)($_POST['veiculo_id'] ?? 0);
        $status = $_POST['status'] ?? 'solicitacao';
        $allowedStatus = ['solicitacao', 'aguardando_agendamento', 'em_execucao', 'analise_aprovacao'];
        $odometro = $_POST['odometro_abertura'] ?? null;
        $prioridade = $_POST['prioridade'] ?? 'media';
        $allowedPrioridade = ['baixa', 'media', 'alta', 'urgente'];
        $tipoFornecedor = $_POST['tipo_fornecedor'] ?? 'externo';
        $fornecedor = trim($_POST['fornecedor'] ?? '');
        $motivoAbertura = trim($_POST['motivo_abertura'] ?? '');
        $responsavelId = (int)($_POST['responsavel_id'] ?? 0);
        $medicaoTipo = ($_POST['medicao_tipo'] ?? 'odometro') === 'horimetro' ? 'horimetro' : 'odometro';
        $disponibilidade = in_array($_POST['disponibilidade'] ?? '', ['disponivel', 'indisponivel'], true) ? $_POST['disponibilidade'] : null;

        if (!$veiculoId || !$this->osModel->veiculoValido($veiculoId)) {
            flash('error', 'Veiculo invalido.');
            header('Location: index.php?page=os&action=create');
            return;
        }
        if (!in_array($status, $allowedStatus, true)) {
            flash('error', 'Status invalido.');
            header('Location: index.php?page=os&action=create');
            return;
        }
        if ($odometro !== null && $odometro !== '' && !is_numeric($odometro)) {
            flash('error', 'Odometro invalido.');
            header('Location: index.php?page=os&action=create');
            return;
        }
        if (!in_array($prioridade, $allowedPrioridade, true)) {
            flash('error', 'Prioridade invalida.');
            header('Location: index.php?page=os&action=create');
            return;
        }
        if ($motivoAbertura === '') {
            flash('error', 'Informe o motivo da abertura.');
            header('Location: index.php?page=os&action=create');
            return;
        }
        if (!in_array($tipoFornecedor, ['interno', 'externo'], true)) {
            flash('error', 'Tipo de fornecedor invalido.');
            header('Location: index.php?page=os&action=create');
            return;
        }
        if ($tipoFornecedor === 'externo' && $fornecedor === '') {
            flash('error', 'Informe o fornecedor externo.');
            header('Location: index.php?page=os&action=create');
            return;
        }
        if ($responsavelId && !$this->osModel->responsavelValido($responsavelId)) {
            flash('error', 'Responsavel invalido.');
            header('Location: index.php?page=os&action=create');
            return;
        }

        $userId = current_user()['id'] ?? null;
        $payload = [
            'veiculo_id' => $veiculoId,
            'status' => $status,
            'odometro_abertura' => $odometro !== '' ? $odometro : null,
            'observacoes' => $_POST['observacoes'] ?? null,
            'aberta_por' => $userId,
            'aberta_em' => $this->osModel->normalizarProgramadaPara($_POST['aberta_em'] ?? '') ?? date('Y-m-d H:i:s'),
            'medicao_tipo' => $medicaoTipo,
            'disponibilidade' => $disponibilidade,
            'prioridade' => $prioridade,
            'motivo_abertura' => $motivoAbertura,
            'tipo_fornecedor' => $tipoFornecedor,
            'fornecedor' => $tipoFornecedor === 'externo' ? $fornecedor : null,
            'responsavel_id' => $responsavelId ?: null,
        ];
        if (trim($_POST['codigo'] ?? '') !== '') {
            $payload['codigo'] = trim($_POST['codigo']);
        }
        $osId = $this->osModel->criar($payload);

        $ssIds = $_POST['ss_ids'] ?? [];
        if (!is_array($ssIds)) {
            $ssIds = [];
        }
        $this->osModel->vincularServiceRequests($osId, $ssIds);
        foreach ($ssIds as $sid) {
            $this->ssModel->mudarStatus((int)$sid, 'convertida', null);
            $this->auditoria->registrar('ss', (int)$sid, 'linked_os', null, ['os_id' => $osId], $userId);
        }

        $servicoTitulos = $_POST['servico_titulo'] ?? [];
        $servicoValores = $_POST['servico_valor'] ?? [];
        foreach ((array)$servicoTitulos as $i => $titulo) {
            $titulo = trim((string)$titulo);
            if ($titulo === '') {
                continue;
            }
            $this->osModel->addItem($osId, [
                'titulo' => $titulo,
                'valor' => $servicoValores[$i] ?? 0,
            ]);
        }

        $pendenciaTitulos = $_POST['pendencia_titulo'] ?? [];
        foreach ((array)$pendenciaTitulos as $titulo) {
            $titulo = trim((string)$titulo);
            if ($titulo === '') {
                continue;
            }
            $this->osModel->addPendencia($osId, $titulo);
        }

        if (!empty($_FILES['anexos']['name'][0] ?? '')) {
            $this->anexosModel->salvar('os', $osId, $_FILES['anexos'], (int)($userId ?? 0));
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
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_permission('os.manage') && !empty($_FILES['anexos']['name'][0] ?? '')) {
            $osIdUpload = (int)($_POST['os_id'] ?? $_GET['id'] ?? 0);
            $userId = current_user()['id'] ?? null;
            $this->anexosModel->salvar('os', $osIdUpload, $_FILES['anexos'], (int)($userId ?? 0));
            $this->auditoria->registrar('os', $osIdUpload, 'upload', null, ['files' => count($_FILES['anexos']['name'] ?? [])], $userId);
            flash('success', 'Anexos enviados.');
            header('Location: index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=' . $osIdUpload);
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
        $programadaPara = $this->osModel->normalizarProgramadaPara($programadaParaRaw);
        $actorUserId = current_user()['id'] ?? null;

        if ($osId <= 0 || !$this->osModel->obter($osId)) {
            $this->json(['ok' => false, 'message' => 'OS invalida.'], 422);
        }

        if ($executorId > 0 && !$this->osModel->executorValido($executorId)) {
            $this->json(['ok' => false, 'message' => 'Executor invalido.'], 422);
        }

        if ($programadaParaRaw !== '' && $programadaPara === null) {
            $this->json(['ok' => false, 'message' => 'Data/hora programada invalida.'], 422);
        }

        $ok = $this->osModel->agendarExecutor(
            $osId,
            $executorId > 0 ? $executorId : null,
            $programadaPara,
            $actorUserId
        );

        if (!$ok) {
            $this->json(['ok' => false, 'message' => 'Nao foi possivel salvar planejamento.'], 500);
        }

        $savedSchedule = $this->osModel->obterAgendamento($osId);
        $requestedExecutor = $executorId > 0 ? $executorId : null;
        $savedExecutor = $savedSchedule['executor_id'] ?? null;
        $savedExecutorNome = $savedSchedule['executor_nome'] ?? null;
        $savedProgramada = $savedSchedule['programada_para'] ?? null;

        if (($programadaPara !== null && $savedProgramada !== $programadaPara)
            || (($savedExecutor !== null ? (int)$savedExecutor : null) !== $requestedExecutor)) {
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

        $this->json([
            'ok' => true,
            'executor_id' => $savedExecutor,
            'executor_nome' => $savedExecutorNome,
            'programada_para' => $savedProgramada,
        ]);
    }

    public function changeStatusDrag(): void
    {
        if (!has_permission('os.manage')) {
            $this->json(['ok' => false, 'message' => 'Sem permissao.'], 403);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['ok' => false, 'message' => 'Metodo invalido.'], 405);
        }

        $osId = (int)($_POST['os_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $draggableStatus = ['solicitacao', 'aguardando_agendamento', 'em_execucao', 'analise_aprovacao'];

        if ($osId <= 0 || !$this->osModel->obter($osId)) {
            $this->json(['ok' => false, 'message' => 'OS invalida.'], 422);
        }
        if (!in_array($status, $draggableStatus, true)) {
            $this->json(['ok' => false, 'message' => 'Status invalido para o quadro.'], 422);
        }

        $this->osModel->mudarStatus($osId, $status);
        $this->auditoria->registrar('os', $osId, 'status_drag', null, ['status' => $status], current_user()['id'] ?? null);
        $this->json(['ok' => true, 'status' => $status]);
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
        $odometroFechamento = $_POST['odometro_fechamento'] ?? null;
        $allowedStatus = ['solicitacao', 'aguardando_agendamento', 'em_execucao', 'analise_aprovacao', 'encerrada', 'cancelada'];
        if (!$id || !$this->osModel->obter($id) || !in_array($status, $allowedStatus, true)) {
            flash('error', 'OS invalida ou status invalido.');
            header('Location: index.php?page=os');
            return;
        }
        if ($odometroFechamento !== null && $odometroFechamento !== '' && !is_numeric($odometroFechamento)) {
            flash('error', 'Odometro de fechamento invalido.');
            header('Location: index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=' . $id);
            return;
        }
        if ($status === 'encerrada') {
            $motivos = $this->osModel->motivosBloqueioEncerramento($id, $odometroFechamento);
            if ($motivos) {
                flash('error', 'Nao foi possivel encerrar a OS: ' . implode(' ', $motivos));
                header('Location: index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=' . $id);
                return;
            }
        }
        $this->osModel->mudarStatus($id, $status, $odometroFechamento);
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
        if ($osId && $titulo !== '' && $this->osModel->obter($osId)) {
            $this->osModel->addItem($osId, [
                'titulo' => $titulo,
                'descricao' => $_POST['descricao'] ?? '',
                'prioridade' => $_POST['prioridade'] ?? 'media',
                'valor' => $_POST['valor'] ?? 0,
            ]);
            $this->auditoria->registrar('os', $osId, 'add_item', null, ['titulo' => $titulo], current_user()['id'] ?? null);
            flash('success', 'Servico adicionado.');
        } else {
            flash('error', 'Informe um titulo para o servico.');
        }
        header('Location: index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=' . $osId);
    }

    public function addPendencia(): void
    {
        if (!has_permission('os.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=os');
            return;
        }
        $osId = (int)($_POST['os_id'] ?? 0);
        $titulo = trim($_POST['titulo'] ?? '');
        if ($osId && $titulo !== '' && $this->osModel->obter($osId)) {
            $this->osModel->addPendencia($osId, $titulo);
            $this->auditoria->registrar('os', $osId, 'add_pendencia', null, ['titulo' => $titulo], current_user()['id'] ?? null);
            flash('success', 'Pendencia adicionada.');
        } else {
            flash('error', 'Informe um titulo para a pendencia.');
        }
        header('Location: index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=' . $osId);
    }

    public function resolvePendencia(): void
    {
        if (!has_permission('os.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=os');
            return;
        }
        $osId = (int)($_POST['os_id'] ?? 0);
        $pendenciaId = (int)($_POST['pendencia_id'] ?? 0);
        $resolvida = !empty($_POST['resolvida']);
        if ($osId && $pendenciaId && $this->osModel->resolverPendencia($pendenciaId, $osId, $resolvida)) {
            $this->auditoria->registrar('os', $osId, 'pendencia_status', null, ['pendencia_id' => $pendenciaId, 'resolvida' => $resolvida], current_user()['id'] ?? null);
            flash('success', 'Pendencia atualizada.');
        } else {
            flash('error', 'Pendencia nao encontrada.');
        }
        header('Location: index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=' . $osId);
    }

    public function updateItemStatus(): void
    {
        if (!has_permission('os.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=os');
            return;
        }
        $osId = (int)($_POST['os_id'] ?? 0);
        $itemId = (int)($_POST['item_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $allowedStatus = ['pendente', 'em_andamento', 'concluido', 'bloqueado', 'cancelado'];
        if ($osId && $itemId && in_array($status, $allowedStatus, true) && $this->osModel->obter($osId)) {
            if ($this->osModel->atualizarStatusItem($itemId, $osId, $status)) {
                $this->auditoria->registrar('os', $osId, 'item_status', null, ['item_id' => $itemId, 'status' => $status], current_user()['id'] ?? null);
                flash('success', 'Status do item atualizado.');
            } else {
                flash('error', 'Item nao encontrado.');
            }
        } else {
            flash('error', 'Dados invalidos para atualizar o item.');
        }
        header('Location: index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=' . $osId);
    }

    public function linkServiceRequests(): void
    {
        if (!has_permission('os.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=os');
            return;
        }
        $osId = (int)($_POST['os_id'] ?? 0);
        $ssIds = $_POST['ss_ids'] ?? [];
        if (!is_array($ssIds)) {
            $ssIds = [];
        }
        if ($osId && $ssIds && $this->osModel->obter($osId)) {
            $userId = current_user()['id'] ?? null;
            $this->osModel->vincularServiceRequests($osId, $ssIds);
            foreach ($ssIds as $sid) {
                $this->ssModel->mudarStatus((int)$sid, 'convertida', null);
                $this->auditoria->registrar('ss', (int)$sid, 'linked_os', null, ['os_id' => $osId], $userId);
            }
            $this->auditoria->registrar('os', $osId, 'link_ss', null, ['ss_ids' => $ssIds], $userId);
            flash('success', 'SS vinculada(s) a OS.');
        } else {
            flash('error', 'Selecione ao menos uma SS para vincular.');
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
        if ($osId && $desc !== '' && $this->osModel->obter($osId)) {
            $this->osModel->addMaoDeObra($osId, [
                'descricao' => $desc,
                'executor_id' => $_POST['executor_id'] ?? null,
                'horas' => $_POST['horas'] ?? 0,
                'valor_hora' => $_POST['valor_hora'] ?? 0,
            ]);
            $this->auditoria->registrar('os', $osId, 'add_labor', null, ['descricao' => $desc], current_user()['id'] ?? null);
            flash('success', 'Mao de obra registrada.');
        } else {
            flash('error', 'Informe uma descricao para a mao de obra.');
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
        if ($osId && $desc !== '' && $this->osModel->obter($osId)) {
            $this->osModel->addPeca($osId, [
                'descricao' => $desc,
                'part_number' => $_POST['part_number'] ?? null,
                'quantidade' => $_POST['quantidade'] ?? 1,
                'custo_unit' => $_POST['custo_unit'] ?? 0,
                'unidade' => $_POST['unidade'] ?? 'un',
            ]);
            $this->auditoria->registrar('os', $osId, 'add_part', null, ['descricao' => $desc], current_user()['id'] ?? null);
            flash('success', 'Peca lancada.');
        } else {
            flash('error', 'Informe uma descricao para a peca.');
        }
        header('Location: index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=' . $osId);
    }
}



