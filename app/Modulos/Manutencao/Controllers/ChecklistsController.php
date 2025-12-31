<?php
namespace App\Modulos\Manutencao\Controllers;

use App\Core\View;

/**
 * Controller de modelos de checklist com controle de versões.
 */
class ChecklistsController
{
    public function index(): void
    {
        require_permission('templates.view');
        $pdo = db();
        $user = current_user();
        $companyId = current_company_id();
        $branchId = current_branch_id();
        $branchIds = current_branch_ids();
        $userId = $user['id'] ?? null;

        $gerarProximaVersao = function (int $checklistId = 0) use ($pdo, $companyId): string {
            $ano = date('Y');
            $num = 1;
            if ($checklistId > 0) {
                $stmt = $pdo->prepare("SELECT versao FROM man_checklists WHERE empresa_id = ? AND id = ?");
                $stmt->execute([$companyId, $checklistId]);
                $last = $stmt->fetchColumn();
                if ($last && preg_match('/Revisao\\s+(\\d+)\\//' . $ano . '/i', $last, $m)) {
                    $num = ((int)$m[1]) + 1;
                } elseif ($last && preg_match('/Revisao\\s+(\\d+)/i', $last, $m)) {
                    $num = ((int)$m[1]) + 1;
                }
            }
            return 'Revisao ' . str_pad((string)$num, 2, '0', STR_PAD_LEFT) . '/' . $ano;
        };

        $temTabelaRevisao = function () use ($pdo): bool {
            static $exists = null;
            if ($exists !== null) {
                return $exists;
            }
            try {
                $stmt = $pdo->query("SHOW TABLES LIKE 'man_checklist_revision_logs'");
                $exists = (bool) $stmt->fetchColumn();
            } catch (\Throwable $e) {
                $exists = false;
            }
            return $exists;
        };

        $garantirTabelaRevisao = function () use ($pdo, $temTabelaRevisao): bool {
            if ($temTabelaRevisao()) {
                return true;
            }
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS man_checklist_revision_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    checklist_id INT NOT NULL,
                    versao_id INT NULL,
                    empresa_id INT NOT NULL,
                    user_id INT NULL,
                    resumo TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_revlog_checklist (checklist_id),
                    INDEX idx_revlog_versao (versao_id),
                    CONSTRAINT fk_revlog_checklist FOREIGN KEY (checklist_id) REFERENCES man_checklists(id) ON DELETE CASCADE,
                    CONSTRAINT fk_revlog_versao FOREIGN KEY (versao_id) REFERENCES man_checklist_versoes(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                return true;
            } catch (\Throwable $e) {
                return false;
            }
        };

        $resumirMudancas = function (array $antes, array $depois): string {
            $norm = function (string $label): string {
                $l = trim($label);
                return mb_strtolower($l, 'UTF-8');
            };

            $mapAntes = [];
            foreach ($antes as $pos => $f) {
                $k = $norm((string)($f['label'] ?? ''));
                if ($k === '') {
                    continue;
                }
                $mapAntes[$k] = [
                    'label' => (string)($f['label'] ?? ''),
                    'required' => (int)($f['required'] ?? 0),
                    'pos' => $pos
                ];
            }
            $mapDepois = [];
            foreach ($depois as $pos => $f) {
                $k = $norm((string)($f['label'] ?? ''));
                if ($k === '') {
                    continue;
                }
                $mapDepois[$k] = [
                    'label' => (string)($f['label'] ?? ''),
                    'required' => (int)($f['required'] ?? 0),
                    'pos' => $pos
                ];
            }

            $addedKeys = array_diff(array_keys($mapDepois), array_keys($mapAntes));
            $removedKeys = array_diff(array_keys($mapAntes), array_keys($mapDepois));
            $changedRequired = [];
            foreach ($mapDepois as $k => $dados) {
                if (!isset($mapAntes[$k])) {
                    continue;
                }
                if ((int)$mapAntes[$k]['required'] !== (int)$dados['required']) {
                    $changedRequired[] = $dados['label'];
                }
            }

            $orderChanged = false;
            $seqAntes = array_keys($mapAntes);
            $seqDepois = array_keys($mapDepois);
            if ($seqAntes !== $seqDepois) {
                $orderChanged = true;
            }

            $parts = [];
            if ($addedKeys) {
                $parts[] = 'Adicionados: ' . implode(', ', array_map(function ($k) use ($mapDepois) {
                    return $mapDepois[$k]['label'];
                }, $addedKeys));
            }
            if ($removedKeys) {
                $parts[] = 'Removidos: ' . implode(', ', array_map(function ($k) use ($mapAntes) {
                    return $mapAntes[$k]['label'];
                }, $removedKeys));
            }
            if ($changedRequired) {
                $parts[] = 'Obrigatoriedade alterada: ' . implode(', ', $changedRequired);
            }
            if ($orderChanged) {
                $parts[] = 'Ordem de campos ajustada';
            }

            return $parts ? implode(' | ', $parts) : 'Estrutura mantida sem alterações relevantes';
        };

        $registrarRevisao = function (int $checklistId, int $versaoId, string $resumo) use ($pdo, $companyId, $userId, $garantirTabelaRevisao): void {
            try {
                if (!$garantirTabelaRevisao()) {
                    return;
                }
                $stmt = $pdo->prepare('INSERT INTO man_checklist_revision_logs (checklist_id, versao_id, empresa_id, user_id, resumo, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
                $stmt->execute([$checklistId, $versaoId, $companyId, $userId, $resumo]);
            } catch (\Throwable $e) {
                // não interrompe fluxo principal
            }
        };

        // tenta criar tabela de revisão; se falhar, segue fluxo sem logs
        $garantirTabelaRevisao();

        // Ativar/inativar modelo
        if (isset($_GET['deactivate'])) {
            $id = (int)$_GET['deactivate'];
            $pdo->prepare('UPDATE man_checklists SET status = ?, updated_at = NOW() WHERE id = ? AND empresa_id = ?')
                ->execute(['inativo', $id, $companyId]);
            flash('success', 'Modelo inativado. Não aparecerá para novas execuções.');
            safe_redirect('index.php?page=templates');
        }
        if (isset($_GET['activate'])) {
            $id = (int)$_GET['activate'];
            $pdo->prepare('UPDATE man_checklists SET status = ?, updated_at = NOW() WHERE id = ? AND empresa_id = ?')
                ->execute(['ativo', $id, $companyId]);
            flash('success', 'Modelo reativado.');
            safe_redirect('index.php?page=templates');
        }

        // Remover modelo
        if (isset($_GET['delete'])) {
            $delId = (int) $_GET['delete'];
            $params = [$delId, $companyId];
            $branchFilter = '';
            if (!is_admin() && $branchId) {
                $branchFilter = ' AND (filial_id IS NULL OR filial_id = ?)';
                $params[] = $branchId;
            }
            $stmtHas = $pdo->prepare('SELECT COUNT(*) FROM man_checklist_execucoes WHERE checklist_id = ? AND empresa_id = ?');
            $stmtHas->execute([$delId, $companyId]);
            $countExec = (int)$stmtHas->fetchColumn();
            if ($countExec > 0) {
                flash('error', 'Modelo já possui execuções e não pode ser excluído. Inative-o.');
                safe_redirect('index.php?page=templates');
            }
            $pdo->prepare('DELETE FROM man_checklists WHERE id = ? AND empresa_id = ?' . $branchFilter)->execute($params);
            flash('success', 'Modelo removido.');
            safe_redirect('index.php?page=templates');
        }

        // Carregar revisão (edição)
        $editingTemplate = null;
        $editingFields = [];
        if (isset($_GET['revise'])) {
            $revId = (int) $_GET['revise'];
            $stmt = $pdo->prepare('SELECT * FROM man_checklists WHERE id = ? AND empresa_id = ?');
            $stmt->execute([$revId, $companyId]);
            $editingTemplate = $stmt->fetch();
            if ($editingTemplate) {
                $stmtF = $pdo->prepare('SELECT * FROM man_checklist_itens WHERE checklist_id = ? ORDER BY position ASC, id ASC');
                $stmtF->execute([$revId]);
                $editingFields = $stmtF->fetchAll();
            } else {
                flash('error', 'Modelo não encontrado.');
                safe_redirect('index.php?page=templates');
            }
        }

        // Criar modelo (nova versão ou revisão)
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $fields = $_POST['fields'] ?? [];
            $grupoId = (int)($_POST['grupo_id'] ?? 0);
            $editId = (int)($_POST['edit_id'] ?? 0);
            $camposAntes = [];
            if ($editId) {
                $stmtOld = $pdo->prepare('SELECT * FROM man_checklist_itens WHERE checklist_id = ? ORDER BY position ASC, id ASC');
                $stmtOld->execute([$editId]);
                $camposAntes = $stmtOld->fetchAll();
            }
            $fields = array_values(array_filter($fields, function ($field) {
                return trim($field['label'] ?? '') !== '';
            }));
            usort($fields, function ($a, $b) {
                return (int)($a['order'] ?? 0) <=> (int)($b['order'] ?? 0);
            });

            if ($name === '') {
                flash('error', 'Nome do modelo é obrigatório.');
                safe_redirect('index.php?page=templates');
            }
            if (!$grupoId) {
                flash('error', 'Selecione um grupo.');
                safe_redirect('index.php?page=templates');
            }

            try {
                $pdo->beginTransaction();
                $versaoTxt = $gerarProximaVersao($editId ?: 0);

                if ($editId) {
                    // revisão do mesmo modelo: atualiza dados base, zera itens vivos, cria nova versão + snapshot
                    $pdo->prepare('UPDATE man_checklists SET grupo_id = ?, name = ?, description = ?, versao = ?, revisado_em = NOW(), updated_at = NOW() WHERE id = ? AND empresa_id = ?')
                        ->execute([$grupoId, $name, $description, $versaoTxt, $editId, $companyId]);

                    $pdo->prepare('DELETE FROM man_checklist_itens WHERE checklist_id = ?')->execute([$editId]);
                    $order = 0;
                    foreach ($fields as $field) {
                        $label = trim($field['label'] ?? '');
                        $required = isset($field['required']) ? 1 : 0;
                        if ($label === '') {
                            continue;
                        }
                        $itemNum = $order + 1;
                        $pdo->prepare('INSERT INTO man_checklist_itens (checklist_id, label, required, position, item_num) VALUES (?, ?, ?, ?, ?)')
                            ->execute([$editId, $label, $required, $order, $itemNum]);
                        $order++;
                    }

                    $pdo->prepare('INSERT INTO man_checklist_versoes (checklist_id, empresa_id, numero, created_at) VALUES (?, ?, ?, NOW())')
                        ->execute([$editId, $companyId, $versaoTxt]);
                    $versaoId = (int)$pdo->lastInsertId();
                    $pdo->prepare('UPDATE man_checklists SET versao_atual = ? WHERE id = ?')->execute([$versaoId, $editId]);

                    $order = 0;
                    foreach ($fields as $field) {
                        $label = trim($field['label'] ?? '');
                        $required = isset($field['required']) ? 1 : 0;
                        if ($label === '') {
                            continue;
                        }
                        $itemNum = $order + 1;
                        $pdo->prepare('INSERT INTO man_checklist_versao_itens (versao_id, label, required, position, item_num) VALUES (?, ?, ?, ?, ?)')
                            ->execute([$versaoId, $label, $required, $order, $itemNum]);
                        $order++;
                    }

                    $resumo = $resumirMudancas($camposAntes, $fields);
                    $registrarRevisao($editId, $versaoId, $resumo);
                } else {
                    // criação de novo modelo
                    $pdo->prepare('INSERT INTO man_checklists (empresa_id, grupo_id, filial_id, name, description, versao, revisado_em, criado_por, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, NOW())')
                        ->execute([$companyId, $grupoId, $branchId, $name, $description, $versaoTxt, $user['id']]);
                    $templateId = (int) $pdo->lastInsertId();

                    $order = 0;
                    foreach ($fields as $field) {
                        $label = trim($field['label'] ?? '');
                        $required = isset($field['required']) ? 1 : 0;
                        if ($label === '') {
                            continue;
                        }
                        $itemNum = $order + 1;
                        $pdo->prepare('INSERT INTO man_checklist_itens (checklist_id, label, required, position, item_num) VALUES (?, ?, ?, ?, ?)')
                            ->execute([$templateId, $label, $required, $order, $itemNum]);
                        $order++;
                    }

                    $pdo->prepare('INSERT INTO man_checklist_versoes (checklist_id, empresa_id, numero, created_at) VALUES (?, ?, ?, NOW())')
                        ->execute([$templateId, $companyId, $versaoTxt]);
                    $versaoId = (int)$pdo->lastInsertId();
                    $pdo->prepare('UPDATE man_checklists SET versao_atual = ? WHERE id = ?')->execute([$versaoId, $templateId]);

                    $order = 0;
                    foreach ($fields as $field) {
                        $label = trim($field['label'] ?? '');
                        $required = isset($field['required']) ? 1 : 0;
                        if ($label === '') {
                            continue;
                        }
                        $itemNum = $order + 1;
                        $pdo->prepare('INSERT INTO man_checklist_versao_itens (versao_id, label, required, position, item_num) VALUES (?, ?, ?, ?, ?)')
                            ->execute([$versaoId, $label, $required, $order, $itemNum]);
                        $order++;
                    }
                    $resumo = $resumirMudancas([], $fields);
                    $registrarRevisao($templateId, $versaoId, $resumo);
                }

                $pdo->commit();
                flash('success', 'Modelo criado/revisado com sucesso.');
            } catch (\Throwable $e) {
                $pdo->rollBack();
                flash('error', 'Erro ao salvar modelo: ' . $e->getMessage());
            }
            safe_redirect('index.php?page=templates');
        }

        // Carregar modelos, campos e grupos
        $templates = [];
        $fieldsByTemplate = [];
        $groups = [];
        $revisionLogsByTemplate = [];
        $usageCounts = [];
        $params = [$companyId];
        $branchFilter = '';
        if (!is_admin()) {
            if (empty($branchIds) && $branchId) {
                $branchIds = [$branchId];
            }
            if ($branchIds) {
                $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
                $branchFilter = " AND (filial_id IS NULL OR filial_id IN ($placeholders))";
                $params = array_merge($params, $branchIds);
            }
        }
        try {
            // grupos
            $grpParams = [$companyId];
            $grpFilter = '';
            if (!is_admin()) {
                if (!empty($branchIds)) {
                    $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
                    $grpFilter = " AND (filial_id IS NULL OR filial_id IN ($placeholders))";
                    $grpParams = array_merge($grpParams, $branchIds);
                } elseif ($branchId) {
                    $grpFilter = " AND (filial_id IS NULL OR filial_id = ?)";
                    $grpParams[] = $branchId;
                }
            }
            $grpStmt = $pdo->prepare('SELECT id, nome, tipo FROM cad_grupos WHERE empresa_id = ?' . $grpFilter . ' ORDER BY nome ASC');
            $grpStmt->execute($grpParams);
            $groups = $grpStmt->fetchAll();

            $tplStmt = $pdo->prepare('SELECT t.*, g.nome AS grupo_nome FROM man_checklists t LEFT JOIN cad_grupos g ON g.id = t.grupo_id WHERE t.empresa_id = ?' . $branchFilter . ' ORDER BY t.created_at DESC');
            $tplStmt->execute($params);
            $templates = $tplStmt->fetchAll();

            $fieldRowsStmt = $pdo->prepare('SELECT f.* FROM man_checklist_itens f INNER JOIN man_checklists t ON t.id = f.checklist_id WHERE t.empresa_id = ?' . $branchFilter . ' ORDER BY f.position ASC, f.id ASC');
            $fieldRowsStmt->execute($params);
            $fieldRows = $fieldRowsStmt->fetchAll();
            foreach ($fieldRows as $row) {
                $fieldsByTemplate[$row['checklist_id']][] = $row;
            }

            if (!empty($templates)) {
                $tplIds = array_column($templates, 'id');
                $placeUsage = implode(',', array_fill(0, count($tplIds), '?'));
                $usageStmt = $pdo->prepare('SELECT checklist_id, COUNT(*) AS total FROM man_checklist_execucoes WHERE empresa_id = ? AND checklist_id IN (' . $placeUsage . ') GROUP BY checklist_id');
                $usageStmt->execute(array_merge([$companyId], $tplIds));
                foreach ($usageStmt->fetchAll() as $row) {
                    $usageCounts[$row['checklist_id']] = (int)$row['total'];
                }
            }

            if (!empty($templates) && $temTabelaRevisao()) {
                $tplIds = array_column($templates, 'id');
                $place = implode(',', array_fill(0, count($tplIds), '?'));
                $logStmt = $pdo->prepare('SELECT l.*, u.name AS user_name, v.numero AS versao_numero FROM man_checklist_revision_logs l LEFT JOIN seg_usuarios u ON u.id = l.user_id LEFT JOIN man_checklist_versoes v ON v.id = l.versao_id WHERE l.empresa_id = ? AND l.checklist_id IN (' . $place . ') ORDER BY l.created_at DESC');
                $logStmt->execute(array_merge([$companyId], $tplIds));
                foreach ($logStmt->fetchAll() as $log) {
                    $revisionLogsByTemplate[$log['checklist_id']][] = $log;
                }
            }
        } catch (\Throwable $e) {
            flash('error', 'Erro ao carregar modelos: ' . $e->getMessage());
        }

        View::render('Manutencao', 'templates', [
            'title' => 'Modelos de checklist',
            'templates' => $templates,
            'fieldsByTemplate' => $fieldsByTemplate,
            'groups' => $groups,
            'editingTemplate' => $editingTemplate,
            'editingFields' => $editingFields,
            'revisionLogsByTemplate' => $revisionLogsByTemplate,
            'usageCounts' => $usageCounts,
        ]);
    }

