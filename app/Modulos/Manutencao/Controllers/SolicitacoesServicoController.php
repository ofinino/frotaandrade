<?php
namespace App\Modulos\Manutencao\Controllers;

use App\Core\View;
use App\Modulos\Manutencao\Models\SolicitacoesServicoModel;
use App\Modulos\Manutencao\Models\OrdensServicoModel;
use App\Modulos\Manutencao\Models\AnexosModel;
use App\Modulos\Manutencao\Models\AuditoriaModel;

class SolicitacoesServicoController
{
    private SolicitacoesServicoModel $ssModel;
    private OrdensServicoModel $osModel;
    private AnexosModel $anexosModel;
    private AuditoriaModel $auditoria;
    private \PDO $db;

    public function __construct()
    {
        $this->db = db();
        $empresaId = current_company_id();
        $filialId = current_branch_id();
        $filiais = current_branch_ids();
        $this->ssModel = new SolicitacoesServicoModel($this->db, $empresaId, $filialId, $filiais);
        $this->osModel = new OrdensServicoModel($this->db, $empresaId, $filialId, $filiais);
        $this->anexosModel = new AnexosModel($this->db, $empresaId, $filialId, $filiais);
        $this->auditoria = new AuditoriaModel($this->db, $empresaId, $filialId, $filiais);
    }

    public function index(): void
    {
        if (!has_permission('ss.view')) {
            flash('error', 'Sem permissao para ver SS.');
            header('Location: index.php');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_permission('ss.manage')) {
            $this->handlePost();
            return;
        }

        $filters = [
            'status' => $_GET['status'] ?? null,
            'prioridade' => $_GET['prioridade'] ?? null,
            'veiculo_id' => $_GET['veiculo_id'] ?? null,
            'source_type' => $_GET['source_type'] ?? null,
            'q' => trim($_GET['q'] ?? ''),
            'de' => $_GET['de'] ?? null,
            'ate' => $_GET['ate'] ?? null,
        ];

        try {
            $lista = $this->ssModel->listar($filters);
            $osList = $this->osModel->listar([]);
            $veiculos = $this->osModel->listarVeiculos();
        } catch (\Throwable $e) {
            flash('error', 'Erro ao carregar SS: ' . $e->getMessage());
            $lista = $osList = $veiculos = [];
        }

        View::render('Manutencao', 'servicos/index', [
            'title' => 'Solicitacoes de Servico',
            'lista' => $lista,
            'osList' => $osList,
            'veiculos' => $veiculos,
            'filters' => $filters,
        ]);
    }

    public function create(): void
    {
        if (!has_permission('ss.manage')) {
            flash('error', 'Sem permissao para criar SS.');
            header('Location: index.php?page=servicos');
            return;
        }
        $veiculos = $this->osModel->listarVeiculos();
        View::render('Manutencao', 'servicos/create', [
            'title' => 'Nova SS',
            'veiculos' => $veiculos,
        ]);
    }

    public function show(): void
    {
        if (!has_permission('ss.view')) {
            flash('error', 'Sem permissao para ver SS.');
            header('Location: index.php');
            return;
        }
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $ss = $this->ssModel->obter($id);
        if (!$ss) {
            flash('error', 'SS nao encontrada.');
            header('Location: index.php?page=servicos');
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_permission('ss.manage') && !empty($_FILES['anexos'])) {
            $userId = current_user()['id'] ?? 0;
            $this->anexosModel->salvar('ss', $id, $_FILES['anexos'], $userId);
            $this->auditoria->registrar('ss', $id, 'upload', null, ['files' => count($_FILES['anexos']['name'] ?? [])], $userId);
            flash('success', 'Anexos enviados.');
            header('Location: index.php?page=servicos&action=show&id=' . $id);
            return;
        }
        $attachments = $this->anexosModel->listar('ss', $id);
        $timeline = $this->auditoria->listar('ss', $id);
        $osList = $this->osModel->listar([]);
        View::render('Manutencao', 'servicos/show', [
            'title' => 'SS #' . $id,
            'ss' => $ss,
            'attachments' => $attachments,
            'timeline' => $timeline,
            'osList' => $osList,
        ]);
    }

    private function handlePost(): void
    {
        $userId = current_user()['id'] ?? null;
        // Criar SS manual
        if (isset($_POST['create_ss'])) {
            $titulo = trim($_POST['titulo'] ?? '');
            if ($titulo === '') {
                flash('error', 'Titulo obrigatorio.');
                header('Location: index.php?page=servicos');
                return;
            }
            $ssId = $this->ssModel->criar([
                'filial_id' => current_branch_id(),
                'source_type' => 'manual',
                'titulo' => $titulo,
                'descricao' => trim($_POST['descricao'] ?? ''),
                'prioridade' => $_POST['prioridade'] ?? 'media',
                'veiculo_id' => $_POST['veiculo_id'] ?? null,
                'status' => 'aberta',
                'criada_por' => $userId,
            ]);
            if (!empty($_FILES['anexos'])) {
                $this->anexosModel->salvar('ss', $ssId, $_FILES['anexos'], $userId ?? 0);
            }
            $this->auditoria->registrar('ss', $ssId, 'create', null, ['titulo' => $titulo], $userId);
            flash('success', 'SS criada.');
            header('Location: index.php?page=servicos&action=show&id=' . $ssId);
            return;
        }

        // Rejeitar
        if (isset($_POST['reject_ss'])) {
            $id = (int)$_POST['reject_ss'];
            $motivo = trim($_POST['motivo'] ?? '');
            $this->ssModel->mudarStatus($id, 'rejeitada', $motivo);
            $this->auditoria->registrar('ss', $id, 'reject', null, ['motivo' => $motivo], $userId);
            flash('success', 'SS rejeitada.');
            header('Location: index.php?page=servicos');
            return;
        }

        // Encerrar
        if (isset($_POST['close_ss'])) {
            $id = (int)$_POST['close_ss'];
            $this->ssModel->mudarStatus($id, 'encerrada', null);
            $this->auditoria->registrar('ss', $id, 'close', null, null, $userId);
            flash('success', 'SS encerrada.');
            header('Location: index.php?page=servicos');
            return;
        }

        // Converter para OS
        if (isset($_POST['convert_ss'])) {
            $id = (int)$_POST['convert_ss'];
            $osId = $this->ssModel->converterParaOS($id, [
                'aberta_por' => $userId,
                'observacoes' => $_POST['obs_os'] ?? '',
            ], $this->osModel);
            $this->auditoria->registrar('ss', $id, 'convert', null, ['os_id' => $osId], $userId);
            flash('success', 'SS convertida em OS.');
            header('Location: index.php?mod=manutencao&ctrl=OrdensServico&action=show&id=' . $osId);
            return;
        }

        // Vincular em OS existente
        if (isset($_POST['link_ss'])) {
            $ssId = (int)$_POST['ss_id'];
            $osId = (int)$_POST['os_id'];
            $this->ssModel->vincularEmOS($ssId, $osId);
            $this->auditoria->registrar('ss', $ssId, 'link_os', null, ['os_id' => $osId], $userId);
            flash('success', 'SS vinculada a OS.');
            header('Location: index.php?page=servicos');
            return;
        }
    }
}
