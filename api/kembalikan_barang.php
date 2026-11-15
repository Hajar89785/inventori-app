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
if (!$input && !empty($_POST)) {
    $input = $_POST;
}
if (!$input) jsonResponse(false, 'Data input tidak valid.', null, 400);

$id = (int) ($input['id'] ?? 0);
if ($id < 1) jsonResponse(false, 'ID peminjaman tidak valid.', null, 400);

try {
    $pdo->beginTransaction();

    // Ambil data peminjaman (lock row)
    $stmt = $pdo->prepare("SELECT p.*, b.nama_barang FROM peminjaman p JOIN barang b ON p.id_barang = b.id WHERE p.id = :id FOR UPDATE");
    $stmt->execute([':id' => $id]);
    $loan = $stmt->fetch();

    if (!$loan) {
        $pdo->rollBack();
        jsonResponse(false, 'Data peminjaman tidak ditemukan.', null, 404);
    }

    if ($loan['status_pinjam'] === 'dikembalikan' || $loan['status_pinjam'] === 'selesai') {
        $pdo->rollBack();
        jsonResponse(false, 'Barang ini sudah dikembalikan sebelumnya.', null, 409);
    }

    if ($loan['status_pinjam'] === 'menunggu' || $loan['status_pinjam'] === 'ditolak') {
        $pdo->rollBack();
        jsonResponse(false, "Peminjaman belum aktif/disetujui (status: {$loan['status_pinjam']}).", null, 400);
    }

    // Update status peminjaman menjadi 'selesai'
    $stmt = $pdo->prepare("UPDATE peminjaman SET status_pinjam = 'selesai' WHERE id = :id");
    $stmt->execute([':id' => $id]);

    // Tambah kembali stok tersedia sesuai jumlah yang dipinjam
    $jmlKembali = max(1, (int) ($loan['jumlah'] ?? 1));
    $stmt = $pdo->prepare('UPDATE barang SET stok_tersedia = stok_tersedia + :jml WHERE id = :id');
    $stmt->execute([':jml' => $jmlKembali, ':id' => $loan['id_barang']]);

    $pdo->commit();

    jsonResponse(true, "Barang \"{$loan['nama_barang']}\" ({$jmlKembali} unit) berhasil dikembalikan dan stok telah dipulihkan!");

} catch (PDOException $e) {
    $pdo->rollBack();
    jsonResponse(false, 'Gagal mengembalikan barang: ' . $e->getMessage(), null, 500);
}
