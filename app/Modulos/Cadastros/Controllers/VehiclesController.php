<?php
namespace App\Modulos\Cadastros\Controllers;

use App\Core\View;
use App\Modulos\Cadastros\Models\VehiclesModel;

class VehiclesController
{
    private VehiclesModel $model;

    public function __construct()
    {
        require_role(['admin','gestor','lider','executante','membro']);
        $db = db();
        $empresaId = current_company_id();
        $filialId = current_branch_id();
        $filiais = current_branch_ids();
        $this->model = new VehiclesModel($db, $empresaId, $filialId, $filiais);
    }

    public function index(): void
    {
        if (!has_permission('vehicles.view')) {
            flash('error', 'Sem permissao para gerenciar veiculos.');
            header('Location: index.php');
            exit;
        }

        $editingId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;

        if (isset($_GET['delete'])) {
            $this->delete((int) $_GET['delete']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->save($editingId);
            return;
        }

        try {
            $vehicles = $this->model->listar();
        } catch (\Throwable $e) {
            flash('error', 'Erro ao carregar veiculos: ' . $e->getMessage());
            $vehicles = [];
        }
        $editVehicle = null;
        if ($editingId) {
            foreach ($vehicles as $v) {
                if ((int)$v['id'] === $editingId) {
                    $editVehicle = $v;
                    break;
                }
            }
        }

        View::render('Cadastros', 'vehicles', [
            'title' => 'Veiculos',
            'vehicles' => $vehicles,
            'editVehicle' => $editVehicle,
        ]);
    }

    private function save(?int $editingId): void
    {
        $plate = strtoupper(trim($_POST['plate'] ?? ''));
        $model = trim($_POST['model'] ?? '');
        $year = trim($_POST['year'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($plate === '') {
            flash('error', 'Placa e obrigatoria.');
            header('Location: index.php?page=vehicles' . ($editingId ? '&edit=' . $editingId : ''));
            exit;
        }

        if ($editingId) {
            $this->model->atualizar($editingId, [
                'plate' => $plate,
                'model' => $model,
                'year' => $year,
                'notes' => $notes,
            ]);
            flash('success', 'Veiculo atualizado.');
        } else {
            $this->model->criar([
                'plate' => $plate,
                'model' => $model,
                'year' => $year,
                'notes' => $notes,
            ]);
            flash('success', 'Veiculo criado.');
        }

        header('Location: index.php?page=vehicles');
        exit;
    }

    private function delete(int $id): void
    {
        try {
            $this->model->excluir($id);
            flash('success', 'Veiculo removido.');
        } catch (\Throwable $e) {
            flash('error', 'Erro ao remover veiculo: ' . $e->getMessage());
        }
        header('Location: index.php?page=vehicles');
        exit;
    }
}
