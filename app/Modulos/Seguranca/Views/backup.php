<?php
$config = $config ?? [];
$result = $result ?? null;
?>
<div class="bg-white border border-slate-100 shadow rounded-lg p-4 space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <div class="text-lg font-semibold text-slate-900">Backup do banco</div>
            <p class="text-sm text-slate-600">Backup direto pelo sistema (mysqldump). Apenas admin.</p>
        </div>
        <?php if (!empty($config['last_run_at'])): ?>
            <div class="text-xs text-slate-500">
                Último: <?= sanitize($config['last_run_at']) ?>
            </div>
        <?php endif; ?>
    </div>

    <form method="post" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm text-slate-600 mb-1">Pasta local para salvar</label>
                <input name="local_path" class="w-full h-10 border border-slate-200 rounded px-3" value="<?= sanitize($config['local_path'] ?? 'C:\backups\db') ?>" required>
            </div>
            <div>
                <label class="block text-sm text-slate-600 mb-1">Pasta Google Drive (opcional)</label>
                <input name="drive_path" class="w-full h-10 border border-slate-200 rounded px-3" value="<?= sanitize($config['drive_path'] ?? '') ?>" placeholder="C:\Users\...\Google Drive\db">
            </div>
        </div>

        <div>
            <label class="block text-sm text-slate-600 mb-1">Caminho do mysqldump.exe</label>
            <input name="mysqldump_path" class="w-full h-10 border border-slate-200 rounded px-3" value="<?= sanitize($config['mysqldump_path'] ?? 'C:\xampp\mysql\bin\mysqldump.exe') ?>" required>
            <p class="text-xs text-slate-500 mt-1">Deixe o padrão se o XAMPP estiver neste servidor.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm text-slate-600 mb-1">Agendamento (executa quando um admin abrir esta página)</label>
                <select name="schedule" class="w-full h-10 border border-slate-200 rounded px-3">
                    <?php $sched = $config['schedule'] ?? 'manual'; ?>
                    <option value="manual" <?= $sched === 'manual' ? 'selected' : '' ?>>Apenas manual</option>
                    <option value="30min" <?= $sched === '30min' ? 'selected' : '' ?>>A cada 30 minutos</option>
                    <option value="daily" <?= $sched === 'daily' ? 'selected' : '' ?>>Diário</option>
                </select>
            </div>
            <div>
                <label class="block text-sm text-slate-600 mb-1">Horário (se diário)</label>
                <input name="daily_hour" class="w-full h-10 border border-slate-200 rounded px-3" value="<?= sanitize($config['daily_hour'] ?? '02:00') ?>" placeholder="02:00">
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" name="save" class="bg-slate-900 hover:bg-slate-800 text-white font-semibold px-4 py-2 rounded">Salvar configuração</button>
            <button type="submit" name="run_now" value="1" class="bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded">Gerar backup agora</button>
        </div>
    </form>
</div>

<?php if ($result): ?>
    <div class="bg-white border border-slate-100 shadow rounded-lg p-4 space-y-2 mt-4 text-sm">
        <div class="font-semibold text-slate-900">Resultado</div>
        <div class="text-slate-700"><?= sanitize($result['status'] ?? '') ?></div>
        <?php if (!empty($result['file'])): ?>
            <div class="text-slate-600">Arquivo: <?= sanitize($result['file']) ?></div>
        <?php endif; ?>
        <?php if (!empty($result['exit']) && $result['exit'] !== 0): ?>
            <div class="text-rose-600">Código: <?= (int)$result['exit'] ?></div>
        <?php endif; ?>
        <?php if (!empty($result['output'])): ?>
            <pre class="bg-slate-900 text-slate-100 text-xs p-3 rounded overflow-x-auto"><?= sanitize(implode("\n", (array)$result['output'])) ?></pre>
        <?php endif; ?>
    </div>
<?php endif; ?>
