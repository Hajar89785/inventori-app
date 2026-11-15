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
    // Pastikan kolom password & plain_password ada di tabel akun
    try {
        $pdo->exec("ALTER TABLE akun ADD COLUMN password VARCHAR(255) NULL AFTER email");
    } catch (Exception $e) {}

    try {
        $pdo->exec("ALTER TABLE akun ADD COLUMN plain_password VARCHAR(255) NULL AFTER password");
    } catch (Exception $e) {}

    // Pastikan akun di database memiliki password terisi
    try {
        $adminHash = password_hash('admin123', PASSWORD_DEFAULT);
        $userHash  = password_hash('user123', PASSWORD_DEFAULT);

        $pdo->exec("UPDATE akun SET password = '{$adminHash}', plain_password = 'admin123' WHERE role = 'admin' AND (password IS NULL OR password = '' OR plain_password IS NULL OR plain_password = '')");
        $pdo->exec("UPDATE akun SET password = '{$userHash}', plain_password = 'user123' WHERE role = 'user' AND (password IS NULL OR password = '' OR plain_password IS NULL OR plain_password = '')");
    } catch (Exception $e) {}

    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare('SELECT * FROM akun WHERE id = :id');
        $stmt->execute([':id' => (int) $_GET['id']]);
        $akun = $stmt->fetch();
        if (!$akun) jsonResponse(false, 'Akun tidak ditemukan.', null, 404);
        unset($akun['password']); // safety
        jsonResponse(true, '', $akun);
    }

    $sql    = 'SELECT * FROM akun';
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
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $plain = $r['plain_password'] ?? '';
        $pass  = $r['password'] ?? '';

        if (!empty($plain)) {
            $r['display_password'] = $plain;
        } elseif (empty($pass)) {
            $r['display_password'] = ($r['role'] === 'admin') ? 'admin123' : 'user123';
        } elseif (password_verify('admin123', $pass)) {
            $r['display_password'] = 'admin123';
        } elseif (password_verify('user123', $pass)) {
            $r['display_password'] = 'user123';
        } elseif (strpos($pass, '$2y$') !== 0 && strpos($pass, '$2a$') !== 0) {
            $r['display_password'] = $pass;
        } else {
            $r['display_password'] = ($r['role'] === 'admin') ? 'admin123' : 'user123';
        }
        unset($r['password']);
    }
    unset($r);

    jsonResponse(true, '', $rows);

} catch (PDOException $e) {
    jsonResponse(false, 'Gagal mengambil data akun: ' . $e->getMessage(), null, 500);
}
