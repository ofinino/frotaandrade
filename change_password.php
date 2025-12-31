<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';
require_login();

$error = null;
$success = null;
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$current || !$new || !$confirm) {
        $error = 'Preencha todos os campos.';
    } elseif ($new !== $confirm) {
        $error = 'A confirmação de senha não confere.';
    } else {
        $stmt = db()->prepare('SELECT password_hash FROM seg_usuarios WHERE id = ?');
        $stmt->execute([$user['id']]);
        $hash = $stmt->fetchColumn();
        if (!$hash || !password_verify($current, $hash)) {
            $error = 'Senha atual incorreta.';
        } else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $upd = db()->prepare('UPDATE seg_usuarios SET password_hash = ? WHERE id = ?');
            $upd->execute([$newHash, $user['id']]);
            $success = 'Senha alterada com sucesso.';
        }
    }
}

render_header('Alterar senha');
?>

<div class="w-full px-4 sm:px-6 lg:px-8 py-4">
    <div class="max-w-2xl mx-auto bg-white border border-slate-200 rounded-lg shadow-sm p-6 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-lg font-semibold text-slate-900">Alterar senha</div>
                <p class="text-sm text-slate-600">Atualize sua senha de acesso.</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-100 border border-rose-300 text-rose-900 px-4 py-3 rounded text-sm"><?= sanitize($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-emerald-100 border border-emerald-300 text-emerald-900 px-4 py-3 rounded text-sm"><?= sanitize($success) ?></div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
            <div>
                <label class="block text-sm text-slate-600 mb-1">Senha atual</label>
                <input type="password" name="current_password" required class="w-full h-10 border border-slate-200 rounded px-3" />
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm text-slate-600 mb-1">Nova senha</label>
                    <input type="password" name="new_password" required class="w-full h-10 border border-slate-200 rounded px-3" />
                </div>
                <div>
                    <label class="block text-sm text-slate-600 mb-1">Confirmar nova senha</label>
                    <input type="password" name="confirm_password" required class="w-full h-10 border border-slate-200 rounded px-3" />
                </div>
            </div>
            <div class="flex items-center justify-between gap-3 pt-2">
                <a href="index.php" class="text-sm text-slate-600 hover:text-slate-800">Voltar</a>
                <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded">Salvar nova senha</button>
            </div>
        </form>
    </div>
</div>

<?php
render_footer();
