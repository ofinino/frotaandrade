<?php
namespace App\Modulos\Combustivel\Controllers;

use App\Core\View;
use App\Modulos\Combustivel\Models\FuelTypesModel;

class FuelTypesController
{
    private FuelTypesModel $model;

    public function __construct()
    {
        $this->model = new FuelTypesModel(db(), current_company_id(), current_branch_id());
    }

    public function index(): void
    {
        if (!has_permission('combustivel.view')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php');
            return;
        }
        View::render('Combustivel', 'tipos/index', [
            'title' => 'Tipos de combustível',
            'tipos' => $this->model->listar(),
        ]);
    }

    public function store(): void
    {
        if (!has_permission('combustivel.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=combustivel_tipos');
            return;
        }
        $nome = trim($_POST['nome'] ?? '');
        if ($nome === '') {
            flash('error', 'Informe o nome do tipo de combustível.');
        } else {
            $this->model->criar($nome);
            flash('success', 'Tipo de combustível criado.');
        }
        header('Location: index.php?page=combustivel_tipos');
    }

    public function toggle(): void
    {
        if (!has_permission('combustivel.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=combustivel_tipos');
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $ativo = !empty($_POST['ativo']);
        $this->model->toggleAtivo($id, $ativo);
        flash('success', 'Tipo de combustível atualizado.');
        header('Location: index.php?page=combustivel_tipos');
    }
}
