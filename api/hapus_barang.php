<?php
/**
 * POST /api/hapus_barang.php
 * Menghapus barang dari database.
 *
 * Body JSON:
 *   { "id": 1 }
 *
 * Catatan: Barang yang masih memiliki peminjaman aktif tidak dapat dihapus.
 */

require_once __DIR__ . '/../koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method tidak diizinkan.', null, 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    jsonResponse(false, 'Data input tidak valid.', null, 400);
}

$id = (int) ($input['id'] ?? 0);

if ($id < 1) {
    jsonResponse(false, 'ID barang tidak valid.', null, 400);
}

try {
    // Cek apakah barang ada
    $stmt = $pdo->prepare('SELECT * FROM barang WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $barang = $stmt->fetch();

    if (!$barang) {
        jsonResponse(false, 'Barang tidak ditemukan.', null, 404);
    }

    // Cek apakah ada peminjaman aktif
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM peminjaman
        WHERE id_barang = :id AND status_pinjam = 'aktif'
    ");
    $stmt->execute([':id' => $id]);
    $result = $stmt->fetch();

    if ($result['total'] > 0) {
        jsonResponse(
            false,
            "Tidak dapat menghapus \"{$barang['nama_barang']}\" — masih ada {$result['total']} peminjaman aktif.",
            null,
            409
        );
    }

    // Hapus barang (peminjaman non-aktif akan ter-cascade)
    $stmt = $pdo->prepare('DELETE FROM barang WHERE id = :id');
    $stmt->execute([':id' => $id]);

    jsonResponse(true, 'Barang berhasil dihapus.');

} catch (PDOException $e) {
    jsonResponse(false, 'Gagal menghapus barang: ' . $e->getMessage(), null, 500);
}

