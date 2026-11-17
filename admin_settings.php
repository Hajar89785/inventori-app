<?php
/**
 * INVENTO — Halaman Pengaturan Sistem & Branding (Khusus Admin)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─── Restriksi Hak Akses: Hanya untuk Admin ─────────────
if (!isset($_SESSION['invento_logged_in']) || $_SESSION['invento_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['invento_user']['role']) || $_SESSION['invento_user']['role'] !== 'admin') {
    header('Location: user_katalog.php');
    exit;
}

require_once __DIR__ . '/app_settings.php';

$currentUser = $_SESSION['invento_user'] ?? [];
$userJson    = json_encode($currentUser);

$successMsg = '';
$errorMsg   = '';

// Ambil data pengaturan terkini dari database
$settings = getAppSettings($pdo);

// ─── Proses Simpan Pengaturan Sistem & Branding ─────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appName     = trim($_POST['app_name'] ?? '');
    $appSubtitle = trim($_POST['app_subtitle'] ?? '');
    $hapusLogo   = isset($_POST['hapus_logo']) && $_POST['hapus_logo'] === '1';

    if ($appName === '') {
        $errorMsg = 'Nama aplikasi tidak boleh kosong.';
    }

    $logoPath = $settings['app_logo'] ?? '';

    // Jika admin memilih untuk menghapus logo dan kembali ke ikon default
    if ($hapusLogo) {
        if (!empty($logoPath) && file_exists(__DIR__ . '/' . ltrim($logoPath, '/'))) {
            @unlink(__DIR__ . '/' . ltrim($logoPath, '/'));
        }
        $logoPath = '';
    }

    // Proses upload file logo baru
    if ($errorMsg === '' && isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $fileTmp  = $_FILES['logo']['tmp_name'];
        $fileSize = $_FILES['logo']['size'];
        $fileName = $_FILES['logo']['name'];

        if ($fileSize > 2 * 1024 * 1024) {
            $errorMsg = 'Ukuran file logo maksimal 2MB.';
        } else {
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExts = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
            if (!in_array($ext, $allowedExts, true)) {
                $errorMsg = 'Format file logo harus berupa PNG, JPG, JPEG, WEBP, atau SVG.';
            } else {
                $uploadDir = __DIR__ . '/uploads';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                }

                $newFileName = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $targetPath  = $uploadDir . '/' . $newFileName;

                if (move_uploaded_file($fileTmp, $targetPath)) {
                    if (!empty($logoPath) && file_exists(__DIR__ . '/' . ltrim($logoPath, '/'))) {
                        @unlink(__DIR__ . '/' . ltrim($logoPath, '/'));
                    }
                    $logoPath = 'uploads/' . $newFileName;
                } else {
                    $errorMsg = 'Gagal menyimpan file logo yang diunggah ke server.';
                }
            }
        }
    }

    if ($errorMsg === '') {
        try {
            $stmt = $pdo->prepare("
                UPDATE `settings`
                SET `app_name` = :name, `app_subtitle` = :sub, `app_logo` = :logo
                WHERE `id` = 1
            ");
            $stmt->execute([
                ':name' => $appName,
                ':sub'  => $appSubtitle,
                ':logo' => $logoPath,
            ]);

            // Refresh settings
            $settings['app_name']     = $appName;
            $settings['app_subtitle'] = $appSubtitle;
            $settings['app_logo']     = $logoPath;

            $successMsg = 'Pengaturan sistem & branding berhasil diperbarui!';
        } catch (PDOException $e) {
            $errorMsg = 'Gagal menyimpan pengaturan ke database: ' . $e->getMessage();
        }
    }
}

$settingsJson = json_encode($settings);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($settings['app_name']) ?> — Pengaturan Sistem &amp; Branding</title>
  <!-- Google Fonts: Nunito (Mazer Theme) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="style.css?v=20261117d">
  <style>
    .brand-preview-box {
      background: #1e1e2d;
      border-radius: 12px;
      padding: 16px 20px;
      color: #fff;
      display: inline-flex;
      align-items: center;
      gap: 14px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }
    .preview-logo-icon {
      width: 44px;
      height: 44px;
      border-radius: 10px;
      background: linear-gradient(135deg, var(--primary), #57caeb);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-size: 1.4rem;
      overflow: hidden;
      flex-shrink: 0;
    }
    .preview-logo-icon img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }
    .preview-brand-info h4 {
      margin: 0;
      font-size: 1.2rem;
      font-weight: 800;
      letter-spacing: 0.5px;
      color: #fff;
    }
    .preview-brand-info small {
      font-size: 0.75rem;
      color: #a2a3b7;
      font-weight: 600;
      display: block;
    }
    .upload-zone {
      border: 2px dashed #cbd5e1;
      border-radius: 12px;
      padding: 24px;
      text-align: center;
      background: #f8fafc;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .upload-zone:hover {
      border-color: var(--primary);
      background: #f0f4ff;
    }
  </style>
  <script>
    localStorage.setItem('invento_user', <?= json_encode($userJson) ?>);
    localStorage.setItem('invento_settings', <?= json_encode($settingsJson) ?>);
  </script>
</head>
<body id="adminPage" data-page="settings">

  <!-- Mobile Sidebar Backdrop -->
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <div class="app-wrapper">
    <!-- ═══ Sidebar Admin (Mazer Style) ═══ -->
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-brand">
        <div class="brand-icon">
          <?= renderBrandLogoHtml($settings['app_logo']) ?>
        </div>
        <div>
          <h1 class="brand-app-name"><?= htmlspecialchars($settings['app_name']) ?></h1>
          <small class="brand-app-subtitle"><?= htmlspecialchars($settings['app_subtitle']) ?></small>
        </div>
        <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Tutup Menu">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <nav class="sidebar-nav">
        <!-- ── Menu Utama ── -->
        <p class="nav-label">Menu Utama</p>
        <ul style="list-style:none;padding:0;margin:0;">
          <li class="nav-item">
            <a href="admin_dashboard.php" class="nav-link">
              <i class="bi bi-grid-fill"></i> Dashboard
            </a>
          </li>
          <li class="nav-item">
            <a href="kelola_barang.html" class="nav-link">
              <i class="bi bi-box-seam-fill"></i> Data Barang
            </a>
          </li>
          <li class="nav-item">
            <a href="admin_peminjaman.php" class="nav-link">
              <i class="bi bi-arrow-left-right"></i> Peminjaman
            </a>
          </li>
        </ul>

        <!-- ── Manajemen Akun ── -->
        <p class="nav-label">Manajemen Akun</p>
        <ul style="list-style:none;padding:0;margin:0;">
          <li class="nav-item">
            <a href="manajemen_akun.html" class="nav-link">
              <i class="bi bi-people-fill"></i> Pengurus &amp; Anggota
            </a>
          </li>
          <li class="nav-item">
            <a href="admin_profil.php" class="nav-link">
              <i class="bi bi-person-gear"></i> Pengaturan Profil
            </a>
          </li>
          <!-- ── Menu Pengaturan Sistem (Khusus Admin) ── -->
          <li class="nav-item">
            <a href="admin_settings.php" class="nav-link active">
              <i class="bi bi-sliders"></i> Pengaturan Sistem
            </a>
          </li>
          <li class="nav-item">
            <a href="javascript:void(0)" class="nav-link btn-logout" onclick="handleLogout()">
              <i class="bi bi-box-arrow-right"></i> Keluar (Logout)
            </a>
          </li>
        </ul>
      </nav>

      <!-- Sidebar User Card -->
      <div class="sidebar-user-card">
        <div class="sidebar-user-avatar">
          <?= strtoupper(substr($currentUser['nama_lengkap'] ?? 'A', 0, 1)) ?>
        </div>
        <div class="sidebar-user-info">
          <div class="user-name"><?= htmlspecialchars($currentUser['nama_lengkap'] ?? 'Administrator') ?></div>
          <span class="user-role">Pengurus Inti</span>
        </div>
      </div>

      <div class="sidebar-footer">
        <p class="mb-0">&copy; 2026 <?= htmlspecialchars($settings['app_name']) ?></p>
      </div>
    </aside>

    <!-- ═══ Main Content ═══ -->
    <div class="main-content">
      <!-- Top Navbar (Persis Seperti Halaman Peminjaman) -->
      <header class="top-navbar">
        <div class="d-flex align-center gap-2">
          <button class="btn-sidebar-toggle" id="sidebarToggle" aria-label="Toggle Menu">
            <i class="bi bi-list"></i>
          </button>
          <span class="page-title"><i class="bi bi-sliders" style="color:var(--primary);"></i>&nbsp; Pengaturan Sistem &amp; Branding</span>
        </div>
      </header>

      <!-- Content Body (Persis Seperti Halaman Peminjaman) -->
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

        <!-- Section 1: Pratinjau Tampilan Sidebar Brand -->
        <section class="card-section" style="margin-bottom:24px;">
          <div class="card-header" style="padding-bottom:14px;border-bottom:1px solid #edf2f7;margin-bottom:18px;">
            <h3 style="font-size:1.05rem;font-weight:700;color:var(--text-primary);margin-bottom:4px;">
              <i class="bi bi-eye-fill" style="color:var(--primary);"></i> Pratinjau Tampilan Sidebar Brand
            </h3>
            <p style="font-size:0.85rem;color:var(--text-secondary);margin:0;">Tampilan langsung logo dan nama aplikasi pada header sidebar sistem:</p>
          </div>
          <div class="card-body">
            <div class="brand-preview-box" id="liveBrandPreview">
              <div class="preview-logo-icon" id="previewLogoBox">
                <?php if (!empty($settings['app_logo']) && file_exists(__DIR__ . '/' . $settings['app_logo'])): ?>
                  <img src="<?= htmlspecialchars($settings['app_logo']) ?>" id="previewLogoImg" alt="Logo">
                <?php else: ?>
                  <i class="bi bi-box-seam-fill" id="previewDefaultIcon"></i>
                <?php endif; ?>
              </div>
              <div class="preview-brand-info">
                <h4 id="previewAppName"><?= htmlspecialchars($settings['app_name']) ?></h4>
                <small id="previewAppSubtitle"><?= htmlspecialchars($settings['app_subtitle']) ?></small>
              </div>
            </div>
          </div>
        </section>

        <!-- Section 2: Form Pengaturan Identitas Aplikasi -->
        <section class="card-section">
          <div class="card-header" style="padding-bottom:14px;border-bottom:1px solid #edf2f7;margin-bottom:22px;">
            <h3 style="font-size:1.05rem;font-weight:700;color:var(--text-primary);margin-bottom:4px;">
              <i class="bi bi-palette-fill" style="color:var(--primary);"></i> Identitas &amp; Logo Aplikasi
            </h3>
            <p style="font-size:0.85rem;color:var(--text-secondary);margin:0;">Sesuaikan identitas visual aplikasi untuk nama sistem, teks sub-judul/header, dan berkas logo.</p>
          </div>
          <div class="card-body">
            <form action="admin_settings.php" method="POST" enctype="multipart/form-data" id="settingsForm">
              
              <!-- 1. Nama Aplikasi -->
              <div class="form-group mb-3">
                <label for="appNameInput" style="font-weight:700;font-size:0.88rem;color:#2d3748;margin-bottom:6px;display:block;">
                  Nama Aplikasi <span style="color:#e53e3e;">*</span>
                </label>
                <input type="text" class="form-control" id="appNameInput" name="app_name" value="<?= htmlspecialchars($settings['app_name']) ?>" required placeholder="Contoh: INVENTO, E-INVENTARIS..." style="height:44px;border-radius:8px;">
                <small class="text-muted" style="display:block;margin-top:4px;font-size:0.8rem;color:#718096;">Nama utama yang muncul di sidebar, header, dan judul dokumen.</small>
              </div>

              <!-- 2. Sub-Judul / Header -->
              <div class="form-group mb-3">
                <label for="appSubtitleInput" style="font-weight:700;font-size:0.88rem;color:#2d3748;margin-bottom:6px;display:block;">
                  Sub-Judul / Header Aplikasi
                </label>
                <input type="text" class="form-control" id="appSubtitleInput" name="app_subtitle" value="<?= htmlspecialchars($settings['app_subtitle']) ?>" placeholder="Contoh: Inventaris Himpunan, Sistem Peminjaman Barang..." style="height:44px;border-radius:8px;">
                <small class="text-muted" style="display:block;margin-top:4px;font-size:0.8rem;color:#718096;">Keterangan pelengkap yang berada tepat di bawah nama aplikasi pada sidebar.</small>
              </div>

              <!-- 3. Unggah File Logo Baru -->
              <div class="form-group mb-4">
                <label style="font-weight:700;font-size:0.88rem;color:#2d3748;margin-bottom:6px;display:block;">
                  Logo Aplikasi
                </label>
                
                <div class="upload-zone" onclick="document.getElementById('logoFileInput').click()">
                  <i class="bi bi-cloud-arrow-up-fill" style="font-size:2.2rem;color:var(--primary);display:block;margin-bottom:8px;"></i>
                  <span style="font-weight:700;font-size:0.95rem;color:#2d3748;display:block;">Pilih atau Tarik File Logo ke Sini</span>
                  <span style="font-size:0.8rem;color:#718096;display:block;margin-top:4px;">Format yang didukung: <strong>PNG, JPG, JPEG, WEBP, SVG</strong> (Maksimal 2MB).</span>
                  <input type="file" id="logoFileInput" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" style="display:none;" onchange="handleLogoChange(event)">
                </div>

                <div id="fileInfoBox" style="display:none;margin-top:12px;padding:10px 14px;background:#ebf3ff;border-radius:8px;font-size:0.85rem;color:#1e40af;align-items:center;justify-content:space-between;">
                  <span id="fileNameLabel"><i class="bi bi-file-image"></i> file_logo.png</span>
                  <button type="button" class="btn btn-sm btn-outline" style="padding:2px 8px;font-size:0.75rem;" onclick="cancelLogoUpload()">Batalkan</button>
                </div>

                <?php if (!empty($settings['app_logo'])): ?>
                  <div style="margin-top:14px;display:flex;align-items:center;gap:10px;">
                    <label style="display:inline-flex;align-items:center;gap:6px;font-size:0.85rem;color:#e53e3e;cursor:pointer;">
                      <input type="checkbox" name="hapus_logo" value="1" id="chkHapusLogo" onchange="toggleHapusLogo(this)">
                      <span>Hapus logo kustom saat ini dan gunakan kembali ikon bawaan (<i class="bi bi-box-seam-fill"></i>)</span>
                    </label>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Tombol Aksi -->
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2" style="border-top:1px solid #edf2f7;padding-top:22px;">
                <a href="admin_dashboard.php" class="btn btn-outline" style="border-radius:8px;padding:9px 18px;">
                  <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                </a>
                <button type="submit" class="btn btn-primary" style="border-radius:8px;padding:9px 24px;font-weight:700;">
                  <i class="bi bi-check2"></i> Simpan Pengaturan
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
  <script>
    const nameInp = document.getElementById('appNameInput');
    const subInp = document.getElementById('appSubtitleInput');
    const prevName = document.getElementById('previewAppName');
    const prevSub = document.getElementById('previewAppSubtitle');
    const fileInp = document.getElementById('logoFileInput');
    const fileInfo = document.getElementById('fileInfoBox');
    const fileNameLbl = document.getElementById('fileNameLabel');
    const logoBox = document.getElementById('previewLogoBox');

    nameInp.addEventListener('input', () => {
      prevName.textContent = nameInp.value.trim() || 'INVENTO';
    });

    subInp.addEventListener('input', () => {
      prevSub.textContent = subInp.value.trim() || 'Inventaris Himpunan';
    });

    function handleLogoChange(e) {
      const file = e.target.files && e.target.files[0];
      if (file) {
        fileInfo.style.display = 'flex';
        fileNameLbl.innerHTML = '<i class="bi bi-image"></i> ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
        
        const reader = new FileReader();
        reader.onload = function(evt) {
          logoBox.innerHTML = '<img src="' + evt.target.result + '" alt="Preview Logo" style="width:100%;height:100%;object-fit:contain;">';
        };
        reader.readAsDataURL(file);

        const chkHapus = document.getElementById('chkHapusLogo');
        if (chkHapus) chkHapus.checked = false;
      }
    }

    function cancelLogoUpload() {
      fileInp.value = '';
      fileInfo.style.display = 'none';
      const currentLogo = <?= json_encode($settings['app_logo']) ?>;
      if (currentLogo) {
        logoBox.innerHTML = '<img src="' + currentLogo + '" alt="Preview Logo" style="width:100%;height:100%;object-fit:contain;">';
      } else {
        logoBox.innerHTML = '<i class="bi bi-box-seam-fill"></i>';
      }
    }

    function toggleHapusLogo(chk) {
      if (chk.checked) {
        fileInp.value = '';
        fileInfo.style.display = 'none';
        logoBox.innerHTML = '<i class="bi bi-box-seam-fill"></i>';
      } else {
        cancelLogoUpload();
      }
    }
  </script>
</body>
</html>
