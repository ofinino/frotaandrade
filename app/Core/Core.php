<?php
// Nucleo de conexao e helpers compartilhados.
global $config;

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Erro ao conectar no banco: ' . htmlspecialchars($e->getMessage());
    exit;
}

function db(): PDO
{
    global $pdo;
    return $pdo;
}

function sanitize($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message === null) {
        if (isset($_SESSION['flash'][$key])) {
            $msg = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $msg;
        }
        return null;
    }
    $_SESSION['flash'][$key] = $message;
    return null;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function current_company(): array
{
    return $_SESSION['company'] ?? [
        'id' => 1,
        'name' => 'Empresa',
        'display_name' => 'Empresa',
        'logo_url' => '',
    ];
}

function current_company_id(): int
{
    $user = current_user();
    return $user['company_id'] ?? 1;
}

function current_branch_id(): ?int
{
    return $_SESSION['branch_id'] ?? null;
}

function current_branch_ids(): array
{
    return $_SESSION['branch_ids'] ?? [];
}

function set_current_branch(?int $branchId): void
{
    $_SESSION['branch_id'] = $branchId;
    if ($branchId !== null) {
        $_SESSION['branch_ids'] = array_values(array_unique(array_merge($_SESSION['branch_ids'] ?? [], [$branchId])));
    }
}

function login_user(array $user, array $modules = [], array $company = [], ?int $branchId = null): void
{
    $_SESSION['user'] = [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'company_id' => $user['company_id'] ?? 1,
        'modules' => $modules,
    ];

    if ($company) {
        $_SESSION['company'] = $company;
    }
    if ($branchId !== null) {
        $_SESSION['branch_id'] = $branchId;
    }
    if (!isset($_SESSION['branch_ids'])) {
        $_SESSION['branch_ids'] = $branchId ? [$branchId] : [];
    }
}

function logout_user(): void
{
    session_destroy();
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: login.php');
        exit;
    }
}

function is_admin(): bool
{
    return current_user() && current_user()['role'] === 'admin';
}

function can_manage_role(string $role): bool
{
    if (is_admin()) {
        return true;
    }
    return $role === 'membro';
}

function has_module(string $key): bool
{
    $user = current_user();
    $modules = $user['modules'] ?? [];
    return in_array($key, $modules, true);
}

function has_permission(string $perm): bool
{
    if (is_admin()) {
        return true;
    }

    // Compatibilidade: mapear permissoes antigas para novas features
    $alias = [
        'checklist.ver' => 'checks.view',
        'checklist.editar' => 'checks.view',
        'checklist.executar' => 'checks.view',
        'templates.gerenciar' => 'templates.view',
        'groups.gerenciar' => 'groups.view',
        'revision_logs.ver' => 'revision_logs.view',
        'veiculos.gerenciar' => 'vehicles.view',
        'pessoas.gerenciar' => 'people.view',
        'usuarios.gerenciar' => 'users.view',
        'empresa.gerenciar' => 'company.view',
        'filiais.gerenciar' => 'branches.view',
        'acessos.gerenciar' => 'access.view',
    ];
    $reverse = [
        'checks.view' => ['checklist.ver', 'checklist.editar', 'checklist.executar'],
        'templates.view' => ['templates.gerenciar'],
        'groups.view' => ['groups.gerenciar'],
        'revision_logs.view' => ['revision_logs.ver'],
        'vehicles.view' => ['veiculos.gerenciar'],
        'people.view' => ['pessoas.gerenciar'],
        'users.view' => ['usuarios.gerenciar'],
        'company.view' => ['empresa.gerenciar'],
        'branches.view' => ['filiais.gerenciar'],
        'access.view' => ['acessos.gerenciar'],
    ];
    $key = $alias[$perm] ?? $perm;

    $perms = $_SESSION['perms'] ?? [];
    if (in_array($key, $perms, true)) {
        return true;
    }
    if (isset($reverse[$key])) {
        foreach ($reverse[$key] as $legacyKey) {
            if (in_array($legacyKey, $perms, true)) {
                return true;
            }
        }
    }
    return false;
}

