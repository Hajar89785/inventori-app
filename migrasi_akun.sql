-- ============================================
-- INVENTO - Migrasi: Tambah Tabel Akun
-- ============================================

USE `invento_db`;

-- ─── Tabel Akun ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `akun` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `nama_lengkap`  VARCHAR(255)    NOT NULL,
  `username`      VARCHAR(100)    NOT NULL,
  `email`         VARCHAR(255)    NULL,
  `role`          ENUM('admin','user') NOT NULL DEFAULT 'user',
  `status`        ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  INDEX `idx_role` (`role`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Data Awal Akun ─────────────────────────────────────
INSERT INTO `akun` (`nama_lengkap`, `username`, `email`, `role`, `status`) VALUES
  ('Administrator',     'admin',      'admin@invento.local',       'admin', 'aktif'),
  ('Ahmad Fadli',       'ahmadfadli', 'ahmad.fadli@mail.com',      'user',  'aktif'),
  ('Siti Nurhaliza',    'sitinur',    'siti.nurhaliza@mail.com',   'user',  'aktif'),
  ('Budi Santoso',      'budisantoso','budi.santoso@mail.com',     'user',  'aktif'),
  ('Dewi Lestari',      'dewilestari','dewi.lestari@mail.com',     'user',  'aktif'),
  ('Rini Wulandari',    'riniwulan',  'rini.wulandari@mail.com',   'user',  'aktif'),
  ('Eko Prasetyo',      'ekoprasetyo','eko.prasetyo@mail.com',     'user',  'aktif'),
  ('Fajar Nugroho',     'fajarnugroho','fajar.nugroho@mail.com',   'user',  'aktif'),
  ('Hendra Wijaya',     'hendrawij',  'hendra.wijaya@mail.com',    'user',  'aktif'),
  ('Maya Sari',         'mayasari',   'maya.sari@mail.com',        'user',  'nonaktif')
ON DUPLICATE KEY UPDATE `nama_lengkap` = VALUES(`nama_lengkap`);
