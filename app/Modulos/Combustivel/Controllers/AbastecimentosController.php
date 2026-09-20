<?php
namespace App\Modulos\Combustivel\Controllers;

use App\Core\View;
use App\Modulos\Combustivel\Models\FuelRecordsModel;
use App\Modulos\Combustivel\Models\FuelTanksModel;
use App\Modulos\Combustivel\Models\FornecedoresModel;
use App\Modulos\Manutencao\Models\AnexosModel;

class AbastecimentosController
{
    private FuelRecordsModel $model;
    private FuelTanksModel $tanksModel;
    private FornecedoresModel $fornecedoresModel;
    private AnexosModel $anexosModel;
    private \PDO $db;

    public function __construct()
    {
        $this->db = db();
        $empresaId = current_company_id();
        $filialId = current_branch_id();
        $filiais = current_branch_ids();
        $this->model = new FuelRecordsModel($this->db, $empresaId, $filialId);
        $this->tanksModel = new FuelTanksModel($this->db, $empresaId, $filialId);
        $this->fornecedoresModel = new FornecedoresModel($this->db, $empresaId, $filialId);
        $this->anexosModel = new AnexosModel($this->db, $empresaId, $filialId, $filiais);
    }

    private function listarVeiculos(): array
    {
        $stmt = $this->db->prepare('SELECT id, plate, model, txt_tipo_combustivel FROM cad_veiculos WHERE empresa_id = ? ORDER BY plate ASC');
        $stmt->execute([current_company_id()]);
        return $stmt->fetchAll();
    }

    public function index(): void
    {
        if (!has_permission('combustivel.view')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php');
            return;
        }
        $filters = [
            'veiculo_id' => $_GET['veiculo_id'] ?? null,
            'de' => $_GET['de'] ?? null,
            'ate' => $_GET['ate'] ?? null,
        ];
        $records = $this->model->listar($filters);
        $anexosByRecord = $this->model->listarAnexosPorRegistros(array_column($records, 'id'));
        View::render('Combustivel', 'abastecimentos/index', [
            'title' => 'Abastecimentos',
            'records' => $records,
            'anexosByRecord' => $anexosByRecord,
            'veiculos' => $this->listarVeiculos(),
            'filters' => $filters,
        ]);
    }

    public function create(): void
    {
        if (!has_permission('combustivel.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=abastecimentos');
            return;
        }
        View::render('Combustivel', 'abastecimentos/form', [
            'title' => 'Novo Abastecimento',
            'veiculos' => $this->listarVeiculos(),
            'fornecedores' => $this->fornecedoresModel->listar(),
            'tanks' => $this->tanksModel->listarAtivos(),
        ]);
    }

    public function store(): void
    {
        if (!has_permission('combustivel.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=abastecimentos');
            return;
        }

        $tipo = ($_POST['tipo'] ?? 'comercial') === 'interno' ? 'interno' : 'comercial';
        $veiculoId = (int)($_POST['veiculo_id'] ?? 0);
        $dataHora = trim($_POST['data_hora'] ?? '');
        $quantidade = $_POST['quantidade'] ?? null;
        $odometro = $_POST['odometro'] ?? null;
        $combustivelTipo = $_POST['combustivel_tipo'] ?? 'diesel';
        $allowedCombustivel = ['alcool', 'arla32', 'diesel', 'diesel_s10', 'gasolina', 'gasolina_aditivada'];

        if (!$veiculoId || $dataHora === '' || !is_numeric($quantidade) || (float)$quantidade <= 0 || !is_numeric($odometro)) {
            flash('error', 'Preencha veiculo, data, quantidade e odometro corretamente.');
            header('Location: index.php?mod=combustivel&ctrl=Abastecimentos&action=create');
            return;
        }
        if (!in_array($combustivelTipo, $allowedCombustivel, true)) {
            flash('error', 'Tipo de combustivel invalido.');
            header('Location: index.php?mod=combustivel&ctrl=Abastecimentos&action=create');
            return;
        }

        $ultimoOdometro = $this->model->ultimoOdometro($veiculoId);
        if ($ultimoOdometro !== null && (float)$odometro < $ultimoOdometro) {
            flash('error', 'Insira um odometro valido: nao pode ser menor que o ultimo registrado (' . $ultimoOdometro . ' km).');
            header('Location: index.php?mod=combustivel&ctrl=Abastecimentos&action=create');
            return;
        }

        $payload = [
            'veiculo_id' => $veiculoId,
            'tipo' => $tipo,
            'data_hora' => str_replace('T', ' ', $dataHora) . (strlen($dataHora) === 16 ? ':00' : ''),
            'quantidade' => $quantidade,
            'odometro' => $odometro,
            'combustivel_tipo' => $combustivelTipo,
            'tanque_cheio' => !empty($_POST['tanque_cheio']),
            'observacoes' => $_POST['observacoes'] ?? null,
            'criado_por' => current_user()['id'] ?? null,
        ];

        if ($tipo === 'comercial') {
            $fornecedorNome = trim($_POST['fornecedor_nome'] ?? '');
            if ($fornecedorNome === '') {
                flash('error', 'Informe o fornecedor.');
                header('Location: index.php?mod=combustivel&ctrl=Abastecimentos&action=create');
                return;
            }
            $payload['fornecedor_id'] = $this->fornecedoresModel->buscarOuCriar($fornecedorNome);
            $payload['custo'] = $_POST['custo'] ?? 0;
        } else {
            $tankId = (int)($_POST['tank_id'] ?? 0);
            $tank = $tankId ? $this->tanksModel->obter($tankId) : null;
            if (!$tank || !$tank['ativo']) {
                flash('error', 'Selecione um tanque de origem ativo.');
                header('Location: index.php?mod=combustivel&ctrl=Abastecimentos&action=create');
                return;
            }
            $payload['tank_id'] = $tankId;
        }

        try {
            $recordId = $this->model->criar($payload, $this->tanksModel);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            header('Location: index.php?mod=combustivel&ctrl=Abastecimentos&action=create');
            return;
        }

        if (!empty($_FILES['anexos']['name'][0] ?? '')) {
            $this->anexosModel->salvar('abastecimento', $recordId, $_FILES['anexos'], (int)(current_user()['id'] ?? 0));
        }

        flash('success', 'Abastecimento registrado.');
        header('Location: index.php?page=abastecimentos');
    }

    public function destroy(): void
    {
        if (!has_permission('combustivel.manage')) {
            flash('error', 'Sem permissao.');
            header('Location: index.php?page=abastecimentos');
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        try {
            if ($this->model->excluir($id, $this->tanksModel)) {
                flash('success', 'Abastecimento excluido.');
            } else {
                flash('error', 'Abastecimento nao encontrado.');
            }
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        header('Location: index.php?page=abastecimentos');
    }
}
