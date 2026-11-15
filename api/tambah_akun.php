<?php
/**
 * POST /api/tambah_akun.php
 * Body JSON: { "nama_lengkap", "username", "email"?, "role"? }
 */

require_once __DIR__ . '/../koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method tidak diizinkan.', null, 405);

$input = json_decode(file_get_contents('php://input'), true);
if (!$input && !empty($_POST)) {
    $input = $_POST;
}
if (!$input) jsonResponse(false, 'Data input tidak valid.', null, 400);

$nama     = trim($input['nama_lengkap'] ?? '');
$username = trim($input['username'] ?? '');
$email    = trim($input['email'] ?? '');
$role     = trim($input['role'] ?? 'user');

$errors = [];
if ($nama === '')     $errors[] = 'Nama lengkap wajib diisi.';
if ($username === '') $errors[] = 'Username wajib diisi.';
if (strlen($username) < 3) $errors[] = 'Username minimal 3 karakter.';
if (!in_array($role, ['admin', 'user'])) $errors[] = 'Role tidak valid.';
if (!empty($errors)) jsonResponse(false, implode(' ', $errors), null, 422);

try {
    // Cek duplikat username
    $check = $pdo->prepare('SELECT id FROM akun WHERE username = :u');
    $check->execute([':u' => $username]);
    if ($check->fetch()) jsonResponse(false, "Username \"$username\" sudah digunakan.", null, 409);

    $rawPass = trim($input['password'] ?? '');
    if ($rawPass === '') {
        $rawPass = ($role === 'admin') ? 'admin123' : 'user123';
    }
    $hashedPass = password_hash($rawPass, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('
        INSERT INTO akun (nama_lengkap, username, email, password, role, status)
        VALUES (:nama, :username, :email, :password, :role, \'aktif\')
    ');
    $stmt->execute([
        ':nama'     => $nama,
        ':username' => $username,
        ':email'    => $email,
        ':password' => $hashedPass,
        ':role'     => $role,
    ]);

    $newId = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT id, nama_lengkap, username, email, role, status, created_at FROM akun WHERE id = :id');
    $stmt->execute([':id' => $newId]);

    jsonResponse(true, 'Akun berhasil ditambahkan!', $stmt->fetch(), 201);

} catch (PDOException $e) {
    jsonResponse(false, 'Gagal menambahkan akun: ' . $e->getMessage(), null, 500);
}
