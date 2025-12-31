<?php
namespace App\Modulos\Seguranca\Controllers;

use App\Core\View;
use App\Modulos\Seguranca\Models\AccessModel;

class AccessController
{
    private AccessModel $model;
    private array $features;

    public function __construct()
    {
        require_role(['admin']);
        $db = db();
        $empresaId = current_company_id();
        $this->model = new AccessModel($db, $empresaId);
        $this->features = $this->featureList();
    }

    public function index(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
            return;
        }

        $users = $this->model->listarUsuarios();
        $roles = $this->model->listarPapeis();
        $branches = $this->model->listarFiliais();
        $rolesByUser = $this->model->papeisPorUsuario();
        $branchesByUser = $this->model->filiaisPorUsuario();
        $permsByRole = $this->model->permissoesPorPapel();
        $selectedRoleId = (int)($_GET['role_id'] ?? ($roles[0]['id'] ?? 0));

        View::render('Seguranca', 'access', [
            'title' => 'Acessos',
            'users' => $users,
            'roles' => $roles,
            'branches' => $branches,
            'rolesByUser' => $rolesByUser,
            'branchesByUser' => $branchesByUser,
            'features' => $this->features,
            'permsByRole' => $permsByRole,
            'selectedRoleId' => $selectedRoleId,
        ]);
    }

    private function handlePost(): void
    {
        $currentUserId = current_user()['id'] ?? null;
        $redirect = function () {
            header('Location: index.php?page=access');
            exit;
        };

        // Criar papel
        if (isset($_POST['create_role'])) {
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            if ($nome !== '') {
                $this->model->criarPapel($nome, $descricao);
                flash('success', 'Papel criado.');
            }
            $redirect();
        }

        // Remover papel (exceto admin)
        if (isset($_POST['delete_role'])) {
            $papelId = (int)($_POST['role_id'] ?? 0);
            if ($papelId > 0) {
                $this->model->removerPapel($papelId);
                flash('success', 'Papel removido.');
                if ($currentUserId) {
                    $_SESSION['perms'] = load_user_permissions($currentUserId);
                }
            }
            $redirect();
        }

        // Salvar permissoes do papel
        if (isset($_POST['save_permissions'])) {
            $papelId = (int)($_POST['role_id'] ?? 0);
            $features = $_POST['features'] ?? [];
            if ($papelId > 0) {
                $features = array_filter(array_map('strval', (array)$features));
                $this->model->salvarPermissoes($papelId, $features);
                flash('success', 'Permissoes atualizadas.');
                if ($currentUserId) {
                    $_SESSION['perms'] = load_user_permissions($currentUserId);
                }
            }
            $redirect();
        }

        // Atribuir papel
        if (isset($_POST['assign_role'])) {
            $userId = (int)($_POST['user_id'] ?? 0);
            $roleId = (int)($_POST['role_id'] ?? 0);
            if ($userId && $roleId) {
                $this->model->atribuirPapel($userId, $roleId);
                if ($currentUserId === $userId) {
                    $_SESSION['perms'] = load_user_permissions($userId);
                }
                flash('success', 'Papel atribuido.');
            }
            $redirect();
        }

        // Remover papel do usuario
        if (isset($_POST['remove_role'])) {
            $userId = (int)($_POST['user_id'] ?? 0);
            $roleId = (int)($_POST['role_id'] ?? 0);
            if ($userId && $roleId) {
                $this->model->removerPapelUsuario($userId, $roleId);
                if ($currentUserId === $userId) {
                    $_SESSION['perms'] = load_user_permissions($userId);
                }
                flash('success', 'Papel removido.');
            }
            $redirect();
        }

        // Atribuir filial
        if (isset($_POST['assign_branch'])) {
            $userId = (int)($_POST['user_id'] ?? 0);
            $branchId = (int)($_POST['branch_id'] ?? 0);
            if ($userId && $branchId) {
                $this->model->atribuirFilial($userId, $branchId);
                if ($currentUserId === $userId) {
                    $_SESSION['branch_ids'] = load_user_branch_ids($userId, current_company_id());
                }
                flash('success', 'Filial atribuida.');
            }
            $redirect();
        }

        // Remover filial
        if (isset($_POST['remove_branch'])) {
            $userId = (int)($_POST['user_id'] ?? 0);
            $branchId = (int)($_POST['branch_id'] ?? 0);
            if ($userId && $branchId) {
                $this->model->removerFilial($userId, $branchId);
                if ($currentUserId === $userId) {
                    $_SESSION['branch_ids'] = load_user_branch_ids($userId, current_company_id());
                }
                flash('success', 'Filial removida.');
            }
            $redirect();
        }
    }

    private function featureList(): array
    {
        return [
            ['key' => 'dashboard.view', 'label' => 'Dashboard'],
            ['key' => 'checks.view', 'label' => 'Execucoes'],
            ['key' => 'templates.view', 'label' => 'Modelos'],
            ['key' => 'groups.view', 'label' => 'Grupos'],
            ['key' => 'revision_logs.view', 'label' => 'Revisoes'],
            ['key' => 'vehicles.view', 'label' => 'Veiculos'],
            ['key' => 'people.view', 'label' => 'Pessoas'],
            ['key' => 'users.view', 'label' => 'Usuarios'],
            ['key' => 'company.view', 'label' => 'Empresa'],
            ['key' => 'branches.view', 'label' => 'Filiais'],
            ['key' => 'access.view', 'label' => 'Acessos'],
        ];
    }
}
