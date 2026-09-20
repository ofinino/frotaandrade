<?php
$plans = $plans ?? [];
$filters = $filters ?? [];
$statusLabels = ['rascunho' => 'Rascunho', 'ativo' => 'Ativo', 'inativo' => 'Inativo'];
$statusColors = [
    'rascunho' => 'bg-slate-100 text-slate-700',
    'ativo' => 'bg-emerald-100 text-emerald-800',
    'inativo' => 'bg-gray-200 text-gray-700',
];
?>
<div class="max-w-6xl mx-auto px-4 py-6 space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-semibold text-slate-900">Plano de manutenção</h2>
        <?php if (has_permission('preventiva.manage')): ?>
            <a class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800" href="index.php?mod=manutencao&ctrl=PlanosPreventiva&action=create">Novo plano de manutenção</a>
        <?php endif; ?>
    </div>

    <div class="flex items-center gap-2">
        <a class="inline-flex items-center rounded-lg border px-3 py-2 text-sm font-medium <?= ($filters['association_type'] ?? '') === '' ? 'bg-slate-900 text-white border-slate-900' : 'border-slate-200 text-slate-700' ?>" href="index.php?page=planos_preventiva">Todos</a>
        <a class="inline-flex items-center rounded-lg border px-3 py-2 text-sm font-medium <?= ($filters['association_type'] ?? '') === 'unidade' ? 'bg-slate-900 text-white border-slate-900' : 'border-slate-200 text-slate-700' ?>" href="index.php?page=planos_preventiva&tab=unidade">Unidades</a>
        <a class="inline-flex items-center rounded-lg border px-3 py-2 text-sm font-medium <?= ($filters['association_type'] ?? '') === 'veiculos' ? 'bg-slate-900 text-white border-slate-900' : 'border-slate-200 text-slate-700' ?>" href="index.php?page=planos_preventiva&tab=veiculos">Veículos</a>
        <a class="ml-auto text-sm text-blue-600 hover:underline" href="index.php?page=manutencao_servicos">Gerenciar serviços</a>
    </div>

    <form method="get" action="index.php">
        <input type="hidden" name="page" value="planos_preventiva">
        <input class="w-full max-w-sm rounded-lg border border-slate-200 px-3 py-2 text-sm" name="q" placeholder="Busque pelo nome do plano" value="<?= sanitize($filters['q'] ?? '') ?>">
    </form>

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium">Nome do plano</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">Serviços</th>
                    <th class="px-4 py-3 font-medium">Veículos</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($plans as $p): ?>
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-800"><?= sanitize($p['nome']) ?></td>
                        <td class="px-4 py-3"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $statusColors[$p['status']] ?? '' ?>"><?= sanitize($statusLabels[$p['status']] ?? $p['status']) ?></span></td>
                        <td class="px-4 py-3 text-slate-600"><?= sanitize($p['servicos_count']) ?></td>
                        <td class="px-4 py-3 text-slate-600"><?= sanitize($p['veiculos_count']) ?></td>
                        <td class="px-4 py-3 text-right">
                            <a class="text-blue-600 hover:underline text-sm" href="index.php?mod=manutencao&ctrl=PlanosPreventiva&action=show&id=<?= sanitize($p['id']) ?>">Abrir</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$plans): ?>
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Nenhum plano encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
