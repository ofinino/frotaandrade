<?php
namespace App\Modulos\Manutencao\Controllers;

use App\Core\View;
use App\Modulos\Manutencao\Models\PlanosPreventivaModel;
use App\Modulos\Manutencao\Models\ManutencaoServicosModel;
use App\Modulos\Manutencao\Models\SolicitacoesServicoModel;
use App\Modulos\Manutencao\Models\AuditoriaModel;

class PlanosPreventivaController
{
    private PlanosPreventivaModel $model;
    private ManutencaoServicosModel $servicosModel;
    private SolicitacoesServicoModel $ssModel;
    private AuditoriaModel $auditoria;

    public function __construct()
    {
        $db = db();
        $empresaId = current_company_id();
        $filialId = current_branch_id();
        $filiais = current_branch_ids();
        $this->model = new PlanosPreventivaModel($db, $empresaId, $filialId, $filiais);
        $this->servicosModel = new ManutencaoServicosModel($db, $empresaId, $filialId);
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
        $filters = [
            'association_type' => $_GET['tab'] ?? null,
            'q' => trim($_GET['q'] ?? ''),
        ];
        $plans = $this->model->listarPlanos($filters);
        View::render('Manutencao', 'preventiva/planos_index', [
            'title' => 'Plano de manutenção',
            'plans' => $plans,
            'filters' => $filters,
        ]);
    }

    public function create(): void
    {
        if (!has_permission('preventiva.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=planos_preventiva');
            return;
        }
        View::render('Manutencao', 'preventiva/plano_create', [
            'title' => 'Novo plano de manutenção',
            'filiais' => $this->model->listarFiliais(),
            'veiculos' => $this->model->listarVeiculos(),
        ]);
    }

