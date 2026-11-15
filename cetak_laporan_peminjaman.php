<?php
/**
 * INVENTO — Halaman Cetak Laporan Transaksi Peminjaman (Print / PDF)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/koneksi.php';

$currentUser = $_SESSION['invento_user'] ?? [
    'nama_lengkap' => 'Administrator',
    'role' => 'admin'
];

$periode = trim($_GET['periode'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$searchQuery = trim($_GET['q'] ?? '');

$where = [];
$params = [];
$periodeJudul = 'Semua Periode';

$bulanIndo = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April',   '05' => 'Mei',      '06' => 'Juni',
    '07' => 'Juli',    '08' => 'Agustus',  '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

if (!empty($periode) && preg_match('/^\d{4}-\d{2}$/', $periode)) {
    $where[] = "DATE_FORMAT(p.tanggal_pinjam, '%Y-%m') = :periode";
    $params[':periode'] = $periode;
    $parts = explode('-', $periode);
    $periodeJudul = 'Bulan ' . ($bulanIndo[$parts[1]] ?? $parts[1]) . ' ' . $parts[0];
}

if (!empty($statusFilter)) {
    if ($statusFilter === 'disetujui') {
        $where[] = "(p.status_pinjam = 'disetujui' OR p.status_pinjam = 'aktif')";
    } elseif ($statusFilter === 'selesai') {
        $where[] = "(p.status_pinjam = 'selesai' OR p.status_pinjam = 'dikembalikan')";
    } else {
        $where[] = "p.status_pinjam = :status";
        $params[':status'] = $statusFilter;
    }
}

if (!empty($searchQuery)) {
    $q = '%' . $searchQuery . '%';
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

// Hitung rekap statistik
$totalTransaksi = count($rows);
$totalMenunggu  = 0;
$totalAktif     = 0;
$totalSelesai   = 0;
$totalDitolak   = 0;

foreach ($rows as $r) {
    $st = $r['status_pinjam'];
    if ($st === 'menunggu') $totalMenunggu++;
    elseif ($st === 'disetujui' || $st === 'aktif') $totalAktif++;
    elseif ($st === 'selesai' || $st === 'dikembalikan') $totalSelesai++;
    elseif ($st === 'ditolak') $totalDitolak++;
}

$tanggalCetak = date('d') . ' ' . ($bulanIndo[date('m')] ?? date('F')) . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Laporan Peminjaman Barang &mdash; <?= htmlspecialchars($periodeJudul) ?></title>
  <!-- Google Fonts: Nunito -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Nunito', 'Segoe UI', Arial, sans-serif;
    }
    body {
      background: #f1f5f9;
      color: #1e293b;
      padding: 24px;
    }
    .print-actions-bar {
      max-width: 1050px;
      margin: 0 auto 20px;
      background: #ffffff;
      padding: 14px 20px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 4px 16px rgba(0,0,0,0.06);
    }
    .btn-action {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      border-radius: 8px;
      font-size: 0.9rem;
      font-weight: 700;
      cursor: pointer;
      border: none;
      text-decoration: none;
      transition: all 0.2s ease;
    }
    .btn-print {
      background: #435ebe;
      color: #ffffff;
    }
    .btn-print:hover { background: #364fa8; }
    .btn-csv {
      background: #10b981;
      color: #ffffff;
    }
    .btn-csv:hover { background: #059669; }
    .btn-back {
      background: #f1f5f9;
      color: #475569;
    }
    .btn-back:hover { background: #e2e8f0; }

    /* Kertas Cetak */
    .report-paper {
      max-width: 1050px;
      margin: 0 auto;
      background: #ffffff;
      padding: 40px 48px;
      border-radius: 12px;
      box-shadow: 0 4px 24px rgba(0,0,0,0.08);
      min-height: 297mm;
    }

    /* Kop Surat */
    .kop-surat {
      display: flex;
      align-items: center;
      gap: 20px;
      padding-bottom: 14px;
      border-bottom: 3px double #1e293b;
      margin-bottom: 24px;
    }
    .kop-logo {
      width: 70px;
      height: 70px;
      background: linear-gradient(135deg, #435ebe, #697feb);
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #ffffff;
      font-size: 36px;
      flex-shrink: 0;
    }
    .kop-text {
      flex: 1;
      text-align: center;
    }
    .kop-text h2 {
      font-size: 1.35rem;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: #1e293b;
      margin-bottom: 4px;
    }
    .kop-text h4 {
      font-size: 1rem;
      font-weight: 700;
      color: #435ebe;
      margin-bottom: 3px;
    }
    .kop-text p {
      font-size: 0.8rem;
      color: #64748b;
    }

    /* Judul Laporan */
    .report-title-box {
      text-align: center;
      margin-bottom: 24px;
    }
    .report-title-box h3 {
      font-size: 1.2rem;
      font-weight: 800;
      text-transform: uppercase;
      color: #0f172a;
      letter-spacing: 0.5px;
    }
    .report-title-box p {
      font-size: 0.88rem;
      color: #475569;
      margin-top: 4px;
      font-weight: 600;
    }

    /* Rekap Ringkasan Stat */
    .summary-grid {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 12px;
      margin-bottom: 24px;
    }
    .summary-box {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 10px 12px;
      text-align: center;
    }
    .summary-box h4 {
      font-size: 1.25rem;
      font-weight: 800;
      color: #1e293b;
      margin-bottom: 2px;
    }
    .summary-box p {
      font-size: 0.72rem;
      color: #64748b;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.4px;
    }

    /* Tabel Laporan */
    .report-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 32px;
      font-size: 0.84rem;
    }
    .report-table th,
    .report-table td {
      border: 1px solid #cbd5e1;
      padding: 8px 10px;
    }
    .report-table th {
      background: #f1f5f9;
      font-weight: 800;
      text-transform: uppercase;
      font-size: 0.76rem;
      color: #1e293b;
      letter-spacing: 0.4px;
    }
    .report-table tbody tr:nth-child(even) {
      background: #fcfdfe;
    }
    .status-badge {
      display: inline-block;
      padding: 3px 8px;
      border-radius: 6px;
      font-size: 0.75rem;
      font-weight: 700;
      text-align: center;
    }
    .st-menunggu { background: #fef3c7; color: #b45309; }
    .st-dipinjam { background: #e0e7ff; color: #3730a3; }
    .st-selesai  { background: #dcfce7; color: #15803d; }
    .st-ditolak  { background: #fee2e2; color: #b91c1c; }

    /* Tanda Tangan */
    .signature-section {
      display: flex;
      justify-content: space-between;
      margin-top: 40px;
      padding: 0 20px;
      page-break-inside: avoid;
    }
    .sign-box {
      width: 250px;
      text-align: center;
      font-size: 0.88rem;
    }
    .sign-line {
      height: 70px;
    }
    .sign-name {
      font-weight: 800;
      text-decoration: underline;
      color: #0f172a;
    }
    .sign-role {
      font-size: 0.8rem;
      color: #64748b;
      margin-top: 2px;
    }

    /* Print Styles */
    @media print {
      body {
        background: #ffffff;
        padding: 0;
      }
      .no-print {
        display: none !important;
      }
      .report-paper {
        box-shadow: none;
        padding: 10mm 15mm;
        max-width: 100%;
        border-radius: 0;
      }
      .report-table th {
        background: #e2e8f0 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      .summary-box {
        background: #f8fafc !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      .status-badge {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      @page {
        size: A4 portrait;
        margin: 10mm;
      }
    }
  </style>
</head>
<body>

  <!-- Action Bar (Hanya Tampil di Layar, Tersembunyi saat Cetak) -->
  <div class="print-actions-bar no-print">
    <div style="display:flex;align-items:center;gap:10px;">
      <a href="admin_peminjaman.php" class="btn-action btn-back">
        <i class="bi bi-arrow-left"></i> Kembali ke Sistem
      </a>
      <span style="font-size:0.85rem;color:#64748b;font-weight:600;">
        Menampilkan <strong><?= $totalTransaksi ?></strong> data peminjaman (<?= htmlspecialchars($periodeJudul) ?>)
      </span>
    </div>
    <div style="display:flex;align-items:center;gap:8px;">
      <a href="api/export_peminjaman_csv.php?periode=<?= urlencode($periode) ?>&status=<?= urlencode($statusFilter) ?>&q=<?= urlencode($searchQuery) ?>" class="btn-action btn-csv">
        <i class="bi bi-file-earmark-spreadsheet-fill"></i> Unduh File CSV
      </a>
      <button class="btn-action btn-print" onclick="window.print()">
        <i class="bi bi-printer-fill"></i> Cetak Dokumen / Simpan PDF
      </button>
    </div>
  </div>

  <!-- Lembar Kertas Laporan -->
  <div class="report-paper">
    
    <!-- Kop Surat Formal -->
    <div class="kop-surat">
      <div class="kop-logo">
        <i class="bi bi-box-seam-fill"></i>
      </div>
      <div class="kop-text">
        <h2>Sistem Inventaris &amp; Peminjaman Barang (INVENTO)</h2>
        <h4>HIMPUNAN MAHASISWA &bull; KELUARGA BESAR MAHASISWA</h4>
        <p>Gedung Sekretariat Bersama, Kampus Terpadu &bull; Email: sekretariat@invento.local &bull; Website: invento.app</p>
      </div>
    </div>

    <!-- Judul Laporan -->
    <div class="report-title-box">
      <h3>LAPORAN TRANSAKSI PEMINJAMAN BARANG</h3>
      <p>Periode: <strong><?= htmlspecialchars($periodeJudul) ?></strong></p>
    </div>

    <!-- Ringkasan Statistik -->
    <div class="summary-grid">
      <div class="summary-box">
        <h4><?= $totalTransaksi ?></h4>
        <p>Total Pengajuan</p>
      </div>
      <div class="summary-box">
        <h4 style="color:#d97706;"><?= $totalMenunggu ?></h4>
        <p>Menunggu</p>
      </div>
      <div class="summary-box">
        <h4 style="color:#435ebe;"><?= $totalAktif ?></h4>
        <p>Sedang Dipinjam</p>
      </div>
      <div class="summary-box">
        <h4 style="color:#10b981;"><?= $totalSelesai ?></h4>
        <p>Selesai Kembali</p>
      </div>
      <div class="summary-box">
        <h4 style="color:#ef4444;"><?= $totalDitolak ?></h4>
        <p>Ditolak</p>
      </div>
    </div>

    <!-- Tabel Data Peminjaman -->
    <table class="report-table">
      <thead>
        <tr>
          <th style="width:35px;text-align:center;">No</th>
          <th>Nama Peminjam</th>
          <th>Barang Inventaris</th>
          <th style="width:50px;text-align:center;">Jml</th>
          <th style="width:90px;text-align:center;">Tgl Pinjam</th>
          <th style="width:90px;text-align:center;">Jadwal Kembali</th>
          <th>Keperluan / Kegiatan</th>
          <th style="width:110px;text-align:center;">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="8" style="text-align:center;padding:24px;color:#94a3b8;">
              Tidak ada data transaksi peminjaman pada periode ini.
            </td>
          </tr>
        <?php else: ?>
          <?php 
          $no = 1;
          foreach ($rows as $r): 
            $st = $r['status_pinjam'];
            $stBadge = 'st-menunggu';
            $stLabel = 'Menunggu';
            if ($st === 'disetujui' || $st === 'aktif') {
                $stBadge = 'st-dipinjam';
                $stLabel = 'Dipinjam';
            } elseif ($st === 'selesai' || $st === 'dikembalikan') {
                $stBadge = 'st-selesai';
                $stLabel = 'Selesai';
            } elseif ($st === 'ditolak') {
                $stBadge = 'st-ditolak';
                $stLabel = 'Ditolak';
            }
          ?>
            <tr>
              <td style="text-align:center;font-weight:700;"><?= $no++ ?></td>
              <td><strong><?= htmlspecialchars($r['nama_peminjam']) ?></strong></td>
              <td><?= htmlspecialchars($r['nama_barang']) ?> <small style="color:#64748b;">(<?= htmlspecialchars($r['kategori'] ?? '') ?>)</small></td>
              <td style="text-align:center;font-weight:700;"><?= (int)($r['jumlah'] ?? 1) ?></td>
              <td style="text-align:center;"><?= date('d/m/Y', strtotime($r['tanggal_pinjam'])) ?></td>
              <td style="text-align:center;"><?= date('d/m/Y', strtotime($r['tanggal_kembali'])) ?></td>
              <td style="font-size:0.8rem;"><?= htmlspecialchars($r['keperluan'] ?? '-') ?></td>
              <td style="text-align:center;">
                <span class="status-badge <?= $stBadge ?>"><?= $stLabel ?></span>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <!-- Tanda Tangan Formal: Peminjam dan Pemberi Barang -->
    <div class="signature-section" style="display:flex;justify-content:space-between;gap:20px;margin-top:40px;">
      <div class="sign-box" style="flex:1;text-align:center;">
        <p style="font-weight:700;">Peminjam (Anggota)</p>
        <p class="sign-role">Yang Menerima Barang</p>
        <div class="sign-line" style="height:65px;"></div>
        <p class="sign-name">( ........................................ )</p>
        <p class="sign-role">NIM / Nama Terang Peminjam</p>
      </div>

      <div class="sign-box" style="flex:1;text-align:center;">
        <p style="font-weight:700;">Mengetahui,</p>
        <p class="sign-role">Ketua Himpunan Mahasiswa</p>
        <div class="sign-line" style="height:65px;"></div>
        <p class="sign-name">Muhammad Rizky Pratama</p>
        <p class="sign-role">NIM. 2201010045</p>
      </div>

      <div class="sign-box" style="flex:1;text-align:center;">
        <p style="font-size:0.84rem;color:#64748b;margin-bottom:2px;">Kota Terpadu, <?= $tanggalCetak ?></p>
        <p style="font-weight:700;">Pemberi Barang</p>
        <p class="sign-role">Pengurus Inventaris &amp; Logistik</p>
        <div class="sign-line" style="height:65px;"></div>
        <p class="sign-name"><?= htmlspecialchars($currentUser['nama_lengkap'] ?? 'Administrator') ?></p>
        <p class="sign-role"><?= ($currentUser['role'] ?? '') === 'admin' ? 'Pengurus Inti INVENTO' : 'Staf Inventaris' ?></p>
      </div>
    </div>

  </div>

</body>
</html>