    public function logs(): void
    {
        require_permission('templates.view');
        $pdo = db();
        $companyId = current_company_id();
        $branchId = current_branch_id();
        $branchIds = current_branch_ids();

        $logs = [];
        $hasTable = false;

        try {
            $stmtCheck = $pdo->query("SHOW TABLES LIKE 'man_checklist_revision_logs'");
            $hasTable = (bool) $stmtCheck->fetchColumn();
        } catch (\Throwable $e) {
            $hasTable = false;
        }

        if ($hasTable) {
            $params = [$companyId];
            $branchFilter = '';
            if (!is_admin()) {
                if (empty($branchIds) && $branchId) {
                    $branchIds = [$branchId];
                }
                if (!empty($branchIds)) {
                    $place = implode(',', array_fill(0, count($branchIds), '?'));
                    $branchFilter = " AND (t.filial_id IS NULL OR t.filial_id IN ($place))";
                    $params = array_merge($params, $branchIds);
                }
            }

            try {
                $sql = 'SELECT l.*, t.name AS checklist_name, v.numero AS versao_numero, u.name AS user_name
                        FROM man_checklist_revision_logs l
                        LEFT JOIN man_checklists t ON t.id = l.checklist_id
                        LEFT JOIN man_checklist_versoes v ON v.id = l.versao_id
                        LEFT JOIN seg_usuarios u ON u.id = l.user_id
                        WHERE l.empresa_id = ?' . $branchFilter . '
                        ORDER BY l.created_at DESC
                        LIMIT 300';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $logs = $stmt->fetchAll();
            } catch (\Throwable $e) {
                flash('error', 'Erro ao carregar logs de revisao: ' . $e->getMessage());
            }
        }

        View::render('Manutencao', 'revision_logs', [
            'title' => 'Revisoes de modelos',
            'logs' => $logs,
            'hasTable' => $hasTable,
        ]);
    }
}
