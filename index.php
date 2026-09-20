<?php
require_once __DIR__ . '/bootstrap.php';

$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$isAppHost = strpos($host, 'app.') === 0;
$hasAppParams = isset($_GET['page']) || isset($_GET['mod']) || isset($_GET['ctrl']);

if ($isAppHost || $hasAppParams) {
    if (!isset($_GET['mod']) || !isset($_GET['ctrl'])) {
        $page = (string)($_GET['page'] ?? 'dashboard');
        $action = (string)($_GET['action'] ?? 'index');

        $routes = [
            'dashboard' => ['mod' => 'cadastros', 'ctrl' => 'Dashboard', 'action' => 'index', 'actions' => ['index']],
            'checks' => ['mod' => 'manutencao', 'ctrl' => 'Execucoes', 'action' => 'index', 'actions' => ['index']],
            'run_check' => ['mod' => 'manutencao', 'ctrl' => 'Execucoes', 'action' => 'run', 'actions' => ['run']],
            'report' => ['mod' => 'manutencao', 'ctrl' => 'Reports', 'action' => 'show', 'actions' => ['show']],
            'media' => ['mod' => 'manutencao', 'ctrl' => 'Media', 'action' => 'serve', 'actions' => ['serve']],
            'videos' => ['mod' => 'manutencao', 'ctrl' => 'Videos', 'action' => 'index', 'actions' => ['index']],
            'servicos' => ['mod' => 'manutencao', 'ctrl' => 'SolicitacoesServico', 'action' => 'index', 'actions' => ['index', 'create', 'show']],
            'os' => ['mod' => 'manutencao', 'ctrl' => 'OrdensServico', 'action' => 'index', 'actions' => ['index', 'create', 'show', 'store', 'assignExecutor', 'changeStatus', 'changeStatusDrag', 'addItem', 'addLabor', 'addPart', 'updateItemStatus', 'linkServiceRequests', 'addPendencia', 'resolvePendencia']],
            'templates' => ['mod' => 'manutencao', 'ctrl' => 'Checklists', 'action' => 'index', 'actions' => ['index']],
            'revision_logs' => ['mod' => 'manutencao', 'ctrl' => 'Checklists', 'action' => 'logs', 'actions' => ['logs']],
            'groups' => ['mod' => 'cadastros', 'ctrl' => 'Groups', 'action' => 'index', 'actions' => ['index']],
            'vehicles' => ['mod' => 'cadastros', 'ctrl' => 'Vehicles', 'action' => 'index', 'actions' => ['index']],
            'people' => ['mod' => 'cadastros', 'ctrl' => 'People', 'action' => 'index', 'actions' => ['index']],
            'users' => ['mod' => 'seguranca', 'ctrl' => 'Users', 'action' => 'index', 'actions' => ['index']],
            'company' => ['mod' => 'cadastros', 'ctrl' => 'Company', 'action' => 'index', 'actions' => ['index']],
            'branches' => ['mod' => 'cadastros', 'ctrl' => 'Branches', 'action' => 'index', 'actions' => ['index']],
            'access' => ['mod' => 'seguranca', 'ctrl' => 'Access', 'action' => 'index', 'actions' => ['index']],
            'backup' => ['mod' => 'seguranca', 'ctrl' => 'Backup', 'action' => 'index', 'actions' => ['index']],
            'cleanup' => ['mod' => 'seguranca', 'ctrl' => 'Cleanup', 'action' => 'index', 'actions' => ['index']],
            'planos_preventiva' => ['mod' => 'manutencao', 'ctrl' => 'PlanosPreventiva', 'action' => 'index', 'actions' => ['index', 'create', 'store', 'show', 'publish', 'toggleStatus', 'addService', 'removeService', 'addVeiculo', 'removeVeiculo', 'run']],
            'vencimentos_preventiva' => ['mod' => 'manutencao', 'ctrl' => 'PlanosPreventiva', 'action' => 'vencimentos', 'actions' => ['vencimentos']],
            'manutencao_servicos' => ['mod' => 'manutencao', 'ctrl' => 'ManutencaoServicos', 'action' => 'index', 'actions' => ['index', 'store', 'toggle']],
            'abastecimentos' => ['mod' => 'combustivel', 'ctrl' => 'Abastecimentos', 'action' => 'index', 'actions' => ['index', 'create', 'store', 'destroy', 'success']],
            'meus_tanques' => ['mod' => 'combustivel', 'ctrl' => 'Tanques', 'action' => 'index', 'actions' => ['index', 'store', 'update', 'destroy', 'toggleAtivo', 'addPurchase', 'updatePurchase', 'destroyPurchase']],
            'combustivel_tipos' => ['mod' => 'combustivel', 'ctrl' => 'FuelTypes', 'action' => 'index', 'actions' => ['index', 'store', 'toggle', 'update', 'destroy']],
        ];

        if (!isset($routes[$page])) {
            $page = 'dashboard';
        }

        $route = $routes[$page];
        $allowedActions = $route['actions'] ?? [$route['action']];
        if (!in_array($action, $allowedActions, true)) {
            $action = $route['action'];
        }

        $_GET['page'] = $page;
        $_GET['mod'] = $route['mod'];
        $_GET['ctrl'] = $route['ctrl'];
        $_GET['action'] = $action;
    }

    \App\Core\Router::dispatch();
    exit;
}

header('Location: ' . (current_user() ? 'index.php?page=dashboard' : 'login.php'));
exit;
