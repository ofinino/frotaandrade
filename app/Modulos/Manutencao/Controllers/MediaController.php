<?php
namespace App\Modulos\Manutencao\Controllers;

class MediaController
{
    public function serve(): void
    {
        try {
            $requested = $_GET['f'] ?? '';
            $requested = is_string($requested) ? urldecode($requested) : '';
            if (!$requested) {
                http_response_code(404);
                echo 'Arquivo nao informado.';
                return;
            }

            // Base sem criar pasta (evita mkdir em GET). Permite caminho configurável em config['uploads_path'].
            $baseDirRaw = \upload_base_dir();
            $baseDir = realpath($baseDirRaw) ?: $baseDirRaw;
            if (!is_dir($baseDir)) {
                http_response_code(404);
                echo 'Pasta de uploads inexistente.';
                return;
            }
            $baseDir = rtrim($baseDir, '/\\');

            // normaliza caminho e impede traversal
            $relative = str_replace('\\', '/', $requested);
            $relative = ltrim($relative, '/');
            if (strpos($relative, 'uploads/') === 0) {
                $relative = substr($relative, strlen('uploads/'));
            }
            // bloqueia ../
            if (strpos($relative, '../') !== false) {
                http_response_code(403);
                echo 'Caminho invalido.';
                return;
            }

            $fullPath = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $real = realpath($fullPath) ?: $fullPath;

            $baseNorm = rtrim(str_replace('\\', '/', $baseDir), '/');
            $realNorm = str_replace('\\', '/', $real);

            if (!is_file($real) || strpos($realNorm, $baseNorm) !== 0) {
                http_response_code(404);
                echo 'Arquivo nao encontrado.';
                @error_log('[media] arquivo nao encontrado: ' . $real);
                return;
            }
            if (!is_readable($real)) {
                http_response_code(403);
                echo 'Arquivo sem permissao de leitura.';
                @error_log('[media] sem leitura: ' . $real);
                return;
            }

            $mime = 'application/octet-stream';
            if (function_exists('mime_content_type')) {
                $detected = @mime_content_type($real);
                if ($detected) {
                    $mime = $detected;
                }
            }
            header('Content-Type: ' . $mime);
            header('Content-Disposition: inline; filename="' . basename($real) . '"');
            @readfile($real);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Erro ao carregar arquivo.';
            @error_log('[media] erro: ' . $e->getMessage());
        }
    }
}
