<?php
/**
 * POST /api/update_akun.php
 * Body JSON: { "id", "nama_lengkap", "username", "email"?, "role"?, "status"? }
 */

require_once __DIR__ . '/../koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method tidak diizinkan.', null, 405);

$input = json_decode(file_get_contents('php://input'), true);
if (!$input && !empty($_POST)) {
    $input = $_POST;
}
if (!$input) jsonResponse(false, 'Data input tidak valid.', null, 400);

$id       = (int) ($input['id'] ?? 0);
$nama     = trim($input['nama_lengkap'] ?? '');
$username = trim($input['username'] ?? '');
$email    = trim($input['email'] ?? '');
$role     = trim($input['role'] ?? '');
$status   = trim($input['status'] ?? '');

if ($id < 1) jsonResponse(false, 'ID akun tidak valid.', null, 400);

$errors = [];
if ($nama === '')     $errors[] = 'Nama lengkap wajib diisi.';
if ($username === '') $errors[] = 'Username wajib diisi.';
if (!empty($errors)) jsonResponse(false, implode(' ', $errors), null, 422);

try {
    $existing = $pdo->prepare('SELECT * FROM akun WHERE id = :id');
    $existing->execute([':id' => $id]);
    $akun = $existing->fetch();
    if (!$akun) jsonResponse(false, 'Akun tidak ditemukan.', null, 404);

    // Cek duplikat username (kecuali diri sendiri)
    $check = $pdo->prepare('SELECT id FROM akun WHERE username = :u AND id != :id');
    $check->execute([':u' => $username, ':id' => $id]);
    if ($check->fetch()) jsonResponse(false, "Username \"$username\" sudah digunakan.", null, 409);

    $stmt = $pdo->prepare('
        UPDATE akun SET nama_lengkap = :nama, username = :username, email = :email,
        role = :role, status = :status WHERE id = :id
    ');
    $stmt->execute([
        ':nama'     => $nama,
        ':username' => $username,
        ':email'    => $email,
        ':role'     => $role ?: $akun['role'],
        ':status'   => $status ?: $akun['status'],
        ':id'       => $id,
    ]);

    if (!empty($input['password'])) {
        $stmtPass = $pdo->prepare('UPDATE akun SET password = :p WHERE id = :id');
        $stmtPass->execute([
            ':p'  => password_hash($input['password'], PASSWORD_DEFAULT),
            ':id' => $id,
        ]);
    }

    $stmt = $pdo->prepare('SELECT id, nama_lengkap, username, email, role, status, created_at, updated_at FROM akun WHERE id = :id');
    $stmt->execute([':id' => $id]);

    jsonResponse(true, 'Akun berhasil diperbarui!', $stmt->fetch());

} catch (PDOException $e) {
    jsonResponse(false, 'Gagal memperbarui akun: ' . $e->getMessage(), null, 500);
}
