<?php
/**
 * POST /api/register.php
 * Registrasi Akun Anggota / Peminjam Baru (Role: User)
 */

require_once __DIR__ . '/../koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method tidak diizinkan.', null, 405);
}

// Baca input baik berupa JSON maupun POST form-urlencoded
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!$input && !empty($_POST)) {
    $input = $_POST;
}

$nama        = trim($input['nama_lengkap'] ?? $input['reg_nama'] ?? '');
$username    = trim($input['username'] ?? $input['reg_username'] ?? '');
$email       = trim($input['email'] ?? $input['reg_email'] ?? '');
$password    = trim($input['password'] ?? $input['reg_password'] ?? '');
$konfirmasi  = trim($input['konfirmasi_password'] ?? $input['reg_password_confirm'] ?? $input['password_confirm'] ?? '');

// Jika username kosong namun email diisi, turunkan username dari prefix email
if ($username === '' && $email !== '') {
    $parts = explode('@', $email);
    $username = preg_replace('/[^a-zA-Z0-9_]/', '', $parts[0]);
}

$errors = [];
if ($nama === '') {
    $errors[] = 'Nama lengkap wajib diisi.';
}
if (empty($email)) {
    $errors[] = 'Email anggota wajib diisi.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Format email tidak valid. Pastikan penulisan email benar (contoh: nama@email.com).';
}
if ($username === '') {
    $errors[] = 'Username wajib diisi.';
} elseif (strlen($username) < 3) {
    $errors[] = 'Username minimal 3 karakter.';
}
if ($password === '') {
    $errors[] = 'Password wajib diisi.';
} elseif (strlen($password) < 4) {
    $errors[] = 'Password minimal 4 karakter.';
}
if ($konfirmasi !== '' && $password !== $konfirmasi) {
    $errors[] = 'Konfirmasi password tidak cocok!';
}

if (!empty($errors)) {
    jsonResponse(false, implode(' ', $errors), null, 422);
}

try {
    // 1. Cek apakah username sudah dipakai
    $checkUser = $pdo->prepare('SELECT id FROM akun WHERE username = :u LIMIT 1');
    $checkUser->execute([':u' => $username]);
    if ($checkUser->fetch()) {
        jsonResponse(false, "Username \"$username\" sudah digunakan. Silakan pilih username lain.", null, 409);
    }

    // 2. Cek apakah email sudah dipakai (jika ada)
    if ($email !== '') {
        $checkEmail = $pdo->prepare('SELECT id FROM akun WHERE email = :e LIMIT 1');
        $checkEmail->execute([':e' => $email]);
        if ($checkEmail->fetch()) {
            jsonResponse(false, "Email \"$email\" sudah terdaftar. Silakan gunakan menu Masuk.", null, 409);
        }
    }

    // 3. Hash password
    $hashedPass = password_hash($password, PASSWORD_DEFAULT);

    // Coba tambahkan kolom plain_password jika belum ada
    try {
        $pdo->exec("ALTER TABLE akun ADD COLUMN plain_password VARCHAR(255) NULL AFTER password");
    } catch (Exception $ignored) {}

    // 4. Simpan ke tabel akun (Role default: user, status: aktif)
    try {
        $stmt = $pdo->prepare('
            INSERT INTO akun (nama_lengkap, username, email, password, plain_password, role, status)
            VALUES (:nama, :username, :email, :password, :plain, \'user\', \'aktif\')
        ');
        $stmt->execute([
            ':nama'     => $nama,
            ':username' => $username,
            ':email'    => $email,
            ':password' => $hashedPass,
            ':plain'    => $password,
        ]);
    } catch (PDOException $e) {
        $stmt = $pdo->prepare('
            INSERT INTO akun (nama_lengkap, username, email, password, role, status)
            VALUES (:nama, :username, :email, :password, \'user\', \'aktif\')
        ');
        $stmt->execute([
            ':nama'     => $nama,
            ':username' => $username,
            ':email'    => $email,
            ':password' => $hashedPass,
        ]);
    }

    $newId = (int) $pdo->lastInsertId();

    // 5. Langsung buat sesi login aktif tanpa halaman perantara
    $_SESSION['invento_logged_in'] = true;
    $_SESSION['invento_user'] = [
        'id'           => $newId,
        'nama_lengkap' => $nama,
        'username'     => $username,
        'email'        => $email,
        'role'         => 'user',
    ];

    jsonResponse(true, 'Pendaftaran akun berhasil! Anda langsung dialihkan...', [
        'id'       => $newId,
        'redirect' => 'user_katalog.php',
    ], 201);

} catch (PDOException $e) {
    jsonResponse(false, 'Gagal memproses pendaftaran: ' . $e->getMessage(), null, 500);
}
