<?php
// Tela de login - versão corrigida em UTF-8
require_once __DIR__ . '/bootstrap.php';

if (current_user()) {
    header('Location: index.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $stmt = db()->prepare('SELECT id, name, email, password_hash, role, empresa_id AS company_id, filial_id FROM seg_usuarios WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $companyId = $user['company_id'] ?? 1;

            // Garante módulo checklist habilitado para admins
            $modsStmt = db()->prepare('SELECT module_key FROM seg_empresa_modulos WHERE empresa_id = ?');
            $modsStmt->execute([$companyId]);
            $modules = array_column($modsStmt->fetchAll(), 'module_key');
            if (!in_array('checklist', $modules, true) && $user['role'] === 'admin') {
                db()->prepare('INSERT IGNORE INTO seg_empresa_modulos (empresa_id, module_key, created_at) VALUES (?, "checklist", NOW())')
                    ->execute([$companyId]);
                $modsStmt->execute([$companyId]);
                $modules = array_column($modsStmt->fetchAll(), 'module_key');
            }

            // Dados da empresa e filial
            $companyStmt = db()->prepare('SELECT * FROM cad_empresas WHERE id = ?');
            $companyStmt->execute([$companyId]);
            $company = $companyStmt->fetch() ?: [];

            $branchId = $user['filial_id'] ?? null;
            if (!$branchId) {
                $branchStmt = db()->prepare('SELECT id FROM cad_filiais WHERE empresa_id = ? ORDER BY id ASC LIMIT 1');
                $branchStmt->execute([$companyId]);
                $branchId = $branchStmt->fetchColumn() ?: null;
            }

            // Permissões e filiais
            $perms = load_user_permissions((int)$user['id']);
            $userBranches = load_user_branch_ids((int)$user['id'], $companyId);
            if ($userBranches) {
                $branchId = $branchId ?: $userBranches[0];
            }

            if (!in_array('checklist', $modules, true)) {
                $error = 'Módulo de checklist não liberado para esta empresa.';
            } else {
                $_SESSION['perms'] = $perms;
                $_SESSION['branch_ids'] = $userBranches;
                login_user($user, $modules, $company, $branchId ? (int)$branchId : null);
                header('Location: index.php');
                exit;
            }
        }
    }

    $error = $error ?: 'Credenciais inválidas';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Login</title>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 flex items-center justify-center px-4">
    <div class="w-full max-w-md bg-white/90 backdrop-blur shadow-xl rounded-lg p-8 space-y-6">
        <div class="text-center">
            <div class="text-2xl font-semibold text-slate-900">Checklist de Veículos</div>
            <p class="text-sm text-slate-500">Acesse para gerenciar frota e checklists</p>
        </div>
        <?php if ($error): ?>
            <div class="bg-rose-100 border border-rose-300 text-rose-900 px-4 py-3 rounded"><?= sanitize($error) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('success')): ?>
            <div class="bg-emerald-100 border border-emerald-300 text-emerald-900 px-4 py-3 rounded"><?= sanitize($msg) ?></div>
        <?php endif; ?>
        <form method="post" class="space-y-4">
            <div>
                <label class="block text-sm text-slate-600 mb-1">Email</label>
                <input type="email" name="email" required class="w-full rounded border border-slate-200 px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none" />
            </div>
            <div>
                <label class="block text-sm text-slate-600 mb-1">Senha</label>
                <input type="password" name="password" required class="w-full rounded border border-slate-200 px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none" />
            </div>
            <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded">Entrar</button>
        </form>
        <p class="text-xs text-slate-500 text-center">Crie um usuário admin diretamente no banco caso ainda não exista.</p>
    </div>
</body>
</html>
