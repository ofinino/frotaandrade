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
        try {
            $orders = $this->osModel->listar($filters);
            $veiculos = $this->osModel->listarVeiculos();
        } catch (\Throwable $e) {
            flash('error', 'Erro ao carregar OS: ' . $e->getMessage());
            $orders = $veiculos = [];
        }
        View::render('Manutencao', 'os/index', [
            'title' => 'Ordens de Servico',
            'orders' => $orders,
            'veiculos' => $veiculos,
            'filters' => $filters,
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
