<?php
/**
 * POST /api/tolak_peminjaman.php
 * Menolak pengajuan peminjaman barang oleh Admin.
 * Mengubah status menjadi 'ditolak' tanpa mengubah stok barang.
 */

require_once __DIR__ . '/../koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method tidak diizinkan.', null, 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input && !empty($_POST)) {
    $input = $_POST;
}

$id = (int) ($input['id'] ?? 0);
if ($id < 1) {
    jsonResponse(false, 'ID peminjaman tidak valid.', null, 400);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT p.*, b.nama_barang FROM peminjaman p JOIN barang b ON p.id_barang = b.id WHERE p.id = :id FOR UPDATE');
    $stmt->execute([':id' => $id]);
    $loan = $stmt->fetch();

    if (!$loan) {
        $pdo->rollBack();
        jsonResponse(false, 'Data pengajuan peminjaman tidak ditemukan.', null, 404);
    }

    if ($loan['status_pinjam'] === 'ditolak') {
        $pdo->rollBack();
        jsonResponse(false, 'Pengajuan ini sudah berstatus ditolak.', null, 409);
    }

    if ($loan['status_pinjam'] === 'selesai' || $loan['status_pinjam'] === 'dikembalikan') {
        $pdo->rollBack();
        jsonResponse(false, 'Peminjaman yang sudah selesai tidak dapat ditolak.', null, 409);
    }

    // Jika peminjaman sebelumnya sudah disetujui / aktif, pulihkan stok barang
    if ($loan['status_pinjam'] === 'disetujui' || $loan['status_pinjam'] === 'aktif') {
        $jumlah = max(1, (int) ($loan['jumlah'] ?? 1));
        $stmt = $pdo->prepare('UPDATE barang SET stok_tersedia = stok_tersedia + :jml WHERE id = :id');
        $stmt->execute([':jml' => $jumlah, ':id' => $loan['id_barang']]);
    }

    // Ubah status menjadi 'ditolak'
    $stmt = $pdo->prepare("UPDATE peminjaman SET status_pinjam = 'ditolak' WHERE id = :id");
    $stmt->execute([':id' => $id]);

    $pdo->commit();

    jsonResponse(true, "Pengajuan peminjaman \"{$loan['nama_barang']}\" oleh {$loan['nama_peminjam']} telah ditolak.");

} catch (PDOException $e) {
    $pdo->rollBack();
    jsonResponse(false, 'Gagal menolak pengajuan: ' . $e->getMessage(), null, 500);
}
