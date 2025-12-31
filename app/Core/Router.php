<?php
namespace App\Core;

class Router
{
    public static function dispatch(): void
    {
        // Exemplo: ?mod=manutencao&ctrl=Checklists&action=index
        $mod = strtolower($_GET['mod'] ?? '');
        $ctrl = $_GET['ctrl'] ?? 'Checklists';
        $action = $_GET['action'] ?? 'index';

        if (!$mod) {
            header('Location: index.php');
            exit;
        }

        $moduleNamespace = '\\App\\Modulos\\' . ucfirst($mod) . '\\Controllers\\';
        $class = $moduleNamespace . $ctrl . 'Controller';

        if (!class_exists($class)) {
            http_response_code(404);
            echo 'Controller não encontrado.';
            return;
        }

        $controller = new $class();
        if (!method_exists($controller, $action)) {
            http_response_code(404);
            echo 'Ação não encontrada.';
            return;
        }

        $controller->$action();
    }
}
