<?php
$company = $company ?? [];
?>
<div class="max-w-3xl bg-white border border-slate-100 shadow rounded-lg p-4 space-y-3">
    <div class="font-semibold text-slate-900">Empresa / Marca</div>
    <form method="post" class="space-y-3">
        <div>
            <label class="block text-sm text-slate-600 mb-1">Nome interno</label>
            <input class="w-full border border-slate-200 rounded px-3 py-2" name="name" value="<?= sanitize($company['name'] ?? '') ?>" />
        </div>
        <div>
            <label class="block text-sm text-slate-600 mb-1">Nome exibido nos relatorios</label>
            <input class="w-full border border-slate-200 rounded px-3 py-2" name="display_name" value="<?= sanitize($company['display_name'] ?? '') ?>" />
        </div>
        <div>
            <label class="block text-sm text-slate-600 mb-1">URL da logo (pode usar arquivo em uploads/)</label>
            <input class="w-full border border-slate-200 rounded px-3 py-2" name="logo_url" value="<?= sanitize($company['logo_url'] ?? '') ?>" />
            <p class="text-xs text-slate-500 mt-1">Ex: uploads/logo.png</p>
        </div>
        <div class="flex justify-end">
            <button class="bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded" type="submit">Salvar</button>
        </div>
    </form>
</div>
