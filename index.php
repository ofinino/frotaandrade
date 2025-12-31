<?php
// Roteamento principal para o monólito modular
require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/app/Core/Router.php';

// Se já vier mod/ctrl/action (ex.: chamadas diretas ou futura API), exige login e despacha
if (isset($_GET['mod'])) {
    if (!current_user()) {
        header('Location: login.php');
        exit;
    }
    App\Core\Router::dispatch();
    exit;
}

// Login obrigatório para qualquer página; se não logado, mostra o formulário de login
if (!current_user()) {
    require __DIR__ . '/login.php';
    exit;
}

// Página padrão conforme perfil
$user = current_user();
$defaultPage = ($user && $user['role'] === 'executante' && has_permission('checks.view')) ? 'checks' : 'dashboard';
$page = $_GET['page'] ?? $defaultPage;

// Mapa de page => rota modular
$pageRoutes = [
    'checks'    => ['mod' => 'manutencao', 'ctrl' => 'Execucoes', 'action' => 'index'],
    'run_check' => ['mod' => 'manutencao', 'ctrl' => 'Execucoes', 'action' => 'run'],
    'report'    => ['mod' => 'manutencao', 'ctrl' => 'Reports',    'action' => 'show'],
    'media'     => ['mod' => 'manutencao', 'ctrl' => 'Media',      'action' => 'serve'],
    'templates' => ['mod' => 'manutencao', 'ctrl' => 'Checklists', 'action' => 'index'],
    'revision_logs' => ['mod' => 'manutencao', 'ctrl' => 'Checklists', 'action' => 'logs'],
    'videos'    => ['mod' => 'manutencao', 'ctrl' => 'Videos',    'action' => 'index'],
    'backup'    => ['mod' => 'seguranca',  'ctrl' => 'Backup',    'action' => 'index'],
    'users'     => ['mod' => 'seguranca',  'ctrl' => 'Users',      'action' => 'index'],
    'access'    => ['mod' => 'seguranca',  'ctrl' => 'Access',     'action' => 'index'],
    'people'    => ['mod' => 'cadastros',  'ctrl' => 'People',     'action' => 'index'],
    'vehicles'  => ['mod' => 'cadastros',  'ctrl' => 'Vehicles',   'action' => 'index'],
    'dashboard' => ['mod' => 'cadastros',  'ctrl' => 'Dashboard',  'action' => 'index'],
    'company'   => ['mod' => 'cadastros',  'ctrl' => 'Company',    'action' => 'index'],
    'branches'  => ['mod' => 'cadastros',  'ctrl' => 'Branches',   'action' => 'index'],
    'groups'    => ['mod' => 'cadastros',  'ctrl' => 'Groups',     'action' => 'index'],
    'media'     => ['mod' => 'manutencao', 'ctrl' => 'Media',      'action' => 'serve'],
];

if (!isset($pageRoutes[$page])) {
    $page = $defaultPage;
}

// Propaga variáveis para o roteador
$_GET['page'] = $page;
$_GET['mod'] = $pageRoutes[$page]['mod'];
$_GET['ctrl'] = $pageRoutes[$page]['ctrl'];
$_GET['action'] = $pageRoutes[$page]['action'];

App\Core\Router::dispatch();
