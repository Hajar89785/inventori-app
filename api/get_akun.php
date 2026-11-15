<?php
/**
 * GET /api/get_akun.php
 * Mengambil daftar akun, dengan pencarian & filter opsional.
 *
 * Query params:
 *   ?q=keyword        — cari nama/username/email
 *   ?role=admin|user   — filter berdasarkan role
 *   ?id=...           — ambil satu akun
 */

require_once __DIR__ . '/../koneksi.php';

try {
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare('SELECT * FROM akun WHERE id = :id');
        $stmt->execute([':id' => (int) $_GET['id']]);
        $akun = $stmt->fetch();
        if (!$akun) jsonResponse(false, 'Akun tidak ditemukan.', null, 404);
        unset($akun['password']); // safety
        jsonResponse(true, '', $akun);
    }

    $sql    = 'SELECT id, nama_lengkap, username, email, role, status, created_at, updated_at FROM akun';
    $where  = [];
    $params = [];

    if (!empty($_GET['q'])) {
        $kw = '%' . $_GET['q'] . '%';
        $where[] = '(nama_lengkap LIKE :q1 OR username LIKE :q2 OR email LIKE :q3)';
        $params[':q1'] = $kw;
        $params[':q2'] = $kw;
        $params[':q3'] = $kw;
    }

    if (!empty($_GET['role'])) {
        $where[] = 'role = :role';
        $params[':role'] = $_GET['role'];
    }

    if (!empty($where)) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY id ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, '', $stmt->fetchAll());

} catch (PDOException $e) {
    jsonResponse(false, 'Gagal mengambil data akun: ' . $e->getMessage(), null, 500);
}
