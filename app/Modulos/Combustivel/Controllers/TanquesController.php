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
        View::render('Combustivel', 'tanques/index', [
            'title' => 'Meus Tanques',
            'tanks' => $this->model->listar(),
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
        if ($this->model->excluir($id)) {
            flash('success', 'Tanque excluido.');
        } else {
            flash('error', 'Nao foi possivel excluir o tanque.');
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
        $this->model->registrarCompra([
            'tank_id' => $tankId,
            'numero_nota' => $_POST['numero_nota'] ?? null,
            'data' => $_POST['data'] ?? date('Y-m-d'),
            'valor_pago' => $_POST['valor_pago'] ?? 0,
            'quantidade' => $quantidade,
            'criado_por' => current_user()['id'] ?? null,
        ]);
        flash('success', 'Compra de combustivel registrada.');
        header('Location: index.php?page=meus_tanques');
    }
}
