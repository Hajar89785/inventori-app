<?php
/**
 * POST /api/tambah_barang.php
 * Menambahkan barang baru ke database.
 *
 * Body JSON:
 *   { "nama_barang", "kategori", "total_stok", "ikon"?, "deskripsi"? }
 */

require_once __DIR__ . '/../koneksi.php';

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method tidak diizinkan.', null, 405);
}

// Parse JSON body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    jsonResponse(false, 'Data input tidak valid.', null, 400);
}

// ─── Validasi ────────────────────────────────────────────
$nama      = trim($input['nama_barang'] ?? '');
$kategori  = trim($input['kategori'] ?? '');
$totalStok = (int) ($input['total_stok'] ?? 0);
$ikon      = trim($input['ikon'] ?? 'bi-box');
$deskripsi = trim($input['deskripsi'] ?? '');

$errors = [];

if ($nama === '') {
    $errors[] = 'Nama barang wajib diisi.';
}
if ($kategori === '') {
    $errors[] = 'Kategori wajib dipilih.';
}
if ($totalStok < 1) {
    $errors[] = 'Jumlah stok minimal 1.';
}

if (!empty($errors)) {
    jsonResponse(false, implode(' ', $errors), null, 422);
}

// ─── Insert ke Database ──────────────────────────────────
try {
    $stmt = $pdo->prepare('
        INSERT INTO barang (nama_barang, kategori, total_stok, stok_tersedia, ikon, deskripsi)
        VALUES (:nama, :kategori, :total_stok, :stok_tersedia, :ikon, :deskripsi)
    ');

    $stmt->execute([
        ':nama'           => $nama,
        ':kategori'       => $kategori,
        ':total_stok'     => $totalStok,
        ':stok_tersedia'  => $totalStok,   // Barang baru → semua tersedia
        ':ikon'           => $ikon,
        ':deskripsi'      => $deskripsi,
    ]);

    $newId = (int) $pdo->lastInsertId();

    // Ambil data barang yang baru ditambahkan
    $stmt = $pdo->prepare('SELECT * FROM barang WHERE id = :id');
    $stmt->execute([':id' => $newId]);
    $barang = $stmt->fetch();

    jsonResponse(true, 'Barang baru berhasil ditambahkan!', $barang, 201);

} catch (PDOException $e) {
    jsonResponse(false, 'Gagal menambahkan barang: ' . $e->getMessage(), null, 500);
}

