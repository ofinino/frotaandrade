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

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
            $this->delete((int) $_POST['delete']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->save($editingId);
            return;
        }

        try {
            $people = $this->model->listar();
        } catch (\Throwable $e) {
            flash_error('Erro ao carregar pessoas.', $e);
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
        $nomeCompleto = trim($_POST['nome_completo'] ?? '');
        $nomeAbreviado = trim($_POST['nome_abreviado'] ?? '');
        $emailFunc = trim($_POST['email_func'] ?? '');
        $telefoneFunc = trim($_POST['telefone_func'] ?? '');
        $cpf = trim($_POST['cpf'] ?? '');
        $rg = trim($_POST['rg'] ?? '');
        $sexo = $_POST['sexo'] ?? '';
        $funcaoId = trim($_POST['funcao_id'] ?? '');
        $dataNascimento = trim($_POST['data_nascimento'] ?? '');

        if ($nomeCompleto === '') {
            flash('error', 'Nome e obrigatorio.');
            header('Location: index.php?page=people' . ($editingId ? '&edit=' . $editingId : ''));
            exit;
        }

        $data = [
            'nome_completo' => $nomeCompleto,
            'nome_abreviado' => $nomeAbreviado !== '' ? $nomeAbreviado : null,
            'email_func' => $emailFunc !== '' ? $emailFunc : null,
            'telefone_func' => $telefoneFunc !== '' ? $telefoneFunc : null,
            'cpf' => $cpf !== '' ? $cpf : null,
            'rg' => $rg !== '' ? $rg : null,
            'sexo' => $sexo !== '' ? $sexo : null,
            'funcao_id' => $funcaoId !== '' ? (int) $funcaoId : null,
            'data_nascimento' => $dataNascimento !== '' ? $dataNascimento : null,
        ];

        if ($editingId) {
            $this->model->atualizar($editingId, $data);
            flash('success', 'Pessoa atualizada.');
        } else {
            $data['filial_id'] = current_branch_id();
            $data['criado_por'] = current_user()['id'] ?? null;
            $this->model->criar($data);
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
            flash_error('Erro ao remover pessoa.', $e);
        }
        header('Location: index.php?page=people');
        exit;
    }
}
