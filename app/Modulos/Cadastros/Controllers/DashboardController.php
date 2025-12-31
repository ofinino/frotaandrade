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
        $branchFilterExec = '';
        $branchParams = [];
        if (!is_admin() && $branchIds) {
            $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
            $branchFilter = " AND (filial_id IS NULL OR filial_id IN ($placeholders))";
            // Para consultas com alias "e" (pendentes)
            $branchFilterExec = " AND (e.filial_id IS NULL OR e.filial_id IN ($placeholders))";
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

        // Pendentes por executante
        try {
            $sqlPend = "SELECT COALESCE(u.name, 'Sem executante') AS executante, COUNT(*) AS total
                        FROM man_checklist_execucoes e
                        LEFT JOIN seg_usuarios u ON u.id = e.atribuido_para
                        WHERE e.empresa_id = ?
                          AND e.status IN ('pendente','em_andamento','pausado')
                          $branchFilterExec
                        GROUP BY e.atribuido_para, executante
                        ORDER BY total DESC";
            $paramsPend = array_merge([$companyId], $branchParams);
            $stmt = $db->prepare($sqlPend);
            $stmt->execute($paramsPend);
            $pendentesPorExec = $stmt->fetchAll();
        } catch (\Throwable $e) {
            $pendentesPorExec = [];
        }

        // Série de concluídos por dia (finalizado_em)
        try {
            $sqlSerie = "SELECT DATE(finalizado_em) AS dia, COUNT(*) AS total
                         FROM man_checklist_execucoes
                         WHERE empresa_id = ?
                           AND status = 'concluido'
                           AND finalizado_em BETWEEN ? AND ?
                           $branchFilter
                         GROUP BY DATE(finalizado_em)
                         ORDER BY dia ASC";
            $paramsSerie = array_merge([$companyId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')], $branchParams);
            $stmt = $db->prepare($sqlSerie);
            $stmt->execute($paramsSerie);
            $serieExecutadas = $stmt->fetchAll();
        } catch (\Throwable $e) {
            $serieExecutadas = [];
        }

        View::render('Cadastros', 'dashboard', [
            'title' => 'Painel',
            'tables' => $tables,
            'counts' => $counts,
            'statusCounts' => $statusCounts,
            'pendentesPorExec' => $pendentesPorExec,
            'serieExecutadas' => $serieExecutadas,
            'period' => $period,
            'start' => $start,
            'end' => $end,
        ]);
    }
}
