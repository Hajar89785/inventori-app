-- ============================================
-- INVENTO - Migrasi: Tambah Tabel Settings
-- Sistem Inventaris & Peminjaman Barang
-- ============================================

USE `invento_db`;

-- ─── Tabel Pengaturan & Branding ─────────────
CREATE TABLE IF NOT EXISTS `settings` (
  `id`            INT UNSIGNED    NOT NULL DEFAULT 1,
  `app_name`      VARCHAR(255)    NOT NULL DEFAULT 'INVENTO',
  `app_subtitle`  VARCHAR(255)    NOT NULL DEFAULT 'Inventaris Himpunan',
  `app_logo`      VARCHAR(255)    NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Data Awal Pengaturan ────────────────────
INSERT INTO `settings` (`id`, `app_name`, `app_subtitle`, `app_logo`)
VALUES (1, 'INVENTO', 'Inventaris Himpunan', '')
ON DUPLICATE KEY UPDATE `id` = 1;
