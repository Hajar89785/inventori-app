<?php
/**
 * Entry Point Router INVENTO
 * Memastikan pengguna wajib login terlebih dahulu sebelum mengakses sistem.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['invento_logged_in']) || $_SESSION['invento_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if (isset($_SESSION['invento_user']['role']) && $_SESSION['invento_user']['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
} else {
    header('Location: user_katalog.php');
    exit;
}
