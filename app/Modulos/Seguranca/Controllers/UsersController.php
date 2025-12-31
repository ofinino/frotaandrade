<?php
namespace App\Modulos\Seguranca\Controllers;

use App\Core\View;
use App\Modulos\Seguranca\Models\UsersModel;

class UsersController
{
    private UsersModel $model;

    public function __construct()
    {
        require_role(['admin', 'gestor', 'lider', 'executante', 'membro']);
        $db = db();
        $empresaId = current_company_id();
        $filialId = current_branch_id();
        $this->model = new UsersModel($db, $empresaId, $filialId);
    }

    public function index(): void
    {
        if (!has_permission('users.view')) {
            flash('error', 'Sem permissão para gerenciar usuários.');
            header('Location: index.php');
            return;
        }

        $current = current_user();
        $companyId = current_company_id();
        $branchId = current_branch_id();
        $editingId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;

        if (isset($_GET['delete'])) {
            $this->deleteUser((int) $_GET['delete'], $current);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->saveUser($editingId, $branchId, $companyId, $current);
            return;
        }

        $users = $this->model->listarUsuarios($branchId, is_admin());
        $editUser = null;
        if ($editingId) {
            foreach ($users as $u) {
                if ((int)$u['id'] === $editingId) {
                    $editUser = $u;
                    break;
                }
            }
        }
        $roles = ['admin', 'gestor', 'lider', 'executante', 'membro'];

        View::render('Seguranca', 'users', [
            'title' => 'Usuários',
            'users' => $users,
            'editUser' => $editUser,
            'roles' => $roles,
            'current' => $current,
        ]);
    }

    private function saveUser(?int $editingId, ?int $branchId, int $companyId, array $current): void
    {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'membro';
        $password = $_POST['password'] ?? '';

        if (!can_manage_role($role)) {
            flash('error', 'Apenas admin pode criar ou editar usuários desse tipo.');
            $role = 'membro';
        }
        if ($name === '' || $email === '') {
            flash('error', 'Nome e email são obrigatórios.');
            header('Location: index.php?page=users' . ($editingId ? '&edit=' . $editingId : ''));
            return;
        }

        if ($editingId) {
            $targetUser = $this->model->obterUsuario($editingId);
            if (!$targetUser) {
                flash('error', 'Usuário não encontrado.');
                header('Location: index.php?page=users');
                return;
            }
            if (!can_manage_role($targetUser['role'])) {
                flash('error', 'Apenas admin pode alterar esse usuário.');
                header('Location: index.php?page=users');
                return;
            }
            $passwordHash = $password ? password_hash($password, PASSWORD_DEFAULT) : null;
            $this->model->atualizar($editingId, [
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'password_hash' => $passwordHash,
            ]);
            flash('success', 'Usuário atualizado.');
        } else {
            if ($password === '') {
                flash('error', 'Defina uma senha para o novo usuário.');
                header('Location: index.php?page=users');
                return;
            }
            if ($this->model->emailExiste($email)) {
                flash('error', 'Já existe usuário com esse email.');
                header('Location: index.php?page=users');
                return;
            }
            $this->model->criar([
                'filial_id' => $branchId,
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
            ]);
            flash('success', 'Usuário criado.');
        }
        header('Location: index.php?page=users');
    }

    private function deleteUser(int $deleteId, array $current): void
    {
        if ($deleteId === $current['id']) {
            flash('error', 'Você não pode excluir a si mesmo.');
            header('Location: index.php?page=users');
            return;
        }
        $targetUser = $this->model->obterUsuario($deleteId);
        if (!$targetUser) {
            flash('error', 'Usuário não encontrado.');
            header('Location: index.php?page=users');
            return;
        }
        if (!can_manage_role($targetUser['role'])) {
            flash('error', 'Apenas admin pode excluir esse usuário.');
            header('Location: index.php?page=users');
            return;
        }
        $this->model->excluir($deleteId);
        flash('success', 'Usuário removido.');
        header('Location: index.php?page=users');
    }
}
