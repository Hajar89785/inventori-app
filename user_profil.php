<?php
/**
 * INVENTO — Halaman Pengaturan Profil Pengguna / Anggota
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['invento_logged_in']) || $_SESSION['invento_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/app_settings.php';

$userId     = (int) ($_SESSION['invento_user']['id'] ?? 0);
$successMsg = '';
$errorMsg   = '';

$settings = getAppSettings($pdo);

// Ambil data user terkini dari database
$stmt = $pdo->prepare('SELECT * FROM akun WHERE id = :id');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: login.php');
    exit;
}

// ─── Proses Simpan Perubahan Profil & Password ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama        = trim($_POST['nama_lengkap'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $passBaru    = trim($_POST['password'] ?? '');
    $passConfirm = trim($_POST['konfirmasi_password'] ?? '');

    if ($nama === '') {
        $errorMsg = 'Nama lengkap wajib diisi.';
    } elseif ($email === '') {
        $errorMsg = 'Email anggota wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = 'Format email tidak valid. Pastikan penulisan email benar (contoh: nama@email.com).';
    } elseif ($passBaru !== '' || $passConfirm !== '') {
        if (strlen($passBaru) < 4) {
            $errorMsg = 'Password baru minimal 4 karakter.';
        } elseif ($passBaru !== $passConfirm) {
            $errorMsg = 'Konfirmasi password tidak cocok! Pastikan kedua kolom password sama.';
        }
    }

    if ($errorMsg === '') {
        try {
            // Update nama & email
            $upd = $pdo->prepare('UPDATE akun SET nama_lengkap = :nama, email = :email WHERE id = :id');
            $upd->execute([
                ':nama'  => $nama,
                ':email' => $email,
                ':id'    => $userId,
            ]);

            // Update password jika diisi
            if ($passBaru !== '') {
                $hashed = password_hash($passBaru, PASSWORD_DEFAULT);
                try {
                    $pdo->exec("ALTER TABLE akun ADD COLUMN plain_password VARCHAR(255) NULL AFTER password");
                } catch (Exception $e) {}

                try {
                    $updPass = $pdo->prepare('UPDATE akun SET password = :p, plain_password = :plain WHERE id = :id');
                    $updPass->execute([':p' => $hashed, ':plain' => $passBaru, ':id' => $userId]);
                } catch (Exception $e) {
                    $updPass = $pdo->prepare('UPDATE akun SET password = :p WHERE id = :id');
                    $updPass->execute([':p' => $hashed, ':id' => $userId]);
                }
            }

            // Ambil data terbaru & sinkronkan session
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch();
            $_SESSION['invento_user']['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['invento_user']['email']        = $user['email'];

            $successMsg = 'Profil Anda berhasil diperbarui!';
        } catch (PDOException $e) {
            $errorMsg = 'Gagal menyimpan perubahan: ' . $e->getMessage();
        }
    }
}

$currentUser = $_SESSION['invento_user'] ?? $user;
$userJson = json_encode($currentUser);
$settingsJson = json_encode($settings);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($settings['app_name']) ?> — Pengaturan Profil Saya</title>
  <!-- Google Fonts: Nunito (Mazer Theme) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="style.css?v=20261117d">
  <script>
    localStorage.setItem('invento_user', <?= json_encode($userJson) ?>);
    localStorage.setItem('invento_settings', <?= json_encode($settingsJson) ?>);
  </script>
</head>
<body id="userPage" data-page="user_profil">

  <!-- Mobile Sidebar Backdrop -->
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <div class="app-wrapper">
    <!-- ═══ Sidebar Anggota / User ═══ -->
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-brand">
        <div class="brand-icon"><?= renderBrandLogoHtml($settings['app_logo']) ?></div>
        <div>
          <h1 class="brand-app-name"><?= htmlspecialchars($settings['app_name']) ?></h1>
          <small class="brand-app-subtitle"><?= htmlspecialchars($settings['app_subtitle']) ?></small>
        </div>
        <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Tutup Menu">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <nav class="sidebar-nav">
        <!-- ── Menu Peminjam ── -->
        <p class="nav-label">Menu Peminjam</p>
        <ul style="list-style:none;padding:0;margin:0;">
          <li class="nav-item">
            <a href="user_katalog.php" class="nav-link">
              <i class="bi bi-grid-fill"></i> Katalog &amp; Pinjam
            </a>
          </li>
          <li class="nav-item">
            <a href="user_riwayat.php" class="nav-link">
              <i class="bi bi-clock-history"></i> Peminjaman Saya
            </a>
          </li>
        </ul>

        <!-- ── Akun & Sesi ── -->
        <p class="nav-label">Akun Saya</p>
        <ul style="list-style:none;padding:0;margin:0;">
          <li class="nav-item">
            <a href="user_profil.php" class="nav-link active">
              <i class="bi bi-person-gear"></i> Pengaturan Profil
            </a>
          </li>
          <li class="nav-item">
            <a href="javascript:void(0)" class="nav-link btn-logout" onclick="handleLogout()">
              <i class="bi bi-box-arrow-right"></i> Keluar (Logout)
            </a>
          </li>
        </ul>
      </nav>

      <!-- Sidebar User Card (Dapat diklik mengarah ke profil) -->
      <div class="sidebar-user-card" onclick="window.location.href='user_profil.php'" style="cursor:pointer;" title="Buka Profil Saya">
        <div class="sidebar-user-avatar" id="sidebarUserAvatar">
          <?= strtoupper(substr($currentUser['nama_lengkap'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="sidebar-user-info">
          <div class="user-name" id="sidebarUserName"><?= htmlspecialchars($currentUser['nama_lengkap'] ?? 'Anggota Himpunan') ?></div>
          <span class="user-role" id="sidebarUserRole"><?= ($currentUser['role'] ?? '') === 'admin' ? 'Pengurus / Admin' : 'Anggota / Kader' ?></span>
        </div>
      </div>

      <div class="sidebar-footer">
        <p class="mb-0">&copy; 2026 <?= htmlspecialchars($settings['app_name']) ?></p>
      </div>
    </aside>

    <!-- ═══ Main Content ═══ -->
    <div class="main-content">
      <!-- Top Navbar -->
      <header class="top-navbar">
        <div class="d-flex align-center gap-2">
          <button class="btn-sidebar-toggle" id="sidebarToggle" aria-label="Toggle Menu">
            <i class="bi bi-list"></i>
          </button>
          <span class="page-title"><i class="bi bi-person-gear" style="color:var(--primary);"></i>&nbsp; Pengaturan Profil Saya</span>
        </div>
        <div class="navbar-actions">
          <a href="user_katalog.php" class="btn btn-outline" style="border-radius:8px;font-size:0.85rem;padding:6px 14px;">
            <i class="bi bi-grid-fill"></i> Katalog Barang
          </a>
        </div>
      </header>

      <!-- Content Body -->
      <main class="content-body">
        
        <?php if (!empty($successMsg)): ?>
          <div class="alert alert-success" style="background:#e6f4ea;color:#137333;border:1px solid #b7e1cd;border-radius:10px;padding:12px 18px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
            <i class="bi bi-check-circle-fill" style="font-size:1.2rem;"></i>
            <div><?= htmlspecialchars($successMsg) ?></div>
          </div>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
          <div class="alert alert-danger" style="background:#fce8e6;color:#c5221f;border:1px solid #f5c6cb;border-radius:10px;padding:12px 18px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
            <i class="bi bi-exclamation-triangle-fill" style="font-size:1.2rem;"></i>
            <div><?= htmlspecialchars($errorMsg) ?></div>
          </div>
        <?php endif; ?>

        <section class="card-section">
          <div class="card-body">
            
            <div style="text-align:center;margin-bottom:28px;">
              <div class="user-avatar" style="width:78px;height:78px;font-size:1.9rem;margin:0 auto 12px;background:linear-gradient(135deg,var(--primary),#57caeb);box-shadow:0 4px 14px rgba(67,94,190,0.3);">
                <?= strtoupper(substr($currentUser['nama_lengkap'] ?? 'U', 0, 1)) ?>
              </div>
              <h3 style="font-size:1.25rem;font-weight:800;margin-bottom:4px;color:#2d3748;"><?= htmlspecialchars($currentUser['nama_lengkap'] ?? 'Anggota Himpunan') ?></h3>
              <span class="badge" style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:50rem;font-size:0.8rem;background:#e6f4ea;color:#137333;font-weight:700;">
                <i class="bi bi-person-check-fill"></i> Anggota / Peminjam
              </span>
            </div>

            <form action="user_profil.php" method="POST">
              <div class="form-group mb-3">
                <label for="profNama" style="font-weight:700;font-size:0.85rem;color:#4a5568;margin-bottom:6px;display:block;">Nama Lengkap</label>
                <input type="text" class="form-control" id="profNama" name="nama_lengkap" value="<?= htmlspecialchars($user['nama_lengkap'] ?? '') ?>" required style="height:44px;border-radius:8px;">
              </div>

              <div class="form-row mb-3">
                <div class="form-group">
                  <label for="profUser" style="font-weight:700;font-size:0.85rem;color:#4a5568;margin-bottom:6px;display:block;">Username</label>
                  <input type="text" class="form-control" id="profUser" value="<?= htmlspecialchars($user['username'] ?? '') ?>" readonly style="background:#f8fafc;cursor:not-allowed;height:44px;border-radius:8px;">
                </div>
                <div class="form-group">
                  <label for="profEmail" style="font-weight:700;font-size:0.85rem;color:#4a5568;margin-bottom:6px;display:block;">Email Anggota</label>
                  <input type="email" class="form-control" id="profEmail" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required style="height:44px;border-radius:8px;">
                </div>
              </div>

              <!-- ═══ Form Ubah Password (2 Kolom) ═══ -->
              <div class="form-row">
                <div class="form-group">
                  <label for="profPass" style="font-weight:700;font-size:0.85rem;color:#4a5568;margin-bottom:6px;display:block;">Password Baru</label>
                  <input type="password" class="form-control" id="profPass" name="password" placeholder="Masukkan password baru..." style="height:44px;border-radius:8px;">
                </div>
                <div class="form-group">
                  <label for="profPassConfirm" style="font-weight:700;font-size:0.85rem;color:#4a5568;margin-bottom:6px;display:block;">Konfirmasi Password Baru</label>
                  <input type="password" class="form-control" id="profPassConfirm" name="konfirmasi_password" placeholder="Ulangi password baru..." style="height:44px;border-radius:8px;">
                </div>
              </div>
              <small class="text-muted" style="display:block;margin-top:6px;margin-bottom:24px;font-size:0.82rem;color:#718096;">
                <em>*(Kosongkan kedua kolom jika tidak ingin mengubah password)*</em>
              </small>

              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2" style="border-top:1px solid #edf2f7;padding-top:20px;">
                <a href="user_katalog.php" class="btn btn-outline" style="border-radius:8px;padding:9px 18px;">
                  <i class="bi bi-arrow-left"></i> Kembali ke Katalog
                </a>
                <button type="submit" class="btn btn-primary" style="border-radius:8px;padding:9px 24px;font-weight:700;">
                  <i class="bi bi-check2"></i> Simpan Perubahan
                </button>
              </div>
            </form>

          </div>
        </section>

      </main>

      <!-- App Footer -->
      <footer class="app-footer">
        <?= htmlspecialchars($settings['app_name']) ?> &copy; 2026 &mdash; Sistem Inventaris &amp; Peminjaman Barang Himpunan
      </footer>
    </div>
  </div>

  <div id="toastContainer" class="toast-container"></div>

  <!-- Main JavaScript Logic -->
  <script src="script.js?v=20261117d"></script>
  <script>
    if (typeof initSidebar === 'function') {
      initSidebar();
    }
  </script>
</body>
</html>
