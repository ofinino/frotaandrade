<?php
namespace App\Modulos\Cadastros\Controllers;

use App\Core\View;
use App\Modulos\Cadastros\Models\PeopleModel;

class PeopleController
{
    private PeopleModel $model;

    public function __construct()
    {
        require_role(['admin','gestor','lider','executante','membro']);
        $db = db();
        $empresaId = current_company_id();
        $filialId = current_branch_id();
        $filiais = current_branch_ids();
        $this->model = new PeopleModel($db, $empresaId, $filialId, $filiais);
    }

    public function index(): void
    {
        if (!has_permission('people.view')) {
            flash('error', 'Sem permissao para gerenciar pessoas.');
            header('Location: index.php');
            exit;
        }

        $editingId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;

        if (isset($_GET['delete'])) {
            $this->delete((int) $_GET['delete']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->save($editingId);
            return;
        }

        try {
            $people = $this->model->listar();
        } catch (\Throwable $e) {
            flash('error', 'Erro ao carregar pessoas: ' . $e->getMessage());
            $people = [];
        }
        $editPerson = null;
        if ($editingId) {
            foreach ($people as $p) {
                if ((int)$p['id'] === $editingId) {
                    $editPerson = $p;
                    break;
                }
            }
        }

        View::render('Cadastros', 'people', [
            'title' => 'Pessoas',
            'people' => $people,
            'editPerson' => $editPerson,
        ]);
    }

    private function save(?int $editingId): void
    {
        $name = trim($_POST['name'] ?? '');
        $document = trim($_POST['document'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($name === '') {
            flash('error', 'Nome e obrigatorio.');
            header('Location: index.php?page=people' . ($editingId ? '&edit=' . $editingId : ''));
            exit;
        }

        if ($editingId) {
            $this->model->atualizar($editingId, [
                'name' => $name,
                'document' => $document,
                'phone' => $phone,
                'email' => $email,
            ]);
            flash('success', 'Pessoa atualizada.');
        } else {
            $this->model->criar([
                'name' => $name,
                'document' => $document,
                'phone' => $phone,
                'email' => $email,
            ]);
            flash('success', 'Pessoa criada.');
        }

        header('Location: index.php?page=people');
        exit;
    }

    private function delete(int $id): void
    {
        try {
            $this->model->excluir($id);
            flash('success', 'Pessoa removida.');
        } catch (\Throwable $e) {
            flash('error', 'Erro ao remover pessoa: ' . $e->getMessage());
        }
        header('Location: index.php?page=people');
        exit;
    }
}
