<?php
/**
 * POST /api/tambah_peminjaman.php
 * Mengajukan peminjaman barang baru.
 *
 * Body JSON:
 *   { "id_barang", "nama_peminjam", "tanggal_pinjam", "durasi" }
 */

require_once __DIR__ . '/../koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method tidak diizinkan.', null, 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input && !empty($_POST)) {
    $input = $_POST;
}

if (!$input) {
    jsonResponse(false, 'Data input tidak valid.', null, 400);
}

// ─── Validasi ────────────────────────────────────────────
$idBarang   = (int) ($input['id_barang'] ?? 0);
$peminjam   = trim($input['nama_peminjam'] ?? '');
$jumlah     = max(1, (int) ($input['jumlah'] ?? 1));
$tglPinjam  = trim($input['tanggal_pinjam'] ?? '');
$tglKembali = trim($input['tanggal_kembali'] ?? '');
$durasi     = (int) ($input['durasi'] ?? 0);
$keperluan  = trim($input['keperluan'] ?? '');

$errors = [];

if ($idBarang < 1) {
    $errors[] = 'Pilih barang yang ingin dipinjam.';
}
if ($peminjam === '' || mb_strlen($peminjam) < 3) {
    $errors[] = 'Nama peminjam wajib diisi (minimal 3 karakter).';
}
if ($tglPinjam === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglPinjam)) {
    $errors[] = 'Tanggal pinjam tidak valid.';
}

if ($tglKembali !== '') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglKembali)) {
        $errors[] = 'Tanggal kembali tidak valid.';
    } elseif ($tglKembali < $tglPinjam) {
        $errors[] = 'Tanggal kembali tidak boleh lebih awal dari tanggal pinjam.';
    } else {
        $diffDays = (int) round((strtotime($tglKembali) - strtotime($tglPinjam)) / 86400);
        $durasi = max(1, $diffDays);
    }
} elseif ($durasi > 0) {
    $tglKembali = date('Y-m-d', strtotime($tglPinjam . " +{$durasi} days"));
} else {
    $durasi = 3;
    $tglKembali = date('Y-m-d', strtotime($tglPinjam . " +3 days"));
}

if ($durasi > 30) {
    $errors[] = 'Durasi peminjaman maksimal 30 hari.';
}

if (!empty($errors)) {
    jsonResponse(false, implode(' ', $errors), null, 422);
}

try {
    // Mulai transaksi
    $pdo->beginTransaction();

    // Cek ketersediaan barang (dengan row lock)
    $stmt = $pdo->prepare('SELECT * FROM barang WHERE id = :id FOR UPDATE');
    $stmt->execute([':id' => $idBarang]);
    $barang = $stmt->fetch();

    if (!$barang) {
        $pdo->rollBack();
        jsonResponse(false, 'Barang tidak ditemukan.', null, 404);
    }

    if ($barang['stok_tersedia'] < $jumlah) {
        $pdo->rollBack();
        jsonResponse(false, "Stok tidak mencukupi. Tersedia hanya {$barang['stok_tersedia']} unit.", null, 409);
    }

    // Insert peminjaman
    $stmt = $pdo->prepare("
        INSERT INTO peminjaman (id_barang, jumlah, nama_peminjam, tanggal_pinjam, tanggal_kembali, durasi, keperluan, status_pinjam)
        VALUES (:id_barang, :jumlah, :peminjam, :tgl_pinjam, :tgl_kembali, :durasi, :keperluan, 'aktif')
    ");
    $stmt->execute([
        ':id_barang'   => $idBarang,
        ':jumlah'      => $jumlah,
        ':peminjam'    => $peminjam,
        ':tgl_pinjam'  => $tglPinjam,
        ':tgl_kembali' => $tglKembali,
        ':durasi'      => $durasi,
        ':keperluan'   => $keperluan,
    ]);

    // Kurangi stok tersedia
    $stmt = $pdo->prepare('
        UPDATE barang
        SET stok_tersedia = stok_tersedia - :jumlah
        WHERE id = :id
    ');
    $stmt->execute([':jumlah' => $jumlah, ':id' => $idBarang]);

    $pdo->commit();

    jsonResponse(true, "Pengajuan peminjaman \"{$barang['nama_barang']}\" ({$jumlah} unit) berhasil diajukan!", null, 201);

} catch (PDOException $e) {
    $pdo->rollBack();
    jsonResponse(false, 'Gagal mengajukan peminjaman: ' . $e->getMessage(), null, 500);
}

