<?php
/**
 * INVENTO — User Katalog & Peminjaman Router
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['invento_logged_in']) || $_SESSION['invento_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Sinkronkan data sesi ke localStorage dan sajikan user.html
$userHtml = file_get_contents(__DIR__ . '/user.html');
$userJson = json_encode($_SESSION['invento_user']);
$syncScript = "<script>localStorage.setItem('invento_user', " . json_encode($userJson) . ");</script>";
echo str_replace('</head>', $syncScript . '</head>', $userHtml);
