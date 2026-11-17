<?php
/**
 * ========================================================
 * INVENTO — Halaman Masuk (Login) & Daftar Akun (Register)
 * ========================================================
 * 
 * - Desain Split-Screen Elegan & Minimalis
 * - Pilihan Akses Clean: Tab Switcher "Masuk" dan "Daftar Akun"
 * - Form Langsung To-The-Point tanpa teks basa-basi yang berlebihan
 * - Eksekusi Registrasi & Login langsung ke database tanpa transisi rumit
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/app_settings.php';
$settings = getAppSettings($pdo);

$error       = '';
$success     = '';
$activeTab   = 'login';

// Input memory untuk form
$loginInput       = '';
$regNamaInput     = '';
$regUserInput     = '';
$regEmailInput    = '';

// Jika sudah memiliki sesi login aktif, langsung arahkan ke portal yang sesuai
if (isset($_SESSION['invento_logged_in']) && $_SESSION['invento_logged_in'] === true) {
    if (isset($_SESSION['invento_user']['role']) && $_SESSION['invento_user']['role'] === 'admin') {
        header('Location: admin_dashboard.php');
        exit;
    } else {
        header('Location: user_katalog.php');
        exit;
    }
}

// Proses submit form (Login atau Register)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['auth_action'] ?? 'login');

    if ($action === 'register') {
        // ─── PROSES PENDAFTARAN AKUN BARU ──────────────────
        $activeTab       = 'register';
        $nama            = trim($_POST['reg_nama'] ?? $_POST['nama_lengkap'] ?? '');
        $username        = trim($_POST['reg_username'] ?? $_POST['username'] ?? '');
        $email           = trim($_POST['email'] ?? $_POST['reg_email'] ?? '');
        $password        = trim($_POST['reg_password'] ?? $_POST['password'] ?? '');
        $konfirmasiPass  = trim($_POST['reg_password_confirm'] ?? $_POST['konfirmasi_password'] ?? '');

        $regNamaInput    = $nama;
        $regUserInput    = $username;
        $regEmailInput   = $email;

        // Auto-derive username jika kosong tapi email diisi
        if ($username === '' && $email !== '') {
            $parts = explode('@', $email);
            $username = preg_replace('/[^a-zA-Z0-9_]/', '', $parts[0]);
        }

        // Validasi Ketat Backend
        if ($nama === '') {
            $error = 'Nama lengkap wajib diisi.';
        } elseif (empty($email)) {
            $error = 'Email anggota wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid. Pastikan penulisan email benar (contoh: nama@email.com).';
        } elseif ($username === '') {
            $error = 'Username wajib diisi.';
        } elseif (strlen($username) < 3) {
            $error = 'Username minimal 3 karakter.';
        } elseif ($password === '') {
            $error = 'Password baru wajib diisi.';
        } elseif (strlen($password) < 4) {
            $error = 'Password minimal 4 karakter.';
        } elseif ($password !== $konfirmasiPass) {
            $error = 'Konfirmasi password tidak cocok! Pastikan kedua kolom password sama.';
        } else {
            try {
                // Cek duplikasi username
                $checkUser = $pdo->prepare('SELECT id FROM akun WHERE username = :u LIMIT 1');
                $checkUser->execute([':u' => $username]);
                if ($checkUser->fetch()) {
                    $error = "Username \"$username\" sudah digunakan. Silakan pilih username lain.";
                } else {
                    // Cek duplikasi email jika diisi
                    $emailExists = false;
                    if ($email !== '') {
                        $checkEmail = $pdo->prepare('SELECT id FROM akun WHERE email = :e LIMIT 1');
                        $checkEmail->execute([':e' => $email]);
                        if ($checkEmail->fetch()) {
                            $emailExists = true;
                        }
                    }

                    if ($emailExists) {
                        $error = "Email \"$email\" sudah terdaftar. Silakan pilih tab Masuk.";
                    } else {
                        // Hash password
                        $hashedPass = password_hash($password, PASSWORD_DEFAULT);

                        // Coba simpan ke tabel akun
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

                        // Langsung buat sesi aktif dan masuk tanpa halaman jeda
                        $_SESSION['invento_logged_in'] = true;
                        $_SESSION['invento_user'] = [
                            'id'           => $newId,
                            'nama_lengkap' => $nama,
                            'username'     => $username,
                            'email'        => $email,
                            'role'         => 'user',
                        ];

                        header('Location: user_katalog.php');
                        exit;
                    }
                }
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan sistem saat mendaftar: ' . $e->getMessage();
            }
        }

    } else {
        // ─── PROSES LOGIN MASUK ───────────────────────────
        $activeTab   = 'login';
        $userKey     = trim($_POST['email'] ?? '');
        $password    = trim($_POST['password'] ?? '');
        $loginInput  = $userKey;

        if ($userKey === '' || $password === '') {
            $error = 'Email/Username dan Password wajib diisi.';
        } else {
            try {
                $stmt = $pdo->prepare('
                    SELECT id, nama_lengkap, username, email, password, role, status
                    FROM akun
                    WHERE email = :v1 OR username = :v2
                    LIMIT 1
                ');
                $stmt->execute([':v1' => $userKey, ':v2' => $userKey]);
                $user = $stmt->fetch();

                if (!$user) {
                    $error = 'Email atau Username tidak terdaftar dalam sistem.';
                } else {
                    $passwordValid = false;
                    if (!empty($user['password']) && password_verify($password, $user['password'])) {
                        $passwordValid = true;
                    } elseif ($password === $user['password']) {
                        $passwordValid = true;
                    }

                    if (!$passwordValid) {
                        $error = 'Password yang Anda masukkan salah. Silakan coba lagi.';
                    } elseif (isset($user['status']) && $user['status'] !== 'aktif') {
                        $error = 'Akun Anda sedang dinonaktifkan. Silakan hubungi pengurus himpunan.';
                    } else {
                        // Login Berhasil -> Set Sesi
                        $_SESSION['invento_logged_in'] = true;
                        $_SESSION['invento_user'] = [
                            'id'           => (int) $user['id'],
                            'nama_lengkap' => $user['nama_lengkap'],
                            'username'     => $user['username'],
                            'email'        => $user['email'],
                            'role'         => $user['role'],
                        ];

                        // Arahkan otomatis berdasarkan role
                        if ($user['role'] === 'admin') {
                            header('Location: admin_dashboard.php');
                            exit;
                        } else {
                            header('Location: user_katalog.php');
                            exit;
                        }
                    }
                }
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan koneksi database: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($settings['app_name']) ?> &mdash; Akses Masuk &amp; Pendaftaran</title>
  <!-- Google Fonts: Nunito -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="style.css?v=20261117e">
  <style>
    /* ═══ Split-Screen Layout ═══ */
    body.split-login-body {
      margin: 0;
      padding: 0;
      font-family: 'Nunito', sans-serif;
      background: #f8fafc;
      color: var(--text-main);
      overflow-x: hidden;
    }

    .split-wrapper {
      display: flex;
      min-height: 100vh;
      width: 100%;
    }

    /* Sisi Kiri (Form Area) */
    .split-form-side {
      flex: 1;
      max-width: 540px;
      padding: 40px 48px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      background: #ffffff;
      box-sizing: border-box;
      z-index: 2;
    }

    /* Brand Header Minimalis */
    .split-brand-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 24px;
    }

    .split-brand-icon {
      width: 42px;
      height: 42px;
      border-radius: 12px;
      background: linear-gradient(135deg, #435ebe 0%, #25396f 100%);
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.35rem;
      box-shadow: 0 4px 12px rgba(67, 94, 190, 0.25);
      flex-shrink: 0;
    }

    .split-brand-text h1 {
      margin: 0;
      font-size: 1.35rem;
      font-weight: 800;
      letter-spacing: 0.5px;
      color: #1e293b;
      line-height: 1.2;
    }

    .split-brand-text small {
      font-size: 0.78rem;
      color: #64748b;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.8px;
    }

    /* Tab Switcher: Masuk vs Daftar Akun */
    .auth-tab-switcher {
      display: flex;
      background: #f1f5f9;
      padding: 4px;
      border-radius: 12px;
      margin-bottom: 20px;
      border: 1px solid #e2e8f0;
      position: relative;
    }

    .auth-tab-btn {
      flex: 1;
      border: none;
      background: transparent;
      padding: 9px 16px;
      border-radius: 8px;
      font-size: 0.92rem;
      font-weight: 700;
      color: #64748b;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      font-family: inherit;
    }

    .auth-tab-btn.active {
      background: #ffffff;
      color: #435ebe;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    .auth-tab-btn:hover:not(.active) {
      color: #1e293b;
    }

    /* Keterangan Form Ringkas Tanpa Basa-Basi */
    .auth-header-desc {
      margin-bottom: 18px;
    }

    .auth-title {
      font-size: 1.3rem;
      font-weight: 800;
      color: #1e293b;
      margin: 0 0 4px;
    }

    .auth-subtitle {
      font-size: 0.88rem;
      color: #64748b;
      margin: 0;
      line-height: 1.4;
    }

    /* Alert Pesan Error & Sukses */
    .alert-error {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #b91c1c;
      padding: 10px 14px;
      border-radius: 10px;
      font-size: 0.86rem;
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 18px;
      animation: shake 0.35s ease-in-out;
    }

    .alert-success {
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      color: #15803d;
      padding: 10px 14px;
      border-radius: 10px;
      font-size: 0.86rem;
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 18px;
    }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      20%, 60% { transform: translateX(-5px); }
      40%, 80% { transform: translateX(5px); }
    }

    /* Form Styles */
    .split-form .form-group {
      margin-bottom: 16px;
    }

    .split-form label {
      display: block;
      font-size: 0.84rem;
      font-weight: 700;
      color: #334155;
      margin-bottom: 6px;
    }

    .split-form .input-icon-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }

    .split-form .input-icon {
      position: absolute;
      left: 14px;
      color: #94a3b8;
      font-size: 1.05rem;
      pointer-events: none;
    }

    .split-form .form-control {
      width: 100%;
      height: 44px;
      padding: 8px 40px 8px 38px;
      font-size: 0.9rem;
      font-family: inherit;
      border: 1.5px solid #cbd5e1;
      border-radius: 9px;
      background: #f8fafc;
      color: #1e293b;
      transition: all 0.2s ease;
      box-sizing: border-box;
    }

    .split-form .form-control:focus {
      outline: none;
      border-color: #435ebe;
      background: #ffffff;
      box-shadow: 0 0 0 3px rgba(67, 94, 190, 0.15);
    }

    .split-form .btn-toggle-password {
      position: absolute;
      right: 10px;
      background: transparent;
      border: none;
      color: #94a3b8;
      font-size: 1.1rem;
      cursor: pointer;
      padding: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: color 0.2s;
    }

    .split-form .btn-toggle-password:hover {
      color: #435ebe;
    }

    /* 2-Kolom Berdampingan untuk Form Ringkas */
    .form-row-2col {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin-bottom: 16px;
    }

    .form-row-2col .form-group {
      margin-bottom: 0;
    }

    /* Tombol Aksi */
    .btn-submit-auth {
      width: 100%;
      height: 46px;
      background: linear-gradient(135deg, #435ebe 0%, #25396f 100%);
      color: #ffffff;
      border: none;
      border-radius: 9px;
      font-size: 0.95rem;
      font-weight: 700;
      letter-spacing: 0.3px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      box-shadow: 0 4px 12px rgba(67, 94, 190, 0.3);
      transition: all 0.2s ease;
      margin-top: 6px;
    }

    .btn-submit-auth:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(67, 94, 190, 0.4);
      background: linear-gradient(135deg, #374fc7 0%, #1e2d5a 100%);
    }

    .btn-submit-auth:active {
      transform: translateY(0);
    }

    /* Demo Quick Fill Chips */
    .split-demo-chips {
      margin-top: 18px;
      padding-top: 16px;
      border-top: 1px dashed #e2e8f0;
    }

    .split-demo-chips span {
      display: block;
      font-size: 0.76rem;
      color: #64748b;
      margin-bottom: 8px;
      font-weight: 600;
    }

    .demo-btn-group {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .chip-demo {
      background: #f1f5f9;
      border: 1px solid #cbd5e1;
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 0.76rem;
      font-weight: 700;
      color: #475569;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s;
    }

    .chip-demo:hover {
      background: #e2e8f0;
      color: #1e293b;
      border-color: #94a3b8;
    }

    /* ═══ Sisi Kanan: Visual Branding Split-Screen ═══ */
    .split-logo-side {
      flex: 1.15;
      background: linear-gradient(145deg, #090e1a 0%, #111b33 50%, #1e293b 100%);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow: hidden;
      padding: 48px;
      box-sizing: border-box;
      color: #ffffff;
      text-align: center;
    }

    .split-logo-side::before {
      content: '';
      position: absolute;
      width: 480px;
      height: 480px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(67, 94, 190, 0.3) 0%, rgba(0,0,0,0) 70%);
      top: 10%;
      right: -100px;
      pointer-events: none;
    }

    .split-logo-side::after {
      content: '';
      position: absolute;
      width: 400px;
      height: 400px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(37, 99, 235, 0.2) 0%, rgba(0,0,0,0) 70%);
      bottom: 5%;
      left: -80px;
      pointer-events: none;
    }

    .logo-card-container {
      position: relative;
      z-index: 2;
      display: flex;
      flex-direction: column;
      align-items: center;
      max-width: 420px;
    }

    .invento-logo-img {
      width: 200px;
      max-width: 80%;
      border-radius: 20px;
      box-shadow: 0 16px 36px rgba(0, 0, 0, 0.5);
      border: 1px solid rgba(255, 255, 255, 0.1);
      margin-bottom: 24px;
      transition: transform 0.3s ease;
    }

    .invento-logo-img:hover {
      transform: scale(1.02);
    }

    .logo-card-container h3 {
      font-size: 1.45rem;
      font-weight: 800;
      letter-spacing: 0.5px;
      margin: 0 0 10px;
      color: #ffffff;
    }

    .logo-card-container p {
      font-size: 0.9rem;
      color: #94a3b8;
      line-height: 1.6;
      margin: 0 0 24px;
    }

    .brand-features-row {
      display: flex;
      gap: 10px;
      justify-content: center;
      flex-wrap: wrap;
    }

    .brand-badge {
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.15);
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 0.78rem;
      color: #cbd5e1;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    /* Responsif */
    @media (max-width: 860px) {
      .split-wrapper {
        flex-direction: column;
      }
      .split-form-side {
        max-width: 100%;
        padding: 32px 20px;
        order: 2;
      }
      .split-logo-side {
        flex: none;
        padding: 36px 20px;
        order: 1;
      }
      .invento-logo-img {
        width: 140px;
        margin-bottom: 14px;
      }
    }

    @media (max-width: 480px) {
      .form-row-2col {
        grid-template-columns: 1fr;
        gap: 14px;
      }
    }
  </style>
</head>
<body class="split-login-body">

  <div class="split-wrapper">
    
    <!-- ═══ SISI KIRI: Formulir Bersih & Ringkas ═══ -->
    <div class="split-form-side">
      <!-- Brand Header -->
      <div class="split-brand-header">
        <div class="split-brand-icon">
          <?= renderBrandLogoHtml($settings['app_logo']) ?>
        </div>
        <div class="split-brand-text">
          <h1><?= htmlspecialchars($settings['app_name']) ?></h1>
          <small><?= htmlspecialchars($settings['app_subtitle']) ?></small>
        </div>
      </div>

      <!-- Segmented Tab Switcher (Masuk vs Daftar Akun) -->
      <div class="auth-tab-switcher" role="tablist">
        <button 
          type="button" 
          class="auth-tab-btn <?= $activeTab === 'login' ? 'active' : '' ?>" 
          id="tabLoginBtn" 
          onclick="switchAuthTab('login')"
        >
          <i class="bi bi-box-arrow-in-right"></i> Masuk
        </button>
        <button 
          type="button" 
          class="auth-tab-btn <?= $activeTab === 'register' ? 'active' : '' ?>" 
          id="tabRegisterBtn" 
          onclick="switchAuthTab('register')"
        >
          <i class="bi bi-person-plus-fill"></i> Daftar Akun
        </button>
      </div>

      <!-- Ringkasan Singkat Tanpa Basa-Basi -->
      <div class="auth-header-desc">
        <h2 class="auth-title" id="authTitle">
          <?= $activeTab === 'register' ? 'Buat Akun Baru' : 'Masuk ke Akun' ?>
        </h2>
        <p class="auth-subtitle" id="authSubtitle">
          <?= $activeTab === 'register' 
            ? 'Lengkapi data singkat berikut untuk mendaftar akun anggota.' 
            : 'Masukkan data Anda untuk mengakses sistem inventaris.' ?>
        </p>
      </div>

      <!-- Pesan Kesalahan / Notifikasi -->
      <?php if (!empty($error)): ?>
        <div class="alert-error" id="authAlertError">
          <i class="bi bi-exclamation-triangle-fill" style="font-size:1.1rem;flex-shrink:0;"></i>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <!-- ════════ FORM 1: MASUK (LOGIN) ════════ -->
      <form 
        action="login.php" 
        method="POST" 
        class="split-form" 
        id="formLogin" 
        style="<?= $activeTab === 'login' ? '' : 'display:none;' ?>"
      >
        <input type="hidden" name="auth_action" value="login">

        <div class="form-group">
          <label for="loginEmail">Email atau Username</label>
          <div class="input-icon-wrapper">
            <i class="bi bi-person input-icon"></i>
            <input 
              type="text" 
              name="email" 
              id="loginEmail" 
              class="form-control" 
              placeholder="nama@email.com atau username" 
              value="<?= htmlspecialchars($loginInput) ?>" 
              required 
              autofocus
            >
          </div>
        </div>

        <div class="form-group">
          <label for="loginPassword">Password</label>
          <div class="input-icon-wrapper">
            <i class="bi bi-lock input-icon"></i>
            <input 
              type="password" 
              name="password" 
              id="loginPassword" 
              class="form-control" 
              placeholder="Masukkan password Anda" 
              required
            >
            <button 
              type="button" 
              class="btn-toggle-password" 
              onclick="togglePasswordVisibility('loginPassword', 'eyeIconLogin')" 
              title="Lihat/Sembunyikan Password"
            >
              <i class="bi bi-eye" id="eyeIconLogin"></i>
            </button>
          </div>
        </div>

        <button type="submit" name="submit_login" class="btn-submit-auth">
          <i class="bi bi-box-arrow-in-right"></i> Masuk Sekarang
        </button>

        <!-- Akses Cepat Demo -->
        <div class="split-demo-chips">
          <span><i class="bi bi-lightning-charge-fill" style="color:#eab308;"></i> Akses Cepat (Klik untuk isi formulir):</span>
          <div class="demo-btn-group">
            <button type="button" class="chip-demo" onclick="fillQuick('admin', 'admin123')">
              <i class="bi bi-shield-lock-fill" style="color:var(--primary);"></i> Admin (admin)
            </button>
            <button type="button" class="chip-demo" onclick="fillQuick('ahmadfadli', 'user123')">
              <i class="bi bi-person-fill" style="color:#0ea5e9;"></i> User (ahmadfadli)
            </button>
          </div>
        </div>
      </form>

      <!-- ════════ FORM 2: DAFTAR AKUN (REGISTER) ════════ -->
      <form 
        action="login.php" 
        method="POST" 
        class="split-form" 
        id="formRegister" 
        style="<?= $activeTab === 'register' ? '' : 'display:none;' ?>"
      >
        <input type="hidden" name="auth_action" value="register">

        <!-- Nama Lengkap -->
        <div class="form-group">
          <label for="regNama">Nama Lengkap</label>
          <div class="input-icon-wrapper">
            <i class="bi bi-person-badge input-icon"></i>
            <input 
              type="text" 
              name="reg_nama" 
              id="regNama" 
              class="form-control" 
              placeholder="Masukkan nama lengkap Anda" 
              value="<?= htmlspecialchars($regNamaInput) ?>" 
              required
            >
          </div>
        </div>

        <!-- Username & Email (2-Kolom Berdampingan) -->
        <div class="form-row-2col">
          <div class="form-group">
            <label for="regUser">Username</label>
            <div class="input-icon-wrapper">
              <i class="bi bi-at input-icon"></i>
              <input 
                type="text" 
                name="reg_username" 
                id="regUser" 
                class="form-control" 
                placeholder="misal: budi_santoso" 
                value="<?= htmlspecialchars($regUserInput) ?>" 
                required
              >
            </div>
          </div>
          <div class="form-group">
            <label for="regEmail">Email Anggota</label>
            <div class="input-icon-wrapper">
              <i class="bi bi-envelope input-icon"></i>
              <input 
                type="email" 
                name="email" 
                id="regEmail" 
                class="form-control" 
                placeholder="nama@email.com" 
                value="<?= htmlspecialchars($regEmailInput) ?>" 
                required
              >
            </div>
          </div>
        </div>

        <!-- Password Baru & Konfirmasi Password (2-Kolom Berdampingan) -->
        <div class="form-row-2col">
          <div class="form-group">
            <label for="regPass">Password Baru</label>
            <div class="input-icon-wrapper">
              <i class="bi bi-lock input-icon"></i>
              <input 
                type="password" 
                name="reg_password" 
                id="regPass" 
                class="form-control" 
                placeholder="Min. 4 karakter" 
                required
              >
              <button 
                type="button" 
                class="btn-toggle-password" 
                onclick="togglePasswordVisibility('regPass', 'eyeIconReg')" 
                title="Lihat/Sembunyikan"
              >
                <i class="bi bi-eye" id="eyeIconReg"></i>
              </button>
            </div>
          </div>
          <div class="form-group">
            <label for="regPassConf">Konfirmasi Password</label>
            <div class="input-icon-wrapper">
              <i class="bi bi-shield-check input-icon"></i>
              <input 
                type="password" 
                name="reg_password_confirm" 
                id="regPassConf" 
                class="form-control" 
                placeholder="Ulangi password" 
                required
              >
              <button 
                type="button" 
                class="btn-toggle-password" 
                onclick="togglePasswordVisibility('regPassConf', 'eyeIconRegConf')" 
                title="Lihat/Sembunyikan"
              >
                <i class="bi bi-eye" id="eyeIconRegConf"></i>
              </button>
            </div>
          </div>
        </div>

        <button type="submit" name="submit_register" class="btn-submit-auth">
          <i class="bi bi-person-check-fill"></i> Daftar Akun
        </button>

        <div style="margin-top:12px;font-size:0.78rem;color:#718096;text-align:center;">
          <em>*(Akun anggota baru akan langsung aktif dan otomatis masuk ke sistem)*</em>
        </div>
      </form>

      <!-- Footer Info -->
      <div style="margin-top:24px;font-size:0.78rem;color:#94a3b8;">
        &copy; 2026 <?= htmlspecialchars($settings['app_name']) ?> &mdash; Sistem Inventaris Himpunan Mahasiswa
      </div>
    </div>

    <!-- ═══ SISI KANAN: Visual Identity Split-Screen ═══ -->
    <div class="split-logo-side">
      <div class="logo-card-container">
        <?php if (!empty($settings['app_logo']) && file_exists(__DIR__ . '/' . ltrim($settings['app_logo'], '/'))): ?>
          <img src="<?= htmlspecialchars(ltrim($settings['app_logo'], '/')) ?>" alt="Logo Sistem" class="invento-logo-img">
        <?php elseif (file_exists(__DIR__ . '/logo.png')): ?>
          <img src="logo.png" alt="INVENTO Logo" class="invento-logo-img">
        <?php else: ?>
          <div class="invento-logo-img" style="display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.05);height:180px;font-size:3rem;color:#ffffff;">
            <i class="bi bi-box-seam-fill"></i>
          </div>
        <?php endif; ?>

        <h3><?= htmlspecialchars($settings['app_name']) ?></h3>
        <p>
          <?= htmlspecialchars($settings['app_subtitle'] ?? 'Platform terintegrasi pengelolaan aset, peminjaman barang, dan manajemen keanggotaan himpunan secara rapi, cepat, dan transparan.') ?>
        </p>

        <div class="brand-features-row">
          <span class="brand-badge"><i class="bi bi-box-seam"></i> Peminjaman Aset</span>
          <span class="brand-badge"><i class="bi bi-clock-history"></i> Riwayat Transparan</span>
          <span class="brand-badge"><i class="bi bi-shield-check"></i> Akses Mandiri</span>
        </div>
      </div>
    </div>

  </div>

  <script>
    /**
     * Berpindah antara Tab Masuk dan Tab Daftar Akun secara instan
     */
    function switchAuthTab(tab) {
      const isLogin = (tab === 'login');
      const tabLoginBtn = document.getElementById('tabLoginBtn');
      const tabRegisterBtn = document.getElementById('tabRegisterBtn');
      const formLogin = document.getElementById('formLogin');
      const formRegister = document.getElementById('formRegister');
      const authTitle = document.getElementById('authTitle');
      const authSubtitle = document.getElementById('authSubtitle');
      const alertBox = document.getElementById('authAlertError');

      if (isLogin) {
        tabLoginBtn.classList.add('active');
        tabRegisterBtn.classList.remove('active');
        formLogin.style.display = 'block';
        formRegister.style.display = 'none';
        authTitle.textContent = 'Masuk ke Akun';
        authSubtitle.textContent = 'Masukkan kredensial Anda untuk mengakses sistem inventaris.';
      } else {
        tabRegisterBtn.classList.add('active');
        tabLoginBtn.classList.remove('active');
        formLogin.style.display = 'none';
        formRegister.style.display = 'block';
        authTitle.textContent = 'Buat Akun Baru';
        authSubtitle.textContent = 'Lengkapi data singkat berikut untuk mendaftar akun anggota.';
      }

      // Sembunyikan notifikasi error lama jika user berpindah tab
      if (alertBox) {
        alertBox.style.display = 'none';
      }
    }

    /**
     * Lihat / sembunyikan password
     */
    function togglePasswordVisibility(fieldId, iconId) {
      const field = document.getElementById(fieldId);
      const icon = document.getElementById(iconId);
      if (!field || !icon) return;

      if (field.type === 'password') {
        field.type = 'text';
        icon.className = 'bi bi-eye-slash';
      } else {
        field.type = 'password';
        icon.className = 'bi bi-eye';
      }
    }

    /**
     * Isi cepat akun demo
     */
    function fillQuick(user, pass) {
      switchAuthTab('login');
      document.getElementById('loginEmail').value = user;
      document.getElementById('loginPassword').value = pass;
    }
  </script>
</body>
</html>