function require_permission(string $perm): void
{
    if (!has_permission($perm)) {
        flash('error', 'Sem permissao: ' . $perm);
        header('Location: index.php');
        exit;
    }
}

function load_user_permissions(int $userId): array
{
    $sql = 'SELECT DISTINCT pp.feature
            FROM seg_usuario_papel up
            INNER JOIN seg_papel_permissoes pp ON pp.papel_id = up.papel_id AND pp.allow = 1
            WHERE up.user_id = ?';
    $stmt = db()->prepare($sql);
    $stmt->execute([$userId]);
    $features = array_column($stmt->fetchAll(), 'feature');

    // Fallback para esquema antigo (seg_usuario_roles + seg_role_permissao + seg_permissoes)
    if (!$features) {
        try {
            $legacy = db()->prepare('SELECT p.chave
                                     FROM seg_usuario_roles ur
                                     INNER JOIN seg_role_permissao rp ON rp.role_id = ur.role_id
                                     INNER JOIN seg_permissoes p ON p.id = rp.permissao_id
                                     WHERE ur.user_id = ?');
            $legacy->execute([$userId]);
            $features = array_column($legacy->fetchAll(), 'chave');
        } catch (\Throwable $e) {
            // ignora erro de tabela ausente
        }
    }

    // fallback final: papel com mesmo nome do campo role do usuario
    if (!$features) {
        try {
            $roleStmt = db()->prepare('SELECT role FROM seg_usuarios WHERE id = ?');
            $roleStmt->execute([$userId]);
            $roleName = $roleStmt->fetchColumn();
            if ($roleName) {
                $pstmt = db()->prepare('SELECT pp.feature
                                        FROM seg_papeis p
                                        INNER JOIN seg_papel_permissoes pp ON pp.papel_id = p.id AND pp.allow = 1
                                        WHERE p.nome = ?');
                $pstmt->execute([$roleName]);
                $features = array_column($pstmt->fetchAll(), 'feature');
            }
        } catch (\Throwable $e) {
            // silencioso
        }
    }

    return array_values(array_unique($features));
}

function load_user_branch_ids(int $userId, int $companyId): array
{
    $sql = 'SELECT filial_id FROM seg_usuario_filiais WHERE user_id = ? AND empresa_id = ?';
    $stmt = db()->prepare($sql);
    $stmt->execute([$userId, $companyId]);
    $ids = array_map('intval', array_column($stmt->fetchAll(), 'filial_id'));
    return array_values(array_unique($ids));
}

function require_role(array $roles): void
{
    $user = current_user();
    if (!$user || !in_array($user['role'], $roles, true)) {
        flash('error', 'Sem permissao para acessar.');
        header('Location: index.php');
        exit;
    }
}

function format_role(string $role): string
{
    $map = [
        'admin' => 'Admin',
        'gestor' => 'Gestor',
        'lider' => 'Lider',
        'executante' => 'Executante',
        'membro' => 'Membro',
    ];
    return $map[$role] ?? $role;
}

function options_from_text(?string $options): array
{
    if (!$options) {
        return [];
    }
    $decoded = json_decode($options, true);
    if (is_array($decoded)) {
        return $decoded;
    }
    return array_filter(array_map('trim', explode(',', $options)));
}

function upload_base_dir(): string
{
    global $config;
    $dir = $config['uploads_path'] ?? (__DIR__ . '/../../uploads');
    return rtrim($dir, "/\\");
}

function ensure_upload_dir(): string
{
    $dir = upload_base_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function asset_url(string $path): string
{
    global $config;
    $base = rtrim($config['base_url'] ?? '', '/');
    $cleanPath = ltrim($path, '/');
    $prefix = $base ? $base . '/' : '/';
    return $prefix . $cleanPath;
}
