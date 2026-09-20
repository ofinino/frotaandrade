<?php
namespace App\Modulos\Cadastros\Controllers;

use App\Core\View;

class DashboardController
{
    public function index(): void
    {
        require_login();
        $db = db();
        $companyId = current_company_id();
        $branchId = current_branch_id();
        $branchIds = current_branch_ids();
        if (empty($branchIds) && $branchId) {
            $branchIds = [$branchId];
        }

        // Cards básicos
        $tables = [
            'seg_usuarios' => 'Usuários',
            'cad_pessoas' => 'Pessoas',
            'cad_veiculos' => 'Veículos',
            'man_checklists' => 'Modelos',
            'man_checklist_execucoes' => 'Execuções',
        ];

        $counts = [];
        foreach ($tables as $table => $label) {
            try {
                $sql = "SELECT COUNT(*) FROM {$table} WHERE empresa_id = ?";
                $params = [$companyId];
                if (!is_admin() && $branchIds) {
                    $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
                    $sql .= " AND (filial_id IS NULL OR filial_id IN ($placeholders))";
                    $params = array_merge($params, $branchIds);
                }
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $counts[$table] = (int)$stmt->fetchColumn();
            } catch (\Throwable $e) {
                $counts[$table] = 0;
            }
        }

        // Filtro de período
        $period = $_GET['period'] ?? 'month';
        $period = in_array($period, ['day', 'week', 'month', 'year'], true) ? $period : 'month';
        $now = new \DateTimeImmutable('now');
        switch ($period) {
            case 'day':
                $start = $now->setTime(0, 0, 0);
                break;
            case 'week':
                $start = $now->modify('monday this week')->setTime(0, 0, 0);
                break;
            case 'year':
                $start = $now->setDate((int)$now->format('Y'), 1, 1)->setTime(0, 0, 0);
                break;
            case 'month':
            default:
                $start = $now->setDate((int)$now->format('Y'), (int)$now->format('m'), 1)->setTime(0, 0, 0);
                break;
        }
        $end = $now;

        $branchFilter = '';
        $branchParams = [];
        if (!is_admin() && $branchIds) {
            $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
            $branchFilter = " AND (filial_id IS NULL OR filial_id IN ($placeholders))";
            $branchParams = $branchIds;
        }

        // Status das execuções no período (base = created_at)
        $statusCounts = [
            'pendente' => 0,
            'em_andamento' => 0,
            'pausado' => 0,
            'concluido' => 0,
        ];
        try {
            $sqlStatus = "SELECT status, COUNT(*) AS total
                          FROM man_checklist_execucoes
                          WHERE empresa_id = ?
                            AND created_at BETWEEN ? AND ? $branchFilter
                          GROUP BY status";
            $paramsStatus = array_merge([$companyId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')], $branchParams);
            $stmt = $db->prepare($sqlStatus);
            $stmt->execute($paramsStatus);
            foreach ($stmt->fetchAll() as $row) {
                $statusCounts[$row['status']] = (int)$row['total'];
            }
        } catch (\Throwable $e) {
            // mantém zero caso falhe
        }

        // Combustivel: resumo do periodo (gasto, litros, preco medio)
        $fuelSummary = [
            'total_abastecimentos' => 0,
            'custo_total' => 0.0,
            'quantidade_total' => 0.0,
            'preco_medio' => 0.0,
        ];
        try {
            $sqlFuel = "SELECT COUNT(*) AS total,
                               COALESCE(SUM(custo), 0) AS custo_total,
                               COALESCE(SUM(quantidade), 0) AS quantidade_total
                        FROM man_fuel_records
                        WHERE empresa_id = ?
                          AND data_hora BETWEEN ? AND ? $branchFilter";
            $paramsFuel = array_merge([$companyId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')], $branchParams);
            $stmt = $db->prepare($sqlFuel);
            $stmt->execute($paramsFuel);
            $row = $stmt->fetch() ?: [];
            $fuelSummary['total_abastecimentos'] = (int)($row['total'] ?? 0);
            $fuelSummary['custo_total'] = (float)($row['custo_total'] ?? 0);
            $fuelSummary['quantidade_total'] = (float)($row['quantidade_total'] ?? 0);
            $fuelSummary['preco_medio'] = $fuelSummary['quantidade_total'] > 0
                ? $fuelSummary['custo_total'] / $fuelSummary['quantidade_total']
                : 0.0;
        } catch (\Throwable $e) {
            // mantem zeros caso a tabela nao exista/erro
        }

        // Combustivel: gasto por dia no periodo
        try {
            $sqlFuelSerie = "SELECT DATE(data_hora) AS dia, COALESCE(SUM(custo), 0) AS total
                              FROM man_fuel_records
                              WHERE empresa_id = ?
                                AND data_hora BETWEEN ? AND ? $branchFilter
                              GROUP BY DATE(data_hora)
                              ORDER BY dia ASC";
            $paramsFuelSerie = array_merge([$companyId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')], $branchParams);
            $stmt = $db->prepare($sqlFuelSerie);
            $stmt->execute($paramsFuelSerie);
            $serieCombustivel = $stmt->fetchAll();
        } catch (\Throwable $e) {
            $serieCombustivel = [];
        }

        // Combustivel: saldo atual dos tanques ativos
        try {
            $sqlTanques = "SELECT nome, capacidade_maxima, estoque_atual
                            FROM man_fuel_tanks
                            WHERE empresa_id = ? AND ativo = 1 $branchFilter
                            ORDER BY nome ASC";
            $stmt = $db->prepare($sqlTanques);
            $stmt->execute(array_merge([$companyId], $branchParams));
            $tanques = $stmt->fetchAll();
        } catch (\Throwable $e) {
            $tanques = [];
        }

        // Ordens de servico: contagem por status (estado atual, nao filtrado por periodo)
        $osStatusOrder = ['solicitacao', 'aguardando_agendamento', 'em_execucao', 'analise_aprovacao', 'encerrada', 'cancelada'];
        $osStatusCounts = array_fill_keys($osStatusOrder, 0);
        try {
            $sqlOs = "SELECT status, COUNT(*) AS total
                      FROM man_work_orders
                      WHERE empresa_id = ? $branchFilter
                      GROUP BY status";
            $stmt = $db->prepare($sqlOs);
            $stmt->execute(array_merge([$companyId], $branchParams));
            foreach ($stmt->fetchAll() as $row) {
                if (isset($osStatusCounts[$row['status']])) {
                    $osStatusCounts[$row['status']] = (int)$row['total'];
                }
            }
        } catch (\Throwable $e) {
            // mantem zeros caso a tabela nao exista/erro
        }

        View::render('Cadastros', 'dashboard', [
            'title' => 'Painel',
            'tables' => $tables,
            'counts' => $counts,
            'statusCounts' => $statusCounts,
            'fuelSummary' => $fuelSummary,
            'serieCombustivel' => $serieCombustivel,
            'tanques' => $tanques,
            'osStatusCounts' => $osStatusCounts,
            'period' => $period,
            'start' => $start,
            'end' => $end,
        ]);
    }
}
