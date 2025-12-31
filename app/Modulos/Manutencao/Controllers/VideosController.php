<?php
namespace App\Modulos\Manutencao\Controllers;

use App\Core\View;

class VideosController
{
    public function index(): void
    {
        if (!has_permission('checks.view')) {
            flash('error', 'Sem permissao para acessar videos.');
            header('Location: index.php');
            return;
        }

        View::render('Manutencao', 'videos', [
            'title' => 'Videos',
        ]);
    }
}
