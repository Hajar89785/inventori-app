<?php
/**
 * GET /api/get_barang.php
 * Mengambil seluruh data barang, dengan pencarian opsional.
 *
 * Query params:
 *   ?q=keyword     — cari berdasarkan nama/kategori
 *   ?kategori=...  — filter berdasarkan kategori
 *   ?id=...        — ambil satu barang berdasarkan ID
 */

require_once __DIR__ . '/../koneksi.php';

try {
    // ── Ambil satu barang berdasarkan ID ──
    if (isset($_GET['id'])) {
        $id = (int) $_GET['id'];
        $stmt = $pdo->prepare('SELECT * FROM barang WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $barang = $stmt->fetch();

        if (!$barang) {
            jsonResponse(false, 'Barang tidak ditemukan.', null, 404);
        }

        jsonResponse(true, '', $barang);
    }

    // ── Ambil semua barang (dengan filter opsional) ──
    $sql    = 'SELECT * FROM barang';
    $where  = [];
    $params = [];

    // Pencarian teks
    if (!empty($_GET['q'])) {
        $keyword = '%' . $_GET['q'] . '%';
        $where[] = '(nama_barang LIKE :q1 OR kategori LIKE :q2)';
        $params[':q1'] = $keyword;
        $params[':q2'] = $keyword;
    }

    // Filter kategori
    if (!empty($_GET['kategori']) && $_GET['kategori'] !== 'Semua') {
        $where[] = 'kategori = :kategori';
        $params[':kategori'] = $_GET['kategori'];
    }

    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY id ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    jsonResponse(true, '', $data);

} catch (PDOException $e) {
    jsonResponse(false, 'Gagal mengambil data barang: ' . $e->getMessage(), null, 500);
}

