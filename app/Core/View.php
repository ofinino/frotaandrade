<?php
namespace App\Core;

class View
{
    public static function render(string $module, string $view, array $data = [], bool $withLayout = true): void
    {
        $basePath = __DIR__ . '/../Modulos/' . $module . '/Views/' . $view . '.php';
        if (!file_exists($basePath)) {
            echo 'View não encontrada';
            return;
        }
        extract($data, EXTR_SKIP);

        if ($withLayout) {
            require_once __DIR__ . '/../../layout.php';
            $title = $data['title'] ?? ucfirst(strtolower($view));
            render_header($title);
            include $basePath;
            render_footer();
        } else {
            include $basePath;
        }
    }
}
