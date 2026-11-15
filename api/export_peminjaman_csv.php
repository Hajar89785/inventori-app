<?php
/**
 * GET /api/export_peminjaman_csv.php
 * Ekspor data peminjaman ke berkas CSV dengan delimiter titik koma (;) agar
 * otomatis terbagi ke kolom A, B, C, D... di Microsoft Excel (Windows Indonesia).
 * Dilengkapi dengan tanda tangan peminjam dan pemberi barang.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../koneksi.php';

$currentUser = $_SESSION['invento_user'] ?? [
    'nama_lengkap' => 'Administrator',
    'role' => 'admin'
];

try {
    $where = [];
    $params = [];
    $periodeLabel = 'Semua Periode';
    $fileSuffix = 'semua_periode';

    $bulanIndo = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
        '04' => 'April',   '05' => 'Mei',      '06' => 'Juni',
        '07' => 'Juli',    '08' => 'Agustus',  '09' => 'September',
        '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];

    // Filter Periode Bulan (format YYYY-MM)
    if (!empty($_GET['periode'])) {
        $periode = trim($_GET['periode']);
        if (preg_match('/^\d{4}-\d{2}$/', $periode)) {
            $where[] = "DATE_FORMAT(p.tanggal_pinjam, '%Y-%m') = :periode";
            $params[':periode'] = $periode;
            $fileSuffix = $periode;

            $parts = explode('-', $periode);
            $periodeLabel = ($bulanIndo[$parts[1]] ?? $parts[1]) . ' ' . $parts[0];
        }
    } elseif (!empty($_GET['bulan']) && !empty($_GET['tahun'])) {
        $b = str_pad((int)$_GET['bulan'], 2, '0', STR_PAD_LEFT);
        $t = (int)$_GET['tahun'];
        $where[] = "DATE_FORMAT(p.tanggal_pinjam, '%Y-%m') = :periode";
        $params[':periode'] = "$t-$b";
        $fileSuffix = "$t-$b";
        $periodeLabel = ($bulanIndo[$b] ?? $b) . ' ' . $t;
    }

    // Filter Status
    if (!empty($_GET['status'])) {
        $st = trim($_GET['status']);
        if ($st === 'disetujui') {
            $where[] = "(p.status_pinjam = 'disetujui' OR p.status_pinjam = 'aktif')";
        } elseif ($st === 'selesai') {
            $where[] = "(p.status_pinjam = 'selesai' OR p.status_pinjam = 'dikembalikan')";
        } else {
            $where[] = "p.status_pinjam = :status";
            $params[':status'] = $st;
        }
    }

    // Filter Pencarian
    if (!empty($_GET['q'])) {
        $q = '%' . trim($_GET['q']) . '%';
        $where[] = "(p.nama_peminjam LIKE :q1 OR b.nama_barang LIKE :q2 OR p.keperluan LIKE :q3)";
        $params[':q1'] = $q;
        $params[':q2'] = $q;
        $params[':q3'] = $q;
    }

    $sql = "
        SELECT p.*, b.nama_barang, b.kategori 
        FROM peminjaman p
        JOIN barang b ON p.id_barang = b.id
    ";

    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY p.tanggal_pinjam ASC, p.id ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Map status label
    $statusMap = [
        'menunggu'     => 'Menunggu Persetujuan',
        'disetujui'    => 'Sedang Dipinjam',
        'aktif'        => 'Sedang Dipinjam',
        'selesai'      => 'Selesai Dikembalikan',
        'dikembalikan' => 'Selesai Dikembalikan',
        'ditolak'      => 'Ditolak',
        'terlambat'    => 'Terlambat'
    ];

    if (ob_get_level()) {
        ob_end_clean();
    }

    // Header Download File CSV
    $filename = "laporan_peminjaman_invento_{$fileSuffix}.csv";
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // Tulis UTF-8 BOM
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Petunjuk pemisah kolom untuk Microsoft Excel
    fwrite($output, "sep=;\r\n");

    // Header Laporan
    fputcsv($output, ['LAPORAN TRANSAKSI PEMINJAMAN BARANG INVENTARIS'], ';');
    fputcsv($output, ['Sistem Inventaris & Peminjaman Barang (INVENTO) - Himpunan Mahasiswa'], ';');
    fputcsv($output, ['Periode:', $periodeLabel], ';');
    fputcsv($output, ['Tanggal Unduh:', date('d/m/Y H:i') . ' WIB'], ';');
    fputcsv($output, [], ';'); // Baris kosong

    // Baris Header Kolom Tabel
    fputcsv($output, [
        'No',
        'ID Transaksi',
        'Nama Peminjam',
        'Barang Inventaris',
        'Kategori',
        'Jumlah (Unit)',
        'Tanggal Pinjam',
        'Jadwal Kembali',
        'Durasi (Hari)',
        'Keperluan / Kegiatan',
        'Status Peminjaman',
        'Tanggal Pengajuan'
    ], ';');

    // Baris Data Transaksi
    $no = 1;
    $totalUnit = 0;
    foreach ($rows as $r) {
        $statusText = $statusMap[$r['status_pinjam']] ?? ucfirst($r['status_pinjam']);
        $jml = (int)($r['jumlah'] ?? 1);
        $totalUnit += $jml;

        fputcsv($output, [
            $no++,
            'TRX-' . str_pad($r['id'], 5, '0', STR_PAD_LEFT),
            $r['nama_peminjam'],
            $r['nama_barang'],
            $r['kategori'] ?? '-',
            $jml,
            $r['tanggal_pinjam'],
            $r['tanggal_kembali'],
            $r['durasi'] . ' Hari',
            $r['keperluan'] ?? '-',
            $statusText,
            $r['created_at'] ?? '-'
        ], ';');
    }

    // Baris Ringkasan
    fputcsv($output, [], ';');
    fputcsv($output, ['', 'TOTAL TRANSAKSI', count($rows) . ' Transaksi', '', 'TOTAL UNIT:', $totalUnit . ' Unit'], ';');
    fputcsv($output, [], ';');
    fputcsv($output, [], ';');

    // Tanda Tangan: Peminjam dan Pemberi Barang
    $tanggalCetak = date('d') . ' ' . ($bulanIndo[date('m')] ?? date('F')) . ' ' . date('Y');
    fputcsv($output, ['', '', 'Peminjam (Anggota)', '', '', '', '', '', 'Kota Terpadu, ' . $tanggalCetak], ';');
    fputcsv($output, ['', '', 'Yang Menerima Barang', '', '', '', '', '', 'Pemberi Barang (Pengurus Inventaris)'], ';');
    fputcsv($output, ['', '', ''], ';');
    fputcsv($output, ['', '', ''], ';');
    fputcsv($output, ['', '', ''], ';');
    fputcsv($output, ['', '', '( ........................................ )', '', '', '', '', '', '( ' . ($currentUser['nama_lengkap'] ?? 'Administrator') . ' )'], ';');
    fputcsv($output, ['', '', 'NIM / Nama Terang Peminjam', '', '', '', '', '', 'Pengurus Inti Divisi Logistik'], ';');

    fclose($output);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo "Terjadi kesalahan saat mengekspor data: " . $e->getMessage();
    exit;
}
