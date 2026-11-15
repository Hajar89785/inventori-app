<?php
/**
 * POST /api/setujui_peminjaman.php
 * Menyetujui pengajuan peminjaman barang oleh Admin.
 * Mengubah status menjadi 'disetujui' dan memotong stok barang.
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

    // Ambil data peminjaman dengan row lock
    $stmt = $pdo->prepare('SELECT p.*, b.nama_barang, b.stok_tersedia FROM peminjaman p JOIN barang b ON p.id_barang = b.id WHERE p.id = :id FOR UPDATE');
    $stmt->execute([':id' => $id]);
    $loan = $stmt->fetch();

    if (!$loan) {
        $pdo->rollBack();
        jsonResponse(false, 'Data pengajuan peminjaman tidak ditemukan.', null, 404);
    }

    if ($loan['status_pinjam'] !== 'menunggu' && $loan['status_pinjam'] !== 'ditolak') {
        $pdo->rollBack();
        jsonResponse(false, "Peminjaman ini sudah berstatus \"{$loan['status_pinjam']}\".", null, 409);
    }

    $jumlah = max(1, (int) ($loan['jumlah'] ?? 1));

    // Cek apakah stok masih mencukupi
    if ($loan['stok_tersedia'] < $jumlah) {
        $pdo->rollBack();
        jsonResponse(false, "Stok \"{$loan['nama_barang']}\" tidak mencukupi (sisa {$loan['stok_tersedia']} unit, diajukan {$jumlah} unit).", null, 409);
    }

    // 1. Ubah status peminjaman menjadi 'disetujui'
    $stmt = $pdo->prepare("UPDATE peminjaman SET status_pinjam = 'disetujui' WHERE id = :id");
    $stmt->execute([':id' => $id]);

    // 2. Kurangi stok barang
    $stmt = $pdo->prepare('UPDATE barang SET stok_tersedia = stok_tersedia - :jml WHERE id = :id');
    $stmt->execute([':jml' => $jumlah, ':id' => $loan['id_barang']]);

    $pdo->commit();

    jsonResponse(true, "Pengajuan peminjaman \"{$loan['nama_barang']}\" ({$jumlah} unit) oleh {$loan['nama_peminjam']} telah disetujui! Stok barang berhasil dikurangi.");

} catch (PDOException $e) {
    $pdo->rollBack();
    jsonResponse(false, 'Gagal memproses persetujuan: ' . $e->getMessage(), null, 500);
}
