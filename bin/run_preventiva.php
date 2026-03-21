<?php
require __DIR__ . '/../bootstrap.php';

$empresaId = current_company_id() ?: (int)($argv[1] ?? 1);
$filialId = current_branch_id();
$filiais = current_branch_ids();

$db = db();

use App\Modulos\Manutencao\Models\PlanosPreventivaModel;
use App\Modulos\Manutencao\Models\SolicitacoesServicoModel;
use App\Modulos\Manutencao\Models\AuditoriaModel;

$planModel = new PlanosPreventivaModel($db, $empresaId, $filialId, $filiais);
$srModel = new SolicitacoesServicoModel($db, $empresaId, $filialId, $filiais);
$audit = new AuditoriaModel($db, $empresaId, $filialId, $filiais);

$result = $planModel->processarPreventiva($srModel, $audit, true);
echo 'Preventiva executada. Vencimentos atualizados: ' . $result['due_updated'] . ' | SS criadas: ' . $result['ss_criadas'] . PHP_EOL;
