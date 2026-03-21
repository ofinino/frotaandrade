<?php
namespace App\Modulos\Seguranca\Controllers;

use App\Core\View;

class CleanupController
{
    private \PDO $db;
    private int $empresaId;

    private array $groups = [
        'checklists' => [
            'label' => 'Execuções de checklist (execuções, respostas, mídias)',
            'tables' => [
                'man_checklist_midias',
                'man_checklist_respostas',
                'man_checklist_execucoes',
            ],
        ],
        'servicos_os' => [
            'label' => 'SS / OS / Anexos / Auditoria',
            'tables' => [
                'man_service_request_work_order',
                'man_service_requests',
                'man_work_order_parts',
                'man_work_order_labor',
                'man_work_order_items',
                'man_work_orders',
                'man_attachments',
                'man_audit_log',
            ],
        ],
        'preventiva' => [
            'label' => 'Planos preventivos e vencimentos',
            'tables' => [
                'man_maintenance_due',
                'man_maintenance_tasks',
                'man_maintenance_plans',
            ],
        ],
        'cadastros_operacionais' => [
            'label' => 'Veículos e cadastros operacionais',
            'tables' => [
                'cad_veiculos',
                'cad_modelos',
                'cad_equipamentos',
            ],
        ],
        'revision_logs' => [
            'label' => 'Logs e revisões',
            'tables' => [
                'man_checklist_revision_logs',
                'man_revision_logs',
            ],
        ],
    ];

    public function __construct()
    {
        require_role(['admin']);
        $this->db = db();
        $this->empresaId = current_company_id();
    }

    public function index(): void
    {
        if (!has_permission('cleanup.manage') && !is_admin()) {
            flash('error', 'Sem permissão para limpeza.');
            header('Location: index.php');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
            return;
        }

        View::render('Seguranca', 'cleanup', [
            'title' => 'Limpeza de dados (dev)',
            'groups' => $this->groups,
        ]);
    }

    private function handlePost(): void
    {
        $selected = array_keys(array_filter($_POST['groups'] ?? []));
        $confirm = trim($_POST['confirm'] ?? '');

        if ($confirm !== 'LIMPAR') {
            flash('error', 'Digite LIMPAR para confirmar.');
            header('Location: index.php?page=cleanup');
            return;
        }
        if (!$selected) {
            flash('error', 'Selecione pelo menos um grupo para limpar.');
            header('Location: index.php?page=cleanup');
            return;
        }

        $tables = [];
        foreach ($selected as $key) {
            if (isset($this->groups[$key])) {
                foreach ($this->groups[$key]['tables'] as $t) {
                    $tables[] = $t;
                }
            }
        }

        $tables = array_unique($tables);
        $ok = 0;
        $errors = [];

        try {
            $this->db->beginTransaction();
            $this->db->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach ($tables as $table) {
                try {
                    $deleted = $this->clearTable($table);
                    $ok += $deleted;
                } catch (\Throwable $e) {
                    $errors[] = $table . ': ' . $e->getMessage();
                }
            }
            $this->db->exec('SET FOREIGN_KEY_CHECKS=1');
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $errors[] = 'Transação: ' . $e->getMessage();
        }

        if ($errors) {
            flash('error', 'Erros ao limpar: ' . implode(' | ', $errors));
        } else {
            flash('success', 'Limpeza concluída para ' . count($tables) . ' tabela(s).');
        }
        header('Location: index.php?page=cleanup');
    }

    private function clearTable(string $table): int
    {
        // ver se a tabela existe
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([$table]);
        if (!$stmt->fetchColumn()) {
            // apenas ignora se não existe
            return 0;
        }

        // verifica se tem empresa_id
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $stmt->execute([$table, 'empresa_id']);
        $hasEmpresa = (bool)$stmt->fetchColumn();

        if ($hasEmpresa) {
            $del = $this->db->prepare("DELETE FROM `$table` WHERE empresa_id = ?");
            $del->execute([$this->empresaId]);
            return $del->rowCount();
        }

        // sem coluna de empresa: delete completo (evita erro de FK com truncate)
        $this->db->exec("DELETE FROM `$table`");
        return 0;
    }
}
