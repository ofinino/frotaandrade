<?php
namespace App\Modulos\Seguranca\Models;

class BackupModel
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS seg_backup_config (
            id TINYINT PRIMARY KEY,
            local_path VARCHAR(255) NOT NULL,
            drive_path VARCHAR(255) DEFAULT '',
            mysqldump_path VARCHAR(255) NOT NULL,
            schedule ENUM('manual','30min','daily') NOT NULL DEFAULT 'manual',
            daily_hour VARCHAR(5) NOT NULL DEFAULT '02:00',
            last_run_at DATETIME NULL,
            last_status TEXT NULL,
            last_error TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->db->exec($sql);
    }

    public function getConfig(): array
    {
        $stmt = $this->db->query("SELECT * FROM seg_backup_config WHERE id = 1");
        $cfg = $stmt->fetch() ?: null;
        $defaultDump = $this->defaultDumpPath();
        if (!$cfg) {
            $default = [
                'id' => 1,
                'local_path' => 'C:\\backups\\db',
                'drive_path' => '',
                'mysqldump_path' => $defaultDump,
                'schedule' => 'manual',
                'daily_hour' => '02:00',
                'last_run_at' => null,
                'last_status' => null,
                'last_error' => null,
            ];
            $this->saveConfig($default);
            return $default;
        }
        // garante caminho padrão se vier vazio
        if (empty($cfg['mysqldump_path'])) {
            $cfg['mysqldump_path'] = $defaultDump;
        }
        return $cfg;
    }

    public function saveConfig(array $data): void
    {
        $stmt = $this->db->prepare("REPLACE INTO seg_backup_config
            (id, local_path, drive_path, mysqldump_path, schedule, daily_hour, last_run_at, last_status, last_error)
            VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['local_path'] ?? 'C:\\backups\\db',
            $data['drive_path'] ?? '',
            $data['mysqldump_path'] ?? 'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            $data['schedule'] ?? 'manual',
            $data['daily_hour'] ?? '02:00',
            $data['last_run_at'] ?? null,
            $data['last_status'] ?? null,
            $data['last_error'] ?? null,
        ]);
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
