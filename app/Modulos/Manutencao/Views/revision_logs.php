<?php
// View: relatorio de logs de revisao de modelos.
// Espera: $logs, $hasTable.
?>
<div class="bg-white border border-slate-100 shadow rounded-lg p-4 space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <div class="text-lg font-semibold text-slate-900">Histórico de revisões</div>
            <p class="text-sm text-slate-500">Mostra quem alterou cada modelo, quando e o que mudou.</p>
        </div>
        <a href="index.php?page=templates" class="text-sm text-amber-600">Voltar para modelos</a>
    </div>

    <?php if (!$hasTable): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded">
            Tabela de revisões ainda não existe. Salve um modelo para criar ou peça ao administrador do banco para criar.
        </div>
    <?php elseif (empty($logs)): ?>
        <div class="text-sm text-slate-600">Nenhum registro de revisão encontrado.</div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-left">
                <thead>
                    <tr class="text-slate-500 border-b border-slate-100">
                        <th class="px-3 py-2">Data/Hora</th>
                        <th class="px-3 py-2">Modelo</th>
                        <th class="px-3 py-2">Versão</th>
                        <th class="px-3 py-2">Responsável</th>
                        <th class="px-3 py-2">Resumo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($logs as $log): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2 text-slate-700 whitespace-nowrap"><?= sanitize($log['created_at'] ?? '') ?></td>
                            <td class="px-3 py-2 text-slate-800"><?= !empty($log['checklist_name']) ? sanitize($log['checklist_name']) : '-' ?></td>
                            <td class="px-3 py-2 text-slate-700"><?= !empty($log['versao_numero']) ? sanitize($log['versao_numero']) : '-' ?></td>
                            <td class="px-3 py-2 text-slate-700"><?= !empty($log['user_name']) ? sanitize($log['user_name']) : 'N/I' ?></td>
                            <td class="px-3 py-2 text-slate-800"><?= sanitize($log['resumo'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
