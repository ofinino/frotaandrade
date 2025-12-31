<?php
namespace App\Modulos\Manutencao\Controllers;

use App\Core\View;
use App\Modulos\Manutencao\Models\ReportsModel;

class ReportsController
{
    private ReportsModel $model;

    public function __construct()
    {
        $db = db();
        $empresaId = current_company_id();
        $filialId = current_branch_id();
        $filiais = current_branch_ids();
        $this->model = new ReportsModel($db, $empresaId, $filialId, $filiais);
    }

    public function show(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $run = $this->model->obterExecucao($id);
        if (!$run) {
            echo '<p>Relatorio nao encontrado ou sem permissao.</p>';
            return;
        }

        // Seguranca: ja filtrado por empresa/filial no model
        $fields = $this->model->obterCamposVersao((int) $run['versao_id']);
        $answers = $this->model->obterRespostas($id);
        $mediaByField = $this->model->obterMidiasPorCampo($id);

        View::render('Manutencao', 'report', [
            'title' => 'Relatorio',
            'run' => $run,
            'fields' => $fields,
            'answers' => $answers,
            'mediaByField' => $mediaByField,
        ], false);
    }
}