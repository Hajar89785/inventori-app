<?php
/**
 * GET /api/export_peminjaman_excel.php
 * Ekspor data peminjaman langsung ke berkas Microsoft Excel (.xls) berformat tabel rapi,
 * lengkap dengan garis tabel, warna header, dan tanda tangan Peminjam & Pemberi Barang.
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

    $statusMap = [
        'menunggu'     => ['t' => 'Menunggu Persetujuan', 'bg' => '#fef3c7', 'c' => '#b45309'],
        'disetujui'    => ['t' => 'Sedang Dipinjam',      'bg' => '#e0e7ff', 'c' => '#3730a3'],
        'aktif'        => ['t' => 'Sedang Dipinjam',      'bg' => '#e0e7ff', 'c' => '#3730a3'],
        'selesai'      => ['t' => 'Selesai Dikembalikan', 'bg' => '#dcfce7', 'c' => '#15803d'],
        'dikembalikan' => ['t' => 'Selesai Dikembalikan', 'bg' => '#dcfce7', 'c' => '#15803d'],
        'ditolak'      => ['t' => 'Ditolak',              'bg' => '#fee2e2', 'c' => '#b91c1c'],
        'terlambat'    => ['t' => 'Terlambat',            'bg' => '#fee2e2', 'c' => '#b91c1c']
    ];

    if (ob_get_level()) {
        ob_end_clean();
    }

    $filename = "laporan_peminjaman_invento_{$fileSuffix}.xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    $tanggalCetak = date('d') . ' ' . ($bulanIndo[date('m')] ?? date('F')) . ' ' . date('Y');
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; color: #1e293b; }
    .header-title { font-size: 15pt; font-weight: bold; color: #1e1e2d; text-align: center; }
    .header-sub { font-size: 11pt; color: #435ebe; font-weight: bold; text-align: center; }
    .header-info { font-size: 10pt; color: #64748b; text-align: center; }
    table.data-table { border-collapse: collapse; width: 100%; margin-top: 15px; }
    table.data-table th { background-color: #435ebe; color: #ffffff; font-weight: bold; border: 1px solid #2b3e85; padding: 8px 6px; text-align: center; }
    table.data-table td { border: 1px solid #cbd5e1; padding: 6px 8px; vertical-align: middle; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .font-bold { font-weight: bold; }
    .sign-table { width: 100%; margin-top: 35px; border-collapse: collapse; }
    .sign-table td { border: none; text-align: center; vertical-align: top; }
  </style>
</head>
<body>

  <table>
    <tr><td colspan="10" class="header-title">LAPORAN TRANSAKSI PEMINJAMAN BARANG INVENTARIS</td></tr>
    <tr><td colspan="10" class="header-sub">SISTEM INFORMASI INVENTO &mdash; HIMPUNAN MAHASISWA</td></tr>
    <tr><td colspan="10" class="header-info">Periode: <?= htmlspecialchars($periodeLabel) ?> | Waktu Unduh: <?= date('d/m/Y H:i') ?> WIB</td></tr>
    <tr><td colspan="10"></td></tr>
  </table>

  <table class="data-table">
    <thead>
      <tr>
        <th style="width: 40px;">No</th>
        <th style="width: 100px;">ID Transaksi</th>
        <th style="width: 160px;">Nama Peminjam</th>
        <th style="width: 190px;">Barang Inventaris</th>
        <th style="width: 110px;">Kategori</th>
        <th style="width: 70px;">Jumlah</th>
        <th style="width: 100px;">Tgl Pinjam</th>
        <th style="width: 100px;">Jadwal Kembali</th>
        <th style="width: 80px;">Durasi</th>
        <th style="width: 220px;">Keperluan / Kegiatan</th>
        <th style="width: 130px;">Status</th>
      </tr>
    </thead>
    <tbody>
      <?php 
      $no = 1;
      $totalUnit = 0;
      foreach ($rows as $r): 
        $st = $statusMap[$r['status_pinjam']] ?? ['t' => ucfirst($r['status_pinjam']), 'bg' => '#f1f5f9', 'c' => '#334155'];
        $jml = (int)($r['jumlah'] ?? 1);
        $totalUnit += $jml;
      ?>
        <tr>
          <td class="text-center font-bold"><?= $no++ ?></td>
          <td class="text-center" style="mso-number-format:'\@';">TRX-<?= str_pad($r['id'], 5, '0', STR_PAD_LEFT) ?></td>
          <td class="font-bold"><?= htmlspecialchars($r['nama_peminjam']) ?></td>
          <td><?= htmlspecialchars($r['nama_barang']) ?></td>
          <td><?= htmlspecialchars($r['kategori'] ?? '-') ?></td>
          <td class="text-center font-bold"><?= $jml ?></td>
          <td class="text-center"><?= htmlspecialchars($r['tanggal_pinjam']) ?></td>
          <td class="text-center"><?= htmlspecialchars($r['tanggal_kembali']) ?></td>
          <td class="text-center"><?= $r['durasi'] ?> Hari</td>
          <td><?= htmlspecialchars($r['keperluan'] ?? '-') ?></td>
          <td class="text-center" style="background-color: <?= $st['bg'] ?>; color: <?= $st['c'] ?>; font-weight: bold;">
            <?= $st['t'] ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr>
          <td colspan="11" class="text-center" style="padding: 20px; color: #64748b;">
            Tidak ada transaksi peminjaman pada periode ini.
          </td>
        </tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr style="background-color: #f8fafc; font-weight: bold;">
        <td colspan="5" class="text-right">TOTAL TRANSAKSI :</td>
        <td class="text-center font-bold"><?= $totalUnit ?> Unit</td>
        <td colspan="5">(<?= count($rows) ?> Transaksi Peminjaman)</td>
      </tr>
    </tfoot>
  </table>

  <!-- Area Tanda Tangan: Peminjam dan Pemberi Barang -->
  <table class="sign-table">
    <tr>
      <td style="width: 40%;"></td>
      <td style="width: 20%;"></td>
      <td style="width: 40%; font-size: 10pt; color: #64748b;">Kota Terpadu, <?= $tanggalCetak ?></td>
    </tr>
    <tr>
      <td style="font-weight: bold; font-size: 11pt;">Peminjam (Anggota)</td>
      <td style="font-weight: bold; font-size: 11pt;">Mengetahui,</td>
      <td style="font-weight: bold; font-size: 11pt;">Pemberi Barang / Pengurus</td>
    </tr>
    <tr>
      <td style="font-size: 9.5pt; color: #64748b;">Yang Menerima Barang</td>
      <td style="font-size: 9.5pt; color: #64748b;">Ketua Himpunan</td>
      <td style="font-size: 9.5pt; color: #64748b;">Yang Menyerahkan Barang</td>
    </tr>
    <tr>
      <td style="height: 60px;"></td>
      <td style="height: 60px;"></td>
      <td style="height: 60px;"></td>
    </tr>
    <tr>
      <td style="font-weight: bold; text-decoration: underline;">( ........................................ )</td>
      <td style="font-weight: bold; text-decoration: underline;">Muhammad Rizky Pratama</td>
      <td style="font-weight: bold; text-decoration: underline;"><?= htmlspecialchars($currentUser['nama_lengkap'] ?? 'Administrator') ?></td>
    </tr>
    <tr>
      <td style="font-size: 9pt; color: #64748b;">NIM / Nama Terang Peminjam</td>
      <td style="font-size: 9pt; color: #64748b;">NIM. 2201010045</td>
      <td style="font-size: 9pt; color: #64748b;">Pengurus Inti Divisi Logistik</td>
    </tr>
  </table>

</body>
</html>
<?php
    exit;
} catch (Exception $e) {
    echo "Terjadi kesalahan: " . $e->getMessage();
}
