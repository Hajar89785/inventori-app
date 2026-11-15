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

if (!$input) {
    jsonResponse(false, 'Data input tidak valid.', null, 400);
}

// ─── Validasi ────────────────────────────────────────────
$idBarang  = (int) ($input['id_barang'] ?? 0);
$peminjam  = trim($input['nama_peminjam'] ?? '');
$tglPinjam = trim($input['tanggal_pinjam'] ?? '');
$durasi    = (int) ($input['durasi'] ?? 0);

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
if ($durasi < 1 || $durasi > 30) {
    $errors[] = 'Durasi harus antara 1 sampai 30 hari.';
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

    if ($barang['stok_tersedia'] < 1) {
        $pdo->rollBack();
        jsonResponse(false, 'Maaf, barang sudah tidak tersedia.', null, 409);
    }

    // Hitung tanggal kembali
    $tglKembali = date('Y-m-d', strtotime($tglPinjam . " +{$durasi} days"));

    // Insert peminjaman
    $stmt = $pdo->prepare("
        INSERT INTO peminjaman (id_barang, nama_peminjam, tanggal_pinjam, tanggal_kembali, durasi, status_pinjam)
        VALUES (:id_barang, :peminjam, :tgl_pinjam, :tgl_kembali, :durasi, 'aktif')
    ");
    $stmt->execute([
        ':id_barang'  => $idBarang,
        ':peminjam'   => $peminjam,
        ':tgl_pinjam' => $tglPinjam,
        ':tgl_kembali'=> $tglKembali,
        ':durasi'     => $durasi,
    ]);

    // Kurangi stok tersedia
    $stmt = $pdo->prepare('
        UPDATE barang
        SET stok_tersedia = stok_tersedia - 1
        WHERE id = :id
    ');
    $stmt->execute([':id' => $idBarang]);

    $pdo->commit();

    jsonResponse(true, "Peminjaman \"{$barang['nama_barang']}\" berhasil diajukan!", null, 201);

} catch (PDOException $e) {
    $pdo->rollBack();
    jsonResponse(false, 'Gagal mengajukan peminjaman: ' . $e->getMessage(), null, 500);
}

