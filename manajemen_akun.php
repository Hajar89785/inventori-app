<?php
/**
 * INVENTO — Manajemen Akun Router
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['invento_logged_in']) || $_SESSION['invento_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['invento_user']['role']) || $_SESSION['invento_user']['role'] !== 'admin') {
    header('Location: user_katalog.php');
    exit;
}

require_once __DIR__ . '/app_settings.php';
$settings = getAppSettings($pdo);

// Sinkronkan data sesi & settings ke localStorage dan sajikan manajemen_akun.html
$content = file_get_contents(__DIR__ . '/manajemen_akun.html');
$userJson = json_encode($_SESSION['invento_user']);
$settingsJson = json_encode($settings);
$syncScript = "<script>localStorage.setItem('invento_user', " . json_encode($userJson) . "); localStorage.setItem('invento_settings', " . json_encode($settingsJson) . ");</script>";
echo str_replace('</head>', $syncScript . '</head>', $content);
