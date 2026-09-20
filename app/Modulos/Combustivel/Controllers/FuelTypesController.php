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
        $tipos = $this->model->listar();
        $temUso = [];
        foreach ($tipos as $t) {
            $temUso[$t['id']] = $this->model->temUso((int)$t['id']);
        }
        View::render('Combustivel', 'tipos/index', [
            'title' => 'Tipos de combustível',
            'tipos' => $tipos,
            'temUso' => $temUso,
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

    public function update(): void
    {
        if (!has_permission('combustivel.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=combustivel_tipos');
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        if (!$id || $nome === '') {
            flash('error', 'Informe um nome valido.');
        } elseif ($this->model->atualizar($id, $nome)) {
            flash('success', 'Tipo de combustível atualizado.');
        } else {
            flash('error', 'Tipo de combustível nao encontrado.');
        }
        header('Location: index.php?page=combustivel_tipos');
    }

    public function destroy(): void
    {
        if (!has_permission('combustivel.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=combustivel_tipos');
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        try {
            if ($this->model->excluir($id)) {
                flash('success', 'Tipo de combustível excluido.');
            } else {
                flash('error', 'Tipo de combustível nao encontrado.');
            }
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        header('Location: index.php?page=combustivel_tipos');
    }
}
