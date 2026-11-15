<?php
/**
 * GET /api/get_dashboard.php
 * Mengembalikan statistik dashboard:
 *   - Total unit barang (sum total_stok)
 *   - Total unit tersedia (sum stok_tersedia)
 *   - Total unit dipinjam
 *   - Jumlah kategori unik
 *   - Jumlah jenis barang
 */

require_once __DIR__ . '/../koneksi.php';

try {
    $stmt = $pdo->query("
        SELECT
            COALESCE(SUM(total_stok), 0)                          AS total_unit,
            COALESCE(SUM(stok_tersedia), 0)                       AS total_tersedia,
            COALESCE(SUM(total_stok - stok_tersedia), 0)          AS total_dipinjam,
            COUNT(DISTINCT kategori)                              AS total_kategori,
            COUNT(*)                                              AS total_jenis
        FROM barang
    ");

    $stats = $stmt->fetch();

    // Cast to int
    $stats = array_map('intval', $stats);

    jsonResponse(true, '', $stats);

} catch (PDOException $e) {
    jsonResponse(false, 'Gagal mengambil data dashboard: ' . $e->getMessage(), null, 500);
}

