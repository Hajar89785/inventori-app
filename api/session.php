<?php
/**
 * GET /api/session.php
 * Cek status sesi aktif pengguna
 */

require_once __DIR__ . '/../koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['invento_logged_in']) || empty($_SESSION['invento_user'])) {
    jsonResponse(false, 'Belum login atau sesi telah berakhir.', null, 401);
}

jsonResponse(true, 'Sesi aktif', [
    'user' => $_SESSION['invento_user']
]);
