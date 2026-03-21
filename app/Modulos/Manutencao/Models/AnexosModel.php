<?php
namespace App\Modulos\Manutencao\Models;

class AnexosModel
{
    private \PDO $db;
    private int $empresaId;
    private ?int $filialId;
    private array $filiais;

    public function __construct(\PDO $db, int $empresaId, ?int $filialId, array $filiais = [])
    {
        $this->db = $db;
        $this->empresaId = $empresaId;
        $this->filialId = $filialId;
        $this->filiais = $filiais;
    }

    private function garantirDiretorio(string $sub): string
    {
        $base = ensure_upload_dir();
        $dir = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . trim($sub, '/\\');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private function normalizarArquivos(array $files): array
    {
        if (!isset($files['name'])) {
            return [];
        }
        $names = (array)$files['name'];
        $tmp = (array)$files['tmp_name'];
        $types = (array)$files['type'];
        $sizes = (array)$files['size'];
        $errors = (array)$files['error'];
        $out = [];
        foreach ($names as $i => $name) {
            if (empty($name) || ($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $out[] = [
                'name' => $name,
                'tmp' => $tmp[$i] ?? '',
                'type' => $types[$i] ?? '',
                'size' => $sizes[$i] ?? 0,
            ];
        }
        return $out;
    }

    public function salvar(string $ownerType, int $ownerId, array $files, int $userId): array
    {
        $permitidos = ['image/jpeg','image/png','image/webp','image/gif'];
        $maxBytes = 5 * 1024 * 1024;
        $normalizados = $this->normalizarArquivos($files);
        $salvos = [];
        $subdir = $ownerType === 'ss' ? 'uploads/ss' : 'uploads/os';
        $dir = $this->garantirDiretorio($subdir);

        foreach ($normalizados as $file) {
            if ($file['size'] > $maxBytes) {
                continue;
            }
            $mime = $file['type'];
            if (!in_array($mime, $permitidos, true)) {
                continue;
            }
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $safeExt = preg_replace('/[^a-z0-9]+/', '', $ext) ?: 'jpg';
            $filename = uniqid($ownerType . '_', true) . '.' . $safeExt;
            $dest = $dir . DIRECTORY_SEPARATOR . $filename;
            $moved = @move_uploaded_file($file['tmp'], $dest);
            if (!$moved) {
                $data = @file_get_contents($file['tmp']);
                if ($data !== false) {
                    $moved = @file_put_contents($dest, $data) !== false;
                }
            }
            if ($moved) {
                $relative = $subdir . '/' . $filename;
                $this->db->prepare(
                    'INSERT INTO man_attachments (empresa_id, filial_id, owner_type, owner_id, file_path, original_name, mime_type, size, uploaded_by, uploaded_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                )->execute([
                    $this->empresaId,
                    $this->filialId,
                    $ownerType,
                    $ownerId,
                    $relative,
                    $file['name'],
                    $mime,
                    $file['size'],
                    $userId,
                ]);
                $salvos[] = $relative;
            }
        }
        return $salvos;
    }

    public function listar(string $ownerType, int $ownerId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM man_attachments WHERE empresa_id = ? AND owner_type = ? AND owner_id = ? ORDER BY uploaded_at DESC'
        );
        $stmt->execute([$this->empresaId, $ownerType, $ownerId]);
        return $stmt->fetchAll();
    }
}
