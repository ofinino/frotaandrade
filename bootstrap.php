<?php
// Bootstrap raiz: sessao, config e nucleo compartilhado
session_start();

$config = require __DIR__ . '/config.php';
require __DIR__ . '/app/Core/autoload.php';
require __DIR__ . '/app/Core/Core.php';

// Helper para redirecionar mesmo se algum output j? ocorreu
function safe_redirect(string $url): void {
    echo '<script>window.location.href="'.htmlspecialchars($url, ENT_QUOTES).'";</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url='.htmlspecialchars($url, ENT_QUOTES).'" /></noscript>';
    exit;
}
