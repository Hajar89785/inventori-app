<?php
/**
 * GET /api/get_settings.php
 * Mengembalikan pengaturan aplikasi & branding terkini
 */

require_once __DIR__ . '/../app_settings.php';

$settings = getAppSettings($pdo);

jsonResponse(true, 'Pengaturan sistem berhasil dimuat.', $settings);