    public function store(): void
    {
        if (!has_permission('preventiva.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=planos_preventiva');
            return;
        }
        $nome = trim($_POST['nome'] ?? '');
        $medicaoTipo = ($_POST['medicao_tipo'] ?? 'odometro') === 'horimetro' ? 'horimetro' : 'odometro';
        $associationType = ($_POST['association_type'] ?? 'veiculos') === 'unidade' ? 'unidade' : 'veiculos';
        $associationFilialId = $_POST['association_filial_id'] ?? null;
        $veiculoIds = $_POST['veiculo_ids'] ?? [];

        if ($nome === '') {
            flash('error', 'Informe o nome do plano.');
            header('Location: index.php?mod=manutencao&ctrl=PlanosPreventiva&action=create');
            return;
        }
        if ($associationType === 'unidade' && !$associationFilialId) {
            flash('error', 'Selecione a unidade.');
            header('Location: index.php?mod=manutencao&ctrl=PlanosPreventiva&action=create');
            return;
        }
        if ($associationType === 'veiculos' && empty($veiculoIds)) {
            flash('error', 'Selecione ao menos um veículo.');
            header('Location: index.php?mod=manutencao&ctrl=PlanosPreventiva&action=create');
            return;
        }

        $planId = $this->model->criarPlano([
            'nome' => $nome,
            'medicao_tipo' => $medicaoTipo,
            'association_type' => $associationType,
            'association_filial_id' => $associationType === 'unidade' ? (int)$associationFilialId : null,
            'veiculo_ids' => $veiculoIds,
            'criado_por' => current_user()['id'] ?? null,
        ]);
        $this->auditoria->registrar('preventiva', $planId, 'create_plan', null, ['nome' => $nome], current_user()['id'] ?? null);
        flash('success', 'Plano criado.');
        header('Location: index.php?mod=manutencao&ctrl=PlanosPreventiva&action=show&id=' . $planId);
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
            'title' => $plan['nome'],
            'plan' => $plan,
            'servicosCatalogo' => $this->servicosModel->listar(true),
            'veiculosDisponiveis' => $this->model->listarVeiculos(),
        ]);
    }

    public function publish(): void
    {
        if (!has_permission('preventiva.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=planos_preventiva');
            return;
        }
        $id = (int)($_POST['plan_id'] ?? 0);
        if ($this->model->publicar($id)) {
            $this->auditoria->registrar('preventiva', $id, 'publish', null, [], current_user()['id'] ?? null);
            flash('success', 'Plano publicado.');
        } else {
            flash('error', 'Nao foi possivel publicar o plano.');
        }
        header('Location: index.php?mod=manutencao&ctrl=PlanosPreventiva&action=show&id=' . $id);
    }

    public function toggleStatus(): void
    {
        if (!has_permission('preventiva.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=planos_preventiva');
            return;
        }
        $id = (int)($_POST['plan_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if ($this->model->toggleStatus($id, $status)) {
            $this->auditoria->registrar('preventiva', $id, 'status', null, ['status' => $status], current_user()['id'] ?? null);
            flash('success', 'Status do plano atualizado.');
        } else {
            flash('error', 'Nao foi possivel atualizar o status.');
        }
        header('Location: index.php?mod=manutencao&ctrl=PlanosPreventiva&action=show&id=' . $id);
    }

    public function addService(): void
    {
        if (!has_permission('preventiva.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=planos_preventiva');
            return;
        }
        $planId = (int)($_POST['plan_id'] ?? 0);
        $serviceIds = $_POST['service_ids'] ?? [];
        $tipo = ($_POST['tipo'] ?? 'recorrente') === 'unico' ? 'unico' : 'recorrente';
        $intervaloTempoValor = $_POST['intervalo_tempo_valor'] ?? null;
        $intervaloTempoUnidade = $_POST['intervalo_tempo_unidade'] ?? null;
        $alertaTempoDias = $_POST['alerta_tempo_dias'] ?? null;
        $intervaloMedicao = $_POST['intervalo_medicao'] ?? null;
        $alertaMedicao = $_POST['alerta_medicao'] ?? null;

        if (!$planId || empty($serviceIds) || !$this->model->obterPlano($planId)) {
            flash('error', 'Selecione ao menos um servico.');
            header('Location: index.php?mod=manutencao&ctrl=PlanosPreventiva&action=show&id=' . $planId);
            return;
        }
        if ($tipo === 'recorrente' && !$intervaloTempoValor && !$intervaloMedicao) {
            flash('error', 'Preencha pelo menos uma recorrencia: por tempo, odometro/horimetro ou ambas.');
            header('Location: index.php?mod=manutencao&ctrl=PlanosPreventiva&action=show&id=' . $planId);
            return;
        }

        $this->model->addServicos($planId, $serviceIds, [
            'tipo' => $tipo,
            'intervalo_tempo_valor' => $intervaloTempoValor !== '' ? $intervaloTempoValor : null,
            'intervalo_tempo_unidade' => $intervaloTempoUnidade !== '' ? $intervaloTempoUnidade : null,
            'alerta_tempo_dias' => $alertaTempoDias !== '' ? $alertaTempoDias : null,
            'intervalo_medicao' => $intervaloMedicao !== '' ? $intervaloMedicao : null,
            'alerta_medicao' => $alertaMedicao !== '' ? $alertaMedicao : null,
        ]);
        $this->auditoria->registrar('preventiva', $planId, 'add_services', null, ['service_ids' => $serviceIds], current_user()['id'] ?? null);
        flash('success', 'Servico(s) adicionado(s).');
        header('Location: index.php?mod=manutencao&ctrl=PlanosPreventiva&action=show&id=' . $planId);
    }

    public function removeService(): void
    {
        if (!has_permission('preventiva.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=planos_preventiva');
            return;
        }
        $planId = (int)($_POST['plan_id'] ?? 0);
        $planServiceId = (int)($_POST['plan_service_id'] ?? 0);
        $this->model->removeServico($planServiceId, $planId);
        flash('success', 'Servico removido.');
        header('Location: index.php?mod=manutencao&ctrl=PlanosPreventiva&action=show&id=' . $planId);
    }

    public function addVeiculo(): void
    {
        if (!has_permission('preventiva.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=planos_preventiva');
            return;
        }
        $planId = (int)($_POST['plan_id'] ?? 0);
        $veiculoId = (int)($_POST['veiculo_id'] ?? 0);
        if ($planId && $veiculoId) {
            $this->model->addVeiculo($planId, $veiculoId);
            flash('success', 'Veiculo adicionado.');
        }
        header('Location: index.php?mod=manutencao&ctrl=PlanosPreventiva&action=show&id=' . $planId);
    }

    public function removeVeiculo(): void
    {
        if (!has_permission('preventiva.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=planos_preventiva');
            return;
        }
        $planId = (int)($_POST['plan_id'] ?? 0);
        $veiculoId = (int)($_POST['veiculo_id'] ?? 0);
        $this->model->removeVeiculo($planId, $veiculoId);
        flash('success', 'Veiculo removido.');
        header('Location: index.php?mod=manutencao&ctrl=PlanosPreventiva&action=show&id=' . $planId);
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
        $counts = $this->model->contarVencimentos();
        View::render('Manutencao', 'preventiva/vencimentos', [
            'title' => 'Lembretes',
            'vencimentos' => $due,
            'status' => $status,
            'counts' => $counts,
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
