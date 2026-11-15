<?php
/**
 * POST /api/kembalikan_barang.php
 * Mengembalikan barang yang dipinjam.
 *
 * Body JSON: { "id": <id_peminjaman> }
 */

require_once __DIR__ . '/../koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method tidak diizinkan.', null, 405);

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) jsonResponse(false, 'Data input tidak valid.', null, 400);

$id = (int) ($input['id'] ?? 0);
if ($id < 1) jsonResponse(false, 'ID peminjaman tidak valid.', null, 400);

try {
    $pdo->beginTransaction();

    // Ambil data peminjaman (lock row)
    $stmt = $pdo->prepare("SELECT * FROM peminjaman WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $id]);
    $loan = $stmt->fetch();

    if (!$loan) {
        $pdo->rollBack();
        jsonResponse(false, 'Data peminjaman tidak ditemukan.', null, 404);
    }

    if ($loan['status_pinjam'] === 'dikembalikan') {
        $pdo->rollBack();
        jsonResponse(false, 'Barang sudah dikembalikan sebelumnya.', null, 409);
    }

    // Update status peminjaman
    $stmt = $pdo->prepare("UPDATE peminjaman SET status_pinjam = 'dikembalikan' WHERE id = :id");
    $stmt->execute([':id' => $id]);

    // Tambah kembali stok tersedia
    $stmt = $pdo->prepare('UPDATE barang SET stok_tersedia = stok_tersedia + 1 WHERE id = :id');
    $stmt->execute([':id' => $loan['id_barang']]);

    $pdo->commit();

    jsonResponse(true, 'Barang berhasil dikembalikan!');

} catch (PDOException $e) {
    $pdo->rollBack();
    jsonResponse(false, 'Gagal mengembalikan barang: ' . $e->getMessage(), null, 500);
}
