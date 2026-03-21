<?php
namespace App\Modulos\Manutencao\Controllers;

use App\Core\View;
use App\Modulos\Manutencao\Models\PlanosPreventivaModel;
use App\Modulos\Manutencao\Models\SolicitacoesServicoModel;
use App\Modulos\Manutencao\Models\AuditoriaModel;

class PlanosPreventivaController
{
    private PlanosPreventivaModel $model;
    private SolicitacoesServicoModel $ssModel;
    private AuditoriaModel $auditoria;

    public function __construct()
    {
        $db = db();
        $empresaId = current_company_id();
        $filialId = current_branch_id();
        $filiais = current_branch_ids();
        $this->model = new PlanosPreventivaModel($db, $empresaId, $filialId, $filiais);
        $this->ssModel = new SolicitacoesServicoModel($db, $empresaId, $filialId, $filiais);
        $this->auditoria = new AuditoriaModel($db, $empresaId, $filialId, $filiais);
    }

    public function index(): void
    {
        if (!has_permission('preventiva.view')) {
            flash('error', 'Sem permissao para preventiva.');
            header('Location: index.php');
            return;
        }
        $plans = $this->model->listarPlanos([]);
        View::render('Manutencao', 'preventiva/planos_index', [
            'title' => 'Planos de Preventiva',
            'plans' => $plans,
        ]);
    }

    public function form(): void
    {
        if (!has_permission('preventiva.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=planos_preventiva');
            return;
        }
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        $plan = $id ? $this->model->obterPlano($id) : null;
        View::render('Manutencao', 'preventiva/plano_form', [
            'title' => $id ? 'Editar plano' : 'Novo plano',
            'plan' => $plan,
        ]);
    }

    public function save(): void
    {
        if (!has_permission('preventiva.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=planos_preventiva');
            return;
        }
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
        $data = [
            'nome' => trim($_POST['nome'] ?? ''),
            'descricao' => trim($_POST['descricao'] ?? ''),
            'veiculo_id' => $_POST['veiculo_id'] ?? null,
            'tipo' => $_POST['tipo'] ?? 'km_tempo',
            'km_intervalo' => $_POST['km_intervalo'] ?? null,
            'dias_intervalo' => $_POST['dias_intervalo'] ?? null,
            'due_soon_km' => $_POST['due_soon_km'] ?? 0,
            'due_soon_dias' => $_POST['due_soon_dias'] ?? 0,
            'ativo' => isset($_POST['ativo']) ? 1 : 0,
            'criado_por' => current_user()['id'] ?? null,
        ];
        $planId = $this->model->salvarPlano($data, $id);
        $tasks = $_POST['tasks'] ?? [];
        $this->model->salvarTarefas($planId, $tasks);
        $this->auditoria->registrar('preventiva', $planId, $id ? 'update_plan' : 'create_plan', null, $data, current_user()['id'] ?? null);
        flash('success', 'Plano salvo.');
        header('Location: index.php?page=planos_preventiva');
    }

    public function show(): void
    {
        if (!has_permission('preventiva.view')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php');
            return;
        }
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $plan = $this->model->obterPlano($id);
        if (!$plan) {
            flash('error', 'Plano nao encontrado.');
            header('Location: index.php?page=planos_preventiva');
            return;
        }
        View::render('Manutencao', 'preventiva/plano_show', [
            'title' => 'Plano #' . $id,
            'plan' => $plan,
        ]);
    }

    public function vencimentos(): void
    {
        if (!has_permission('preventiva.view')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php');
            return;
        }
        $status = $_GET['status'] ?? null;
        $due = $this->model->listarVencimentos(['status' => $status]);
        View::render('Manutencao', 'preventiva/vencimentos', [
            'title' => 'Vencimentos Preventiva',
            'vencimentos' => $due,
            'status' => $status,
        ]);
    }

    public function run(): void
    {
        if (!has_permission('preventiva.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php');
            return;
        }
        $result = $this->model->processarPreventiva($this->ssModel, $this->auditoria, true);
        flash('success', 'Preventiva executada. Vencimentos: ' . $result['due_updated'] . ' | SS criadas: ' . $result['ss_criadas']);
        header('Location: index.php?page=vencimentos_preventiva');
    }
}
