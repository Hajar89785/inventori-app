<?php
/**
 * ========================================================
 * INVENTO — Halaman Login (Split-Screen Layout)
 * ========================================================
 * 
 * - Desain Split-screen: Form kiri bersih, Logo INVENTO kanan.
 * - Form hanya Email, Password, dan tombol Masuk (tanpa dropdown "Login Sebagai").
 * - Role diverifikasi otomatis dari tabel `users` (atau `akun`).
 * - Jika role == 'admin' -> arahkan ke admin_dashboard.php
 * - Jika role == 'user'  -> arahkan ke user_katalog.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/koneksi.php';

$error = '';
$emailInput = '';

// Jika sudah memiliki sesi login aktif, langsung arahkan ke dashboard yang sesuai
if (isset($_SESSION['invento_logged_in']) && $_SESSION['invento_logged_in'] === true) {
    if (isset($_SESSION['invento_user']['role']) && $_SESSION['invento_user']['role'] === 'admin') {
        header('Location: admin_dashboard.php');
        exit;
    } else {
        header('Location: user_katalog.php');
        exit;
    }
}

// Proses submit form login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $emailInput = $email;

    if ($email === '' || $password === '') {
        $error = 'Email/Username dan Password wajib diisi.';
    } else {
        try {
            // Cek di tabel users (atau akun)
            $stmt = $pdo->prepare('
                SELECT id, nama_lengkap, username, email, password, role, status
                FROM users
                WHERE email = :val1 OR username = :val2
                LIMIT 1
            ');
            $stmt->execute([
                ':val1' => $email,
                ':val2' => $email,
            ]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'Email atau Username tidak terdaftar dalam sistem.';
            } else {
                // Verifikasi password (hash atau plain fallback)
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
                    // Login Berhasil! Set Sesi
                    $_SESSION['invento_logged_in'] = true;
                    $_SESSION['invento_user'] = [
                        'id'           => (int) $user['id'],
                        'nama_lengkap' => $user['nama_lengkap'],
                        'username'     => $user['username'],
                        'email'        => $user['email'],
                        'role'         => $user['role'],
                    ];

                    // Arahkan otomatis berdasarkan role dari database
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
            $error = 'Terjadi kesalahan pada database: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>INVENTO — Masuk ke Sistem Inventaris</title>
  <!-- Google Fonts: Nunito -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="style.css">
  <style>
    /* ═══ Split-Screen Layout ═══ */
    body.split-login-body {
      margin: 0;
      padding: 0;
      font-family: 'Nunito', sans-serif;
      background: #ffffff;
      color: var(--text-main);
      overflow-x: hidden;
    }

    .split-wrapper {
      display: flex;
      min-height: 100vh;
      width: 100%;
    }

    /* Sisi Kiri (Form) */
    .split-form-side {
      flex: 1;
      max-width: 560px;
      padding: 48px 56px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      background: #ffffff;
      box-sizing: border-box;
      z-index: 2;
    }

    .split-brand-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 32px;
    }

    .split-brand-icon {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      background: linear-gradient(135deg, #435ebe 0%, #25396f 100%);
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.4rem;
      box-shadow: 0 4px 12px rgba(67, 94, 190, 0.3);
    }

    .split-brand-text h1 {
      margin: 0;
      font-size: 1.45rem;
      font-weight: 800;
      letter-spacing: 0.5px;
      color: var(--text-main);
      line-height: 1.2;
    }

    .split-brand-text small {
      font-size: 0.78rem;
      color: var(--text-secondary);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.8px;
    }

    .split-welcome h2 {
      font-size: 1.75rem;
      font-weight: 800;
      color: #1e293b;
      margin: 0 0 8px;
    }

    .split-welcome p {
      font-size: 0.92rem;
      color: #64748b;
      margin: 0 0 24px;
      line-height: 1.5;
    }

    /* Alert Pesan Error */
    .alert-error {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #b91c1c;
      padding: 12px 16px;
      border-radius: 10px;
      font-size: 0.88rem;
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 22px;
      animation: shake 0.4s ease-in-out;
    }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      20%, 60% { transform: translateX(-6px); }
      40%, 80% { transform: translateX(6px); }
    }

    .split-form .form-group {
      margin-bottom: 20px;
    }

    .split-form label {
      display: block;
      font-size: 0.88rem;
      font-weight: 700;
      color: #334155;
      margin-bottom: 8px;
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
      font-size: 1.1rem;
      pointer-events: none;
    }

    .split-form .form-control {
      width: 100%;
      height: 48px;
      padding: 10px 44px 10px 42px;
      font-size: 0.92rem;
      font-family: inherit;
      border: 1.5px solid #cbd5e1;
      border-radius: 10px;
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
      right: 12px;
      background: transparent;
      border: none;
      color: #94a3b8;
      font-size: 1.15rem;
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

    .btn-submit-login {
      width: 100%;
      height: 50px;
      background: linear-gradient(135deg, #435ebe 0%, #25396f 100%);
      color: #ffffff;
      border: none;
      border-radius: 10px;
      font-size: 1rem;
      font-weight: 700;
      letter-spacing: 0.3px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      box-shadow: 0 4px 14px rgba(67, 94, 190, 0.35);
      transition: all 0.2s ease;
      margin-top: 10px;
    }

    .btn-submit-login:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 18px rgba(67, 94, 190, 0.45);
      background: linear-gradient(135deg, #374fc7 0%, #1e2d5a 100%);
    }

    .btn-submit-login:active {
      transform: translateY(0);
    }

    /* Demo Quick Fill Chips */
    .split-demo-chips {
      margin-top: 24px;
      padding-top: 20px;
      border-top: 1px dashed #e2e8f0;
    }

    .split-demo-chips span {
      display: block;
      font-size: 0.78rem;
      color: #64748b;
      margin-bottom: 10px;
      font-weight: 600;
    }

    .demo-btn-group {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .chip-demo {
      background: #f1f5f9;
      border: 1px solid #cbd5e1;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.78rem;
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

    /* Sisi Kanan (Logo & Brand Image) */
    .split-logo-side {
      flex: 1.2;
      background: linear-gradient(145deg, #0f172a 0%, #172554 50%, #1e293b 100%);
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
      width: 450px;
      height: 450px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(67, 94, 190, 0.25) 0%, rgba(0,0,0,0) 70%);
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
      width: 240px;
      max-width: 80%;
      border-radius: 20px;
      box-shadow: 0 16px 36px rgba(0, 0, 0, 0.45);
      border: 1px solid rgba(255, 255, 255, 0.1);
      margin-bottom: 28px;
      transition: transform 0.3s ease;
    }

    .invento-logo-img:hover {
      transform: scale(1.03);
    }

    .logo-card-container h3 {
      font-size: 1.5rem;
      font-weight: 800;
      letter-spacing: 0.5px;
      margin: 0 0 12px;
      color: #ffffff;
    }

    .logo-card-container p {
      font-size: 0.92rem;
      color: #94a3b8;
      line-height: 1.6;
      margin: 0;
    }

    /* Responsif */
    @media (max-width: 900px) {
      .split-wrapper {
        flex-direction: column;
      }
      .split-form-side {
        max-width: 100%;
        padding: 36px 24px;
        order: 2;
      }
      .split-logo-side {
        flex: none;
        padding: 40px 20px;
        order: 1;
      }
      .invento-logo-img {
        width: 160px;
        margin-bottom: 16px;
      }
    }
  </style>
</head>
<body class="split-login-body">

  <div class="split-wrapper">
    
    <!-- ═══ SISI KIRI: Form Login Bersih ═══ -->
    <div class="split-form-side">
      <div class="split-brand-header">
        <div class="split-brand-icon">
          <i class="bi bi-box-seam-fill"></i>
        </div>
        <div class="split-brand-text">
          <h1>INVENTO</h1>
          <small>Inventaris &amp; Peminjaman</small>
        </div>
      </div>

      <div class="split-welcome">
        <h2>Selamat Datang Kembali 👋</h2>
        <p>Silakan masukkan akun Anda untuk mengakses sistem inventaris dan peminjaman.</p>
      </div>

      <!-- Pesan Kesalahan Autentikasi -->
      <?php if (!empty($error)): ?>
        <div class="alert-error">
          <i class="bi bi-exclamation-triangle-fill" style="font-size:1.15rem;"></i>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <!-- Form Login (Hanya Email & Password, TANPA Dropdown "Login Sebagai") -->
      <form action="login.php" method="POST" class="split-form" novalidate>
        <div class="form-group">
          <label for="email">Email atau Username</label>
          <div class="input-icon-wrapper">
            <i class="bi bi-person input-icon"></i>
            <input 
              type="text" 
              name="email" 
              id="email" 
              class="form-control" 
              placeholder="nama@email.com atau username" 
              value="<?= htmlspecialchars($emailInput) ?>" 
              required 
              autofocus
            >
          </div>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-icon-wrapper">
            <i class="bi bi-lock input-icon"></i>
            <input 
              type="password" 
              name="password" 
              id="password" 
              class="form-control" 
              placeholder="Masukkan password Anda" 
              required
            >
            <button type="button" class="btn-toggle-password" onclick="togglePasswordVisibility()" title="Lihat/Sembunyikan Password">
              <i class="bi bi-eye" id="eyeIcon"></i>
            </button>
          </div>
        </div>

        <button type="submit" name="login" class="btn-submit-login">
          <i class="bi bi-box-arrow-in-right"></i> Masuk Sekarang
        </button>
      </form>

      <!-- Demo Login Cepat -->
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

      <div style="margin-top:28px;font-size:0.78rem;color:#94a3b8;">
        &copy; 2026 INVENTO &mdash; Sistem Inventaris Himpunan Mahasiswa
      </div>
    </div>

    <!-- ═══ SISI KANAN: Logo & Identitas Visual ═══ -->
    <div class="split-logo-side">
      <div class="logo-card-container">
        <img src="logo.png" alt="INVENTO Logo" class="invento-logo-img">
        <h3>INVENTO</h3>
        <p>
          Platform terintegrasi pengelolaan aset, peminjaman barang, dan manajemen keanggotaan himpunan secara rapi, cepat, dan transparan.
        </p>
      </div>
    </div>

  </div>

  <script>
    function togglePasswordVisibility() {
      const passInp = document.getElementById('password');
      const eyeIcon = document.getElementById('eyeIcon');
      if (passInp.type === 'password') {
        passInp.type = 'text';
        eyeIcon.className = 'bi bi-eye-slash';
      } else {
        passInp.type = 'password';
        eyeIcon.className = 'bi bi-eye';
      }
    }

    function fillQuick(user, pass) {
      document.getElementById('email').value = user;
      document.getElementById('password').value = pass;
    }
  </script>
</body>
</html>
