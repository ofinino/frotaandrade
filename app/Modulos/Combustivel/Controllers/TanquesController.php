<?php
namespace App\Modulos\Combustivel\Controllers;

use App\Core\View;
use App\Modulos\Combustivel\Models\FuelTanksModel;

class TanquesController
{
    private FuelTanksModel $model;

    public function __construct()
    {
        $this->model = new FuelTanksModel(db(), current_company_id(), current_branch_id());
    }

    public function index(): void
    {
        if (!has_permission('combustivel.view')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php');
            return;
        }
        $filters = [
            'tank_id' => $_GET['tank_id'] ?? null,
            'de' => $_GET['de'] ?? null,
            'ate' => $_GET['ate'] ?? null,
        ];
        $tanks = $this->model->listar();
        $temHistorico = [];
        foreach ($tanks as $t) {
            $temHistorico[$t['id']] = $this->model->temHistorico((int)$t['id']);
        }
        View::render('Combustivel', 'tanques/index', [
            'title' => 'Meus Tanques',
            'tanks' => $tanks,
            'tanksAtivos' => $this->model->listarAtivos(),
            'temHistorico' => $temHistorico,
            'compras' => $this->model->listarCompras($filters),
            'filters' => $filters,
        ]);
    }

    public function store(): void
    {
        if (!has_permission('combustivel.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=meus_tanques');
            return;
        }
        $nome = trim($_POST['nome'] ?? '');
        $capacidade = $_POST['capacidade_maxima'] ?? 0;
        $estoque = $_POST['estoque_inicial'] ?? 0;
        if ($nome === '') {
            flash('error', 'Informe o nome do tanque.');
        } else {
            $this->model->criar(['nome' => $nome, 'capacidade_maxima' => $capacidade, 'estoque_inicial' => $estoque]);
            flash('success', 'Tanque criado.');
        }
        header('Location: index.php?page=meus_tanques');
    }

    public function update(): void
    {
        if (!has_permission('combustivel.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=meus_tanques');
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $capacidade = $_POST['capacidade_maxima'] ?? 0;
        if ($id && $nome !== '' && $this->model->atualizar($id, ['nome' => $nome, 'capacidade_maxima' => $capacidade])) {
            flash('success', 'Tanque atualizado.');
        } else {
            flash('error', 'Nao foi possivel atualizar o tanque.');
        }
        header('Location: index.php?page=meus_tanques');
    }

    public function destroy(): void
    {
        if (!has_permission('combustivel.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=meus_tanques');
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        try {
            if ($this->model->excluir($id)) {
                flash('success', 'Tanque excluido.');
            } else {
                flash('error', 'Nao foi possivel excluir o tanque.');
            }
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        header('Location: index.php?page=meus_tanques');
    }

    public function toggleAtivo(): void
    {
        if (!has_permission('combustivel.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=meus_tanques');
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $ativo = !empty($_POST['ativo']);
        if ($this->model->toggleAtivo($id, $ativo)) {
            flash('success', $ativo ? 'Tanque reativado.' : 'Tanque desativado.');
        } else {
            flash('error', 'Nao foi possivel atualizar o tanque.');
        }
        header('Location: index.php?page=meus_tanques');
    }

    public function updatePurchase(): void
    {
        if (!has_permission('combustivel.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=meus_tanques');
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $quantidade = $_POST['quantidade'] ?? 0;
        if (!$id || !is_numeric($quantidade) || (float)$quantidade <= 0) {
            flash('error', 'Informe uma quantidade valida.');
            header('Location: index.php?page=meus_tanques');
            return;
        }
        try {
            if ($this->model->atualizarCompra($id, [
                'numero_nota' => $_POST['numero_nota'] ?? null,
                'data' => $_POST['data'] ?? date('Y-m-d'),
                'valor_pago' => $_POST['valor_pago'] ?? 0,
                'quantidade' => $quantidade,
            ])) {
                flash('success', 'Compra atualizada.');
            } else {
                flash('error', 'Compra nao encontrada.');
            }
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        header('Location: index.php?page=meus_tanques');
    }

    public function destroyPurchase(): void
    {
        if (!has_permission('combustivel.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=meus_tanques');
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        try {
            if ($this->model->excluirCompra($id)) {
                flash('success', 'Compra excluida.');
            } else {
                flash('error', 'Compra nao encontrada.');
            }
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        header('Location: index.php?page=meus_tanques');
    }

    public function addPurchase(): void
    {
        if (!has_permission('combustivel.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=meus_tanques');
            return;
        }
        $tankId = (int)($_POST['tank_id'] ?? 0);
        $quantidade = $_POST['quantidade'] ?? 0;
        if (!$tankId || !is_numeric($quantidade) || (float)$quantidade <= 0) {
            flash('error', 'Selecione um tanque e informe uma quantidade valida.');
            header('Location: index.php?page=meus_tanques');
            return;
        }
        try {
            $this->model->registrarCompra([
                'tank_id' => $tankId,
                'numero_nota' => $_POST['numero_nota'] ?? null,
                'data' => $_POST['data'] ?? date('Y-m-d'),
                'valor_pago' => $_POST['valor_pago'] ?? 0,
                'quantidade' => $quantidade,
                'criado_por' => current_user()['id'] ?? null,
            ]);
            flash('success', 'Compra de combustivel registrada.');
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        header('Location: index.php?page=meus_tanques');
    }
}
