<?php
/**
 * POST /api/login.php
 * Autentikasi Login Pengurus (Admin) dan Anggota (User)
 */

require_once __DIR__ . '/../koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method tidak diizinkan.', null, 405);
}

// Support both JSON body and standard form urlencoded/multipart
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!$input && !empty($_POST)) {
    $input = $_POST;
}

$username = trim($input['username'] ?? '');
$password = trim($input['password'] ?? '');

if ($username === '' || $password === '') {
    jsonResponse(false, 'Username dan password wajib diisi.', null, 422);
}

try {
    $stmt = $pdo->prepare('
        SELECT id, nama_lengkap, username, email, password, role, status
        FROM akun
        WHERE username = :u1 OR email = :u2
        LIMIT 1
    ');
    $stmt->execute([
        ':u1' => $username,
        ':u2' => $username,
    ]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(false, 'Username atau email tidak terdaftar.', null, 401);
    }

    if ($user['status'] !== 'aktif') {
        jsonResponse(false, 'Akun Anda berstatus nonaktif. Silakan hubungi pengurus himpunan.', null, 403);
    }

    $passwordValid = false;
    if (password_verify($password, $user['password'])) {
        $passwordValid = true;
    } elseif ($password === $user['password']) {
        $passwordValid = true;
    }

    if (!$passwordValid) {
        jsonResponse(false, 'Password yang Anda masukkan salah.', null, 401);
    }

    $_SESSION['invento_logged_in'] = true;
    $_SESSION['invento_user'] = [
        'id'           => (int) $user['id'],
        'nama_lengkap' => $user['nama_lengkap'],
        'username'     => $user['username'],
        'email'        => $user['email'],
        'role'         => $user['role'],
    ];

    $redirectUrl = ($user['role'] === 'admin') ? 'admin_dashboard.php' : 'user_katalog.php';

    jsonResponse(true, "Selamat datang, {$user['nama_lengkap']}!", [
        'user' => [
            'id'           => (int) $user['id'],
            'nama_lengkap' => $user['nama_lengkap'],
            'username'     => $user['username'],
            'email'        => $user['email'],
            'role'         => $user['role'],
        ],
        'redirect' => $redirectUrl,
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Terjadi kesalahan sistem: ' . $e->getMessage(), null, 500);
}
