<?php
namespace App\Modulos\Cadastros\Controllers;

use App\Core\View;

class BranchesController
{
    public function index(): void
    {
        require_role(['admin']);
        $db = db();
        $companyId = current_company_id();
        $currentBranch = current_branch_id();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            if ($name === '') {
                flash('error', 'Nome da filial obrigatório.');
                header('Location: index.php?page=branches');
                return;
            }
            $db->prepare('INSERT INTO cad_filiais (empresa_id, name, created_at) VALUES (?, ?, NOW())')
                ->execute([$companyId, $name]);
            if (is_admin()) {
                $db->prepare('INSERT IGNORE INTO seg_usuario_filiais (user_id, empresa_id, filial_id) VALUES (?, ?, LAST_INSERT_ID())')
                    ->execute([current_user()['id'], $companyId]);
            }
            flash('success', 'Filial criada.');
            header('Location: index.php?page=branches');
            return;
        }

        if (isset($_GET['set'])) {
            $branchId = (int) $_GET['set'];
            $allowed = current_branch_ids();
            $stmt = $db->prepare('SELECT id FROM cad_filiais WHERE id = ? AND empresa_id = ?');
            $stmt->execute([$branchId, $companyId]);
            if ($stmt->fetchColumn() && (empty($allowed) || in_array($branchId, $allowed, true) || is_admin())) {
                set_current_branch($branchId);
                flash('success', 'Filial selecionada.');
            } else {
                flash('error', 'Filial não encontrada ou sem acesso.');
            }
            header('Location: index.php?page=branches');
            return;
        }

        $branchesStmt = $db->prepare('SELECT * FROM cad_filiais WHERE empresa_id = ? ORDER BY id ASC');
        $branchesStmt->execute([$companyId]);
        $branches = $branchesStmt->fetchAll();

        View::render('Cadastros', 'branches', [
            'title' => 'Filiais',
            'branches' => $branches,
            'currentBranch' => $currentBranch,
        ]);
    }
}
?>
