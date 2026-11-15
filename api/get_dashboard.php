<?php
/**
 * GET /api/get_dashboard.php
 * Mengembalikan statistik dashboard untuk gaya Mazer:
 *   - Statistik total unit, tersedia, dipinjam, kategori
 *   - Statistik peminjaman tahunan per bulan (Jan - Des)
 *   - Distribusi kategori
 *   - Aktivitas peminjaman terbaru
 */

require_once __DIR__ . '/../koneksi.php';

try {
    // 1. Ringkasan Unit & Kategori
    $stmt = $pdo->query("
        SELECT
            COALESCE(SUM(total_stok), 0)                          AS total_unit,
            COALESCE(SUM(stok_tersedia), 0)                       AS total_tersedia,
            COALESCE(SUM(total_stok - stok_tersedia), 0)          AS total_dipinjam,
            COUNT(DISTINCT kategori)                              AS total_kategori,
            COUNT(*)                                              AS total_jenis
        FROM barang
    ");
    $stats = array_map('intval', $stmt->fetch());

    // 2. Statistik Peminjaman Tahunan (Per Bulan Jan - Des tahun berjalan)
    $tahunIni = (int) date('Y');
    $stmtMonthly = $pdo->prepare("
        SELECT 
            MONTH(tanggal_pinjam) AS bulan,
            COUNT(*) AS total
        FROM peminjaman
        WHERE YEAR(tanggal_pinjam) = :tahun
        GROUP BY MONTH(tanggal_pinjam)
        ORDER BY bulan ASC
    ");
    $stmtMonthly->execute([':tahun' => $tahunIni]);
    $monthlyRows = $stmtMonthly->fetchAll();

    // Inisialisasi 12 bulan dengan 0
    $monthlyData = array_fill(1, 12, 0);
    foreach ($monthlyRows as $row) {
        $monthlyData[(int)$row['bulan']] = (int)$row['total'];
    }

    // 3. Distribusi Stok per Kategori
    $stmtCat = $pdo->query("
        SELECT 
            kategori, 
            COALESCE(SUM(total_stok), 0) AS total,
            COALESCE(SUM(stok_tersedia), 0) AS tersedia
        FROM barang
        GROUP BY kategori
        ORDER BY total DESC
    ");
    $categoryData = $stmtCat->fetchAll();

    // 4. Aktivitas Peminjaman Terbaru (6 item)
    $stmtRecent = $pdo->query("
        SELECT p.*, b.nama_barang, b.kategori, b.ikon
        FROM peminjaman p
        JOIN barang b ON p.id_barang = b.id
        ORDER BY p.id DESC
        LIMIT 6
    ");
    $recentLoans = $stmtRecent->fetchAll();

    $response = [
        'stats' => $stats,
        'year' => $tahunIni,
        'monthly_loans' => array_values($monthlyData), // array [Jan, Feb, ..., Des]
        'categories' => $categoryData,
        'recent_loans' => $recentLoans
    ];

    jsonResponse(true, '', $response);

} catch (PDOException $e) {
    jsonResponse(false, 'Gagal mengambil data dashboard: ' . $e->getMessage(), null, 500);
}
