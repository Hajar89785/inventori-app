<?php
/**
 * GET /api/get_peminjaman.php
 * Mengambil daftar peminjaman, dengan filter opsional.
 *
 * Query params:
 *   ?status=aktif|dikembalikan|terlambat
 *   ?id_barang=...
 */

require_once __DIR__ . '/../koneksi.php';

try {
    $sql    = "
        SELECT p.*, b.nama_barang, b.kategori, b.ikon
        FROM peminjaman p
        JOIN barang b ON p.id_barang = b.id
    ";
    $where  = [];
    $params = [];

    if (!empty($_GET['status'])) {
        $where[] = 'p.status_pinjam = :status';
        $params[':status'] = $_GET['status'];
    }

    if (!empty($_GET['id_barang'])) {
        $where[] = 'p.id_barang = :id_barang';
        $params[':id_barang'] = (int) $_GET['id_barang'];
    }

    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY p.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    jsonResponse(true, '', $data);

} catch (PDOException $e) {
    jsonResponse(false, 'Gagal mengambil data peminjaman: ' . $e->getMessage(), null, 500);
}

