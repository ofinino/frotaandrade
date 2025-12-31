<?php
namespace App\Modulos\Cadastros\Controllers;

use App\Core\View;
use App\Modulos\Cadastros\Models\GroupsModel;

class GroupsController
{
    private GroupsModel $model;

    public function __construct()
    {
        require_role(['admin','gestor','lider','executante','membro']);
        $db = db();
        $empresaId = current_company_id();
        $filialId = current_branch_id();
        $filiais = current_branch_ids();
        $this->model = new GroupsModel($db, $empresaId, $filialId, $filiais);
    }

    public function index(): void
    {
        if (!has_permission('templates.view')) {
            flash('error', 'Sem permissão para gerenciar grupos.');
            header('Location: index.php');
            exit;
        }

        $editingId = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
        $editingGroup = null;
        if ($editingId) {
            $editingGroup = $this->model->obter($editingId);
            if (!$editingGroup) {
                flash('error', 'Grupo não encontrado.');
                header('Location: index.php?page=groups');
                return;
            }
        }

        if (isset($_GET['delete'])) {
            $this->delete((int)$_GET['delete']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->save($editingId);
            return;
        }

        $groups = $this->model->listar();
        View::render('Cadastros', 'groups', [
            'title' => 'Grupos de checklist',
            'groups' => $groups,
            'editingGroup' => $editingGroup,
        ]);
    }

    private function save(?int $editingId = null): void
    {
        $nome = trim($_POST['nome'] ?? '');
        $tipo = trim($_POST['tipo'] ?? 'outro');
        $filialId = current_branch_id();
        if ($nome === '') {
            flash('error', 'Nome do grupo é obrigatório.');
            header('Location: index.php?page=groups');
            return;
        }
        try {
            if ($editingId) {
                $this->model->atualizar($editingId, [
                    'nome' => $nome,
                    'tipo' => $tipo ?: 'outro',
                ]);
                flash('success', 'Grupo atualizado.');
            } else {
                $this->model->criar([
                    'nome' => $nome,
                    'tipo' => $tipo ?: 'outro',
                    'filial_id' => $filialId,
                ]);
                flash('success', 'Grupo salvo.');
            }
        } catch (\Throwable $e) {
            flash('error', 'Erro ao salvar grupo: ' . $e->getMessage());
        }
        header('Location: index.php?page=groups');
    }

    private function delete(int $id): void
    {
        try {
            $deleted = $this->model->excluir($id);
            flash($deleted ? 'success' : 'error', $deleted ? 'Grupo removido.' : 'Não foi possível remover.');
        } catch (\Throwable $e) {
            flash('error', 'Erro ao remover grupo: ' . $e->getMessage());
        }
        header('Location: index.php?page=groups');
    }
}
