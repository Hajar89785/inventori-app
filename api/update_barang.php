<?php
/**
 * POST /api/update_barang.php
 * Memperbarui data barang yang sudah ada.
 *
 * Body JSON:
 *   { "id", "nama_barang", "kategori", "total_stok", "ikon"?, "deskripsi"? }
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
$id        = (int) ($input['id'] ?? 0);
$nama      = trim($input['nama_barang'] ?? '');
$kategori  = trim($input['kategori'] ?? '');
$totalStok = (int) ($input['total_stok'] ?? 0);
$ikon      = trim($input['ikon'] ?? '');
$deskripsi = trim($input['deskripsi'] ?? '');

$errors = [];

if ($id < 1) {
    $errors[] = 'ID barang tidak valid.';
}
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

try {
    // Ambil data barang saat ini
    $stmt = $pdo->prepare('SELECT * FROM barang WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        jsonResponse(false, 'Barang tidak ditemukan.', null, 404);
    }

    // Hitung jumlah dipinjam saat ini
    $dipinjam = $existing['total_stok'] - $existing['stok_tersedia'];

    // Tidak boleh set total_stok di bawah jumlah yang sedang dipinjam
    if ($totalStok < $dipinjam) {
        jsonResponse(false, "Stok tidak boleh kurang dari jumlah dipinjam ($dipinjam).", null, 422);
    }

    // Hitung stok tersedia baru
    $stokTersedia = $totalStok - $dipinjam;

    // Update ikon hanya jika dikirim
    if ($ikon === '') {
        $ikon = $existing['ikon'];
    }

    // ─── Update Database ─────────────────────────────────
    $stmt = $pdo->prepare('
        UPDATE barang
        SET nama_barang    = :nama,
            kategori       = :kategori,
            total_stok     = :total_stok,
            stok_tersedia  = :stok_tersedia,
            ikon           = :ikon,
            deskripsi      = :deskripsi
        WHERE id = :id
    ');

    $stmt->execute([
        ':nama'          => $nama,
        ':kategori'      => $kategori,
        ':total_stok'    => $totalStok,
        ':stok_tersedia' => $stokTersedia,
        ':ikon'          => $ikon,
        ':deskripsi'     => $deskripsi,
        ':id'            => $id,
    ]);

    // Ambil data terbaru
    $stmt = $pdo->prepare('SELECT * FROM barang WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $updated = $stmt->fetch();

    jsonResponse(true, 'Barang berhasil diperbarui!', $updated);

} catch (PDOException $e) {
    jsonResponse(false, 'Gagal memperbarui barang: ' . $e->getMessage(), null, 500);
}

