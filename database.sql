-- ============================================
-- INVENTO - Database Schema
-- Sistem Inventaris & Peminjaman Barang
-- ============================================

CREATE DATABASE IF NOT EXISTS `invento_db`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `invento_db`;

-- ─── Tabel Barang ────────────────────────────────────────
DROP TABLE IF EXISTS `peminjaman`;
DROP TABLE IF EXISTS `barang`;

CREATE TABLE `barang` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `nama_barang`     VARCHAR(255)    NOT NULL,
  `kategori`        VARCHAR(100)    NOT NULL,
  `total_stok`      INT UNSIGNED    NOT NULL DEFAULT 0,
  `stok_tersedia`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `ikon`            VARCHAR(50)     NOT NULL DEFAULT 'bi-box',
  `deskripsi`       TEXT            NULL,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_kategori` (`kategori`),
  INDEX `idx_stok_tersedia` (`stok_tersedia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Tabel Peminjaman ────────────────────────────────────
CREATE TABLE `peminjaman` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `id_barang`       INT UNSIGNED    NOT NULL,
  `nama_peminjam`   VARCHAR(255)    NOT NULL,
  `tanggal_pinjam`  DATE            NOT NULL,
  `tanggal_kembali` DATE            NOT NULL,
  `durasi`          INT UNSIGNED    NOT NULL DEFAULT 1,
  `status_pinjam`   ENUM('aktif','dikembalikan','terlambat') NOT NULL DEFAULT 'aktif',
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_id_barang` (`id_barang`),
  INDEX `idx_status` (`status_pinjam`),
  INDEX `idx_tanggal_kembali` (`tanggal_kembali`),
  CONSTRAINT `fk_peminjaman_barang`
    FOREIGN KEY (`id_barang`) REFERENCES `barang`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Data Awal (Seed) ───────────────────────────────────
INSERT INTO `barang` (`nama_barang`, `kategori`, `total_stok`, `stok_tersedia`, `ikon`, `deskripsi`) VALUES
  ('Laptop ASUS VivoBook',     'Elektronik',   5,  4, 'bi-laptop',        'Laptop 14 inci untuk kebutuhan kerja dan presentasi.'),
  ('Proyektor Epson EB-X51',   'Elektronik',   3,  1, 'bi-projector',     'Proyektor 3LCD dengan kecerahan 3800 lumen.'),
  ('Kamera Canon EOS M50',     'Elektronik',   2,  2, 'bi-camera',        'Kamera mirrorless 24.1 MP dengan video 4K.'),
  ('Meja Lipat Portable',      'Furnitur',     10,  7, 'bi-grid-3x3-gap',  'Meja lipat aluminium serbaguna.'),
  ('Kursi Plastik Stackable',  'Furnitur',     25, 20, 'bi-border-style',  'Kursi plastik stackable untuk acara.'),
  ('Whiteboard 120x80 cm',     'Perlengkapan',  4,  3, 'bi-easel',        'Whiteboard magnetik dengan penghapus dan spidol.'),
  ('Sound System Portable',    'Elektronik',    2,  1, 'bi-speaker',      'Speaker portable 100W dengan mic wireless.'),
  ('Tenda Camping 4 Orang',    'Outdoor',       6,  4, 'bi-house',        'Tenda waterproof kapasitas 4 orang.'),
  ('Toolkit Set Lengkap',      'Perlengkapan',  3,  3, 'bi-tools',        'Set peralatan tangan 120 pcs dalam koper.'),
  ('Printer HP LaserJet',      'Elektronik',    2,  1, 'bi-printer',      'Printer laser monokrom dengan WiFi.');

INSERT INTO `peminjaman` (`id_barang`, `nama_peminjam`, `tanggal_pinjam`, `tanggal_kembali`, `durasi`, `status_pinjam`) VALUES
  (1,  'Ahmad Fadli',      '2026-11-10', '2026-11-13', 3, 'aktif'),
  (2,  'Siti Nurhaliza',   '2026-11-08', '2026-11-13', 5, 'aktif'),
  (2,  'Budi Santoso',     '2026-11-12', '2026-11-14', 2, 'aktif'),
  (4,  'Dewi Lestari',     '2026-11-09', '2026-11-16', 7, 'aktif'),
  (4,  'Rini Wulandari',   '2026-11-11', '2026-11-14', 3, 'aktif'),
  (4,  'Eko Prasetyo',     '2026-11-13', '2026-11-15', 2, 'aktif'),
  (5,  'Fajar Nugroho',    '2026-11-07', '2026-11-12', 5, 'aktif'),
  (5,  'Rizky Ramadhan',   '2026-11-05', '2026-11-09', 4, 'aktif'),
  (5,  'Sari Dewi',        '2026-11-11', '2026-11-14', 3, 'aktif'),
  (5,  'Agus Hermawan',    '2026-11-14', '2026-11-15', 1, 'aktif'),
  (5,  'Nina Safitri',     '2026-11-13', '2026-11-15', 2, 'aktif'),
  (6,  'Lina Marlina',     '2026-11-14', '2026-11-15', 1, 'aktif'),
  (7,  'Hendra Wijaya',    '2026-11-10', '2026-11-14', 4, 'aktif'),
  (8,  'Maya Sari',        '2026-11-06', '2026-11-13', 7, 'aktif'),
  (8,  'Doni Kurniawan',   '2026-11-12', '2026-11-15', 3, 'aktif'),
  (10, 'Putri Handayani',  '2026-11-13', '2026-11-15', 2, 'aktif');

