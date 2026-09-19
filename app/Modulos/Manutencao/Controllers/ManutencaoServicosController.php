<?php
namespace App\Modulos\Manutencao\Controllers;

use App\Core\View;
use App\Modulos\Manutencao\Models\ManutencaoServicosModel;

class ManutencaoServicosController
{
    private ManutencaoServicosModel $model;

    public function __construct()
    {
        $this->model = new ManutencaoServicosModel(db(), current_company_id(), current_branch_id());
    }

    public function index(): void
    {
        if (!has_permission('preventiva.view')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php');
            return;
        }
        View::render('Manutencao', 'preventiva/servicos_index', [
            'title' => 'Serviços de manutenção',
            'servicos' => $this->model->listar(),
        ]);
    }

    public function store(): void
    {
        if (!has_permission('preventiva.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=manutencao_servicos');
            return;
        }
        $nome = trim($_POST['nome'] ?? '');
        if ($nome === '') {
            flash('error', 'Informe o nome do serviço.');
        } else {
            $this->model->criar($nome);
            flash('success', 'Serviço criado.');
        }
        header('Location: index.php?page=manutencao_servicos');
    }

    public function toggle(): void
    {
        if (!has_permission('preventiva.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=manutencao_servicos');
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $ativo = !empty($_POST['ativo']);
        $this->model->toggleAtivo($id, $ativo);
        flash('success', 'Serviço atualizado.');
        header('Location: index.php?page=manutencao_servicos');
    }
}
