<?php
// Bootstrap interno (use o bootstrap raiz quando poss?vel).
if (!function_exists('db')) {
    session_start();
    $config = require __DIR__ . '/../../config.php';
    require __DIR__ . '/autoload.php';
    require __DIR__ . '/Core.php';
}

if (!function_exists('safe_redirect')) {
    function safe_redirect(string $url): void {
        echo '<script>window.location.href="'.htmlspecialchars($url, ENT_QUOTES).'";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url='.htmlspecialchars($url, ENT_QUOTES).'" /></noscript>';
        exit;
    }
}
