<?php
namespace App\Modulos\Seguranca\Controllers;

use App\Core\View;
use App\Modulos\Seguranca\Models\BackupModel;

class BackupController
{
    private BackupModel $model;
    private \PDO $db;

    public function __construct()
    {
        require_role(['admin']);
        $this->db = db();
        $this->model = new BackupModel($this->db);
    }

    public function index(): void
    {
        $config = $this->model->getConfig();
        if (empty($config['mysqldump_path'])) {
            $config['mysqldump_path'] = $this->defaultDumpPath();
        }
        $result = null;

        // Auto-run if eligible (only when admin abre a página)
        if ($config['schedule'] !== 'manual' && $this->shouldRunAuto($config)) {
            $result = $this->runBackup($config, 'auto');
            $config = $this->model->getConfig(); // reload after update
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $config['local_path'] = trim($_POST['local_path'] ?? $config['local_path']);
            $config['drive_path'] = trim($_POST['drive_path'] ?? $config['drive_path']);
            $config['mysqldump_path'] = trim($_POST['mysqldump_path'] ?? $config['mysqldump_path']);
            $config['schedule'] = $_POST['schedule'] ?? $config['schedule'];
            $config['daily_hour'] = $_POST['daily_hour'] ?? $config['daily_hour'];
            $this->model->saveConfig($config);

            if (isset($_POST['run_now'])) {
                $result = $this->runBackup($config, 'manual');
                $config = $this->model->getConfig();
            }
        }

        View::render('Seguranca', 'backup', [
            'title' => 'Backup do banco',
            'config' => $config,
            'result' => $result,
        ]);
    }

    private function shouldRunAuto(array $config): bool
    {
        $last = $config['last_run_at'];
        $now = new \DateTime('now');
        if ($config['schedule'] === '30min') {
            if (!$last) return true;
            $lastDt = new \DateTime($last);
            return ($now->getTimestamp() - $lastDt->getTimestamp()) >= 1800;
        }
        if ($config['schedule'] === 'daily') {
            $hour = $config['daily_hour'] ?? '02:00';
            [$h, $m] = array_pad(explode(':', $hour), 2, '00');
            $target = (clone $now)->setTime((int)$h, (int)$m, 0);
            if (!$last) return $now >= $target;
            $lastDt = new \DateTime($last);
            // run once per day after target time
            return $now >= $target && $lastDt < $target && $now->format('Y-m-d') !== $lastDt->format('Y-m-d');
        }
        return false;
    }

    private function runBackup(array $config, string $mode): array
    {
        $dbHost = $GLOBALS['config']['db_host'] ?? '';
        $dbName = $GLOBALS['config']['db_name'] ?? '';
        $dbUser = $GLOBALS['config']['db_user'] ?? '';
        $dbPass = $GLOBALS['config']['db_pass'] ?? '';

        $timestamp = (new \DateTime())->format('Ymd_His');
        $dumpDir = rtrim($config['local_path'] ?: 'C:\\backups\\db', '\\/');
        $dumpFile = $dumpDir . DIRECTORY_SEPARATOR . $dbName . '_' . $timestamp . '.sql';

        if (!is_dir($dumpDir)) {
            @mkdir($dumpDir, 0775, true);
        }

        $output = [];
        $exitCode = null;
        $status = '';

        $binPath = $config['mysqldump_path'];
        $diagnostic = [];
        $whichDiag = [];
        @exec('where mysqldump', $diagnostic, $wcode);
        @exec('which mysqldump', $whichDiag, $wcode2);

        $localBin = realpath(__DIR__ . '/../../../bin/mysqldump.exe') ?: null;
        // caminhos comuns em hospedagens linux/win
        $common = [
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/usr/mysql/bin/mysqldump',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
        ];

        $candidates = array_values(array_filter(array_merge([
            $binPath,
            realpath($binPath) ?: null,
            $localBin,
        ], $diagnostic, $whichDiag, $common)));
        $resolved = null;
        $basedirs = array_filter(array_map('trim', explode(':', ini_get('open_basedir') ?: '')));
        $isAllowed = function (string $path) use ($basedirs): bool {
            if (empty($basedirs)) return true;
            foreach ($basedirs as $bd) {
                if ($bd !== '' && strpos($path, $bd) === 0) {
                    return true;
                }
            }
            return false;
        };
        foreach ($candidates as $cand) {
            if ($cand && $isAllowed($cand) && @file_exists($cand) && @is_readable($cand)) {
                $resolved = $cand;
                break;
            }
        }

        if (!$resolved) {
            $exitCode = 127;
            $status = 'mysqldump.exe não encontrado.';
            $output[] = 'caminhos testados: ' . implode(' | ', array_unique($candidates));
            $output[] = 'where mysqldump: ' . implode(' | ', $diagnostic);
            $output[] = 'which mysqldump: ' . implode(' | ', $whichDiag);
            $output[] = 'open_basedir: ' . (ini_get('open_basedir') ?: '(vazio)');
            $output[] = 'PATH: ' . (getenv('PATH') ?: '(vazio)');
        } else {
            // monta comando com redirecionamento de stderr
            $cmd = sprintf(
                '"%s" -h "%s" -u "%s" -p%s "%s" > "%s" 2>&1',
                $resolved,
                $dbHost,
                $dbUser,
                $dbPass,
                $dbName,
                $dumpFile
            );

            exec($cmd, $output, $exitCode);

            $status = ($exitCode === 0 && file_exists($dumpFile) && filesize($dumpFile) > 0)
                ? 'Backup gerado: ' . $dumpFile
                : 'Falha ao gerar backup. Código: ' . $exitCode;

            // anexa diagnóstico
            $output[] = 'binario usado: ' . $resolved;
            $output[] = 'where mysqldump: ' . implode(' | ', $diagnostic);
            $output[] = 'which mysqldump: ' . implode(' | ', $whichDiag);
        }

        // Copiar para drive se configurado
        if ($exitCode === 0 && !empty($config['drive_path'])) {
            $targetDir = rtrim($config['drive_path'], '\\/');
            if (is_dir($targetDir)) {
                @copy($dumpFile, $targetDir . DIRECTORY_SEPARATOR . basename($dumpFile));
                $status .= ' | Copiado para: ' . $targetDir;
            } else {
                $status .= ' | Não copiado (pasta Google Drive não encontrada).';
            }
        }

        $config['last_run_at'] = (new \DateTime())->format('Y-m-d H:i:s');
        $config['last_status'] = $status;
        $config['last_error'] = $exitCode === 0 ? null : implode("\n", $output);
        $this->model->saveConfig($config);

        return [
            'status' => $status,
            'exit' => $exitCode,
            'output' => $output,
            'file' => $dumpFile,
            'mode' => $mode,
        ];
    }

    private function defaultDumpPath(): string
    {
        $local = realpath(__DIR__ . '/../../../bin/mysqldump.exe');
        if ($local !== false) {
            return $local;
        }
        return 'C:\\backups\\bin\\mysqldump.exe';
    }
}
