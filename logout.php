<?php
require __DIR__ . '/bootstrap.php';
logout_user();
header('Location: login.php');
exit;
