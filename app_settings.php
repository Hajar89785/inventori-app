<?php
/**
 * INVENTO — Helper Pengaturan Sistem & Branding
 */
require_once __DIR__ . '/koneksi.php';

/**
 * Mengambil pengaturan sistem & branding dari tabel settings.
 * Jika tabel belum ada, otomatis dibuat dengan data awal.
 *
 * @param PDO $pdo
 * @return array{app_name: string, app_subtitle: string, app_logo: string}
 */
function getAppSettings(PDO $pdo): array
{
    static $cachedSettings = null;
    if ($cachedSettings !== null) {
        return $cachedSettings;
    }

    $default = [
        'app_name'     => 'INVENTO',
        'app_subtitle' => 'Inventaris Himpunan',
        'app_logo'     => '',
    ];

    try {
        // Buat tabel jika belum ada
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `settings` (
              `id` INT UNSIGNED NOT NULL DEFAULT 1,
              `app_name` VARCHAR(255) NOT NULL DEFAULT 'INVENTO',
              `app_subtitle` VARCHAR(255) NOT NULL DEFAULT 'Inventaris Himpunan',
              `app_logo` VARCHAR(255) NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Cek data baris id = 1
        $stmt = $pdo->query("SELECT * FROM `settings` WHERE `id` = 1 LIMIT 1");
        $row = $stmt->fetch();

        if (!$row) {
            $pdo->exec("
                INSERT INTO `settings` (`id`, `app_name`, `app_subtitle`, `app_logo`)
                VALUES (1, 'INVENTO', 'Inventaris Himpunan', '')
                ON DUPLICATE KEY UPDATE `id` = 1;
            ");
            $cachedSettings = $default;
            return $cachedSettings;
        }

        $cachedSettings = [
            'app_name'     => !empty($row['app_name']) ? (string)$row['app_name'] : $default['app_name'],
            'app_subtitle' => !empty($row['app_subtitle']) ? (string)$row['app_subtitle'] : $default['app_subtitle'],
            'app_logo'     => !empty($row['app_logo']) ? (string)$row['app_logo'] : '',
        ];

        return $cachedSettings;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Render elemen icon / logo HTML
 *
 * @param string $logoPath
 * @param string $defaultIcon
 * @param string $imgStyle
 * @return string
 */
function renderBrandLogoHtml(string $logoPath, string $defaultIcon = 'bi-box-seam-fill', string $imgStyle = 'max-width:100%;max-height:100%;object-fit:contain;border-radius:8px;'): string
{
    if (!empty($logoPath) && file_exists(__DIR__ . '/' . ltrim($logoPath, '/'))) {
        $src = htmlspecialchars(ltrim($logoPath, '/'));
        return '<img src="' . $src . '" alt="Logo" style="' . $imgStyle . '">';
    }
    return '<i class="bi ' . htmlspecialchars($defaultIcon) . '"></i>';
}
