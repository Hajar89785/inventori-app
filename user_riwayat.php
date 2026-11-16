<?php
/**
 * INVENTO — Halaman Riwayat & Status Peminjaman Saya (Portal User / Anggota)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['invento_logged_in']) || $_SESSION['invento_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['invento_user'] ?? [];
$userJson = json_encode($currentUser);

require_once __DIR__ . '/app_settings.php';
$settings = getAppSettings($pdo);
$settingsJson = json_encode($settings);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($settings['app_name']) ?> — Peminjaman Saya</title>
  <!-- Google Fonts: Nunito -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="style.css">
  <script>
    localStorage.setItem('invento_user', <?= json_encode($userJson) ?>);
    localStorage.setItem('invento_settings', <?= json_encode($settingsJson) ?>);
  </script>
</head>
<body id="userPage" data-page="user_riwayat">

  <!-- Mobile Sidebar Backdrop -->
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <div class="app-wrapper">
    <!-- ═══ Sidebar User / Peminjam ═══ -->
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-brand">
        <div class="brand-icon"><?= renderBrandLogoHtml($settings['app_logo']) ?></div>
        <div>
          <h1 class="brand-app-name"><?= htmlspecialchars($settings['app_name']) ?></h1>
          <small class="brand-app-subtitle">Portal Peminjam</small>
        </div>
        <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Tutup Menu">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <nav class="sidebar-nav">
        <!-- ── Menu Utama ── -->
        <p class="nav-label">Menu Peminjam</p>
        <ul style="list-style:none;padding:0;margin:0;">
          <li class="nav-item">
            <a href="user_katalog.php" class="nav-link">
              <i class="bi bi-grid-fill"></i> Katalog &amp; Pinjam
            </a>
          </li>
          <li class="nav-item">
            <a href="user_riwayat.php" class="nav-link active">
              <i class="bi bi-clock-history"></i> Peminjaman Saya
            </a>
          </li>
        </ul>

        <!-- ── Akun & Sesi ── -->
        <p class="nav-label">Akun Saya</p>
        <ul style="list-style:none;padding:0;margin:0;">
          <li class="nav-item">
            <a href="javascript:void(0)" class="nav-link btn-open-profile" onclick="openProfileModal()">
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

      <!-- Sidebar User Card -->
      <div class="sidebar-user-card">
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
          <span class="page-title"><i class="bi bi-clock-history" style="color:var(--primary);"></i>&nbsp; Peminjaman Saya</span>
        </div>
        <div class="navbar-actions">
          <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="text" id="myLoansSearchInput" placeholder="Cari nama barang / keperluan...">
          </div>
          <div class="user-avatar" id="topbarUserAvatar" title="Profil Saya" onclick="openProfileModal()" style="cursor:pointer;">
            <?= strtoupper(substr($currentUser['nama_lengkap'] ?? 'U', 0, 1)) ?>
          </div>
        </div>
      </header>

      <!-- Content Body -->
      <main class="content-body">
        
        <!-- Welcome Hero Banner -->
        <section class="hero-section">
          <h2>Riwayat Peminjaman Anda 📋</h2>
          <p>Pantau semua barang inventaris yang pernah atau sedang Anda pinjam, lengkap dengan jadwal pengembalian dan status terkininya.</p>
        </section>

        <!-- Stat Cards Khusus User -->
        <section class="stat-cards" style="margin-bottom:24px;">
          <div class="stat-card" style="cursor:pointer;" onclick="filterMyLoansByStatus('semua')" title="Tampilkan Semua Peminjaman">
            <div class="stat-icon bg-primary-light"><i class="bi bi-box-seam"></i></div>
            <div class="stat-info"><h3 id="statMyTotal">0</h3><p>Total Peminjaman</p></div>
          </div>
          <div class="stat-card" style="cursor:pointer;border:1.5px solid #fed7aa;" onclick="filterMyLoansByStatus('menunggu')" title="Filter: Menunggu Persetujuan">
            <div class="stat-icon bg-warning-light"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-info"><h3 id="statMyPending" style="color:#d97706;">0</h3><p>Menunggu Persetujuan</p></div>
          </div>
          <div class="stat-card" style="cursor:pointer;" onclick="filterMyLoansByStatus('disetujui')" title="Filter: Sedang Dipinjam">
            <div class="stat-icon bg-primary-light"><i class="bi bi-arrow-left-right"></i></div>
            <div class="stat-info"><h3 id="statMyActive" style="color:var(--primary);">0</h3><p>Sedang Dipinjam</p></div>
          </div>
          <div class="stat-card" style="cursor:pointer;" onclick="filterMyLoansByStatus('selesai')" title="Filter: Sudah Selesai">
            <div class="stat-icon bg-success-light"><i class="bi bi-check-circle"></i></div>
            <div class="stat-info"><h3 id="statMyReturned" style="color:#10b981;">0</h3><p>Sudah Selesai</p></div>
          </div>
        </section>

        <!-- Riwayat Peminjaman Card Section -->
        <section class="card-section">
          <div class="card-header" style="flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;">
            <div class="d-flex align-center gap-2">
              <h2><i class="bi bi-table"></i> Daftar Peminjaman Saya</h2>
            </div>
            <div class="d-flex align-center gap-3">
              <div class="d-flex align-center gap-2">
                <label for="myLoansStatusSelect" style="font-size:0.85rem;font-weight:700;color:var(--text-secondary);display:flex;align-items:center;gap:6px;margin:0;white-space:nowrap;">
                  <i class="bi bi-funnel-fill" style="color:var(--primary);"></i> Status:
                </label>
                <div class="filter-select-wrapper" style="position:relative;display:inline-block;">
                  <select id="myLoansStatusSelect" class="form-control" style="min-width:210px;padding:8px 36px 8px 14px;border-radius:20px;font-size:0.86rem;font-weight:700;color:var(--text-primary);background:#fff;border:1.5px solid #dce7f6;appearance:none;-webkit-appearance:none;cursor:pointer;box-shadow:0 2px 6px rgba(0,0,0,0.03);">
                    <option value="semua">Semua Status</option>
                    <option value="menunggu">⏳ Menunggu Persetujuan</option>
                    <option value="disetujui">📦 Sedang Dipinjam</option>
                    <option value="selesai">✅ Selesai Dikembalikan</option>
                    <option value="ditolak">❌ Ditolak</option>
                  </select>
                  <i class="bi bi-chevron-down" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);pointer-events:none;color:var(--text-secondary);font-size:0.8rem;"></i>
                </div>
              </div>
              <a href="user_katalog.php" class="btn btn-primary btn-sm" style="white-space:nowrap;">
                <i class="bi bi-plus-lg"></i> Pinjam Barang Lagi
              </a>
            </div>
          </div>

          <div class="card-body" style="padding:0;">
            <div class="table-wrapper">
              <table class="data-table">
                <thead>
                  <tr>
                    <th style="width:50px;" class="text-center">No</th>
                    <th>Nama Barang</th>
                    <th>Kategori</th>
                    <th style="text-align:center;">Jumlah</th>
                    <th>Tanggal Pinjam</th>
                    <th>Tenggat Kembali</th>
                    <th>Durasi</th>
                    <th>Keperluan / Kegiatan</th>
                    <th style="text-align:center;">Status</th>
                  </tr>
                </thead>
                <tbody id="myLoansTableBody">
                  <tr>
                    <td colspan="9" class="text-center" style="padding:32px;color:var(--text-secondary);">
                      <i class="bi bi-arrow-repeat" style="font-size:1.5rem;"></i><br>Memuat riwayat peminjaman...
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

      </main>

      <!-- App Footer -->
      <footer class="app-footer">
        INVENTO &copy; 2026 &mdash; Sistem Inventaris &amp; Peminjaman Barang Himpunan
      </footer>
    </div>
  </div>

  <!-- ═══ Modal Pengaturan Profil Pengguna ═══ -->
  <div class="modal-overlay" id="profileModal">
    <div class="modal-dialog">
      <div class="modal-header">
        <h3><i class="bi bi-person-gear" style="color:var(--primary);"></i>&nbsp; Pengaturan Profil Saya</h3>
      </div>
      <form id="profileForm" onsubmit="handleProfileSave(event)" novalidate>
        <div class="modal-body">
          <div style="text-align:center;margin-bottom:20px;">
            <div class="user-avatar" id="modalProfAvatar" style="width:72px;height:72px;font-size:1.8rem;margin:0 auto 10px;background:var(--primary-gradient);">
              <?= strtoupper(substr($currentUser['nama_lengkap'] ?? 'U', 0, 1)) ?>
            </div>
            <h4 style="margin:0;font-size:1.05rem;" id="modalProfName"><?= htmlspecialchars($currentUser['nama_lengkap'] ?? 'Pengguna') ?></h4>
            <span class="badge badge-success" style="font-size:0.75rem;padding:3px 10px;" id="modalProfBadge">Anggota / Kader</span>
          </div>

          <div class="form-group">
            <label for="profNama">Nama Lengkap</label>
            <input type="text" class="form-control" id="profNama" value="<?= htmlspecialchars($currentUser['nama_lengkap'] ?? '') ?>" required>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="profUser">Username</label>
              <input type="text" class="form-control" id="profUser" value="<?= htmlspecialchars($currentUser['username'] ?? '') ?>" readonly style="background:#f1f5f9;cursor:not-allowed;">
            </div>
            <div class="form-group">
              <label for="profEmail">Email</label>
              <input type="email" class="form-control" id="profEmail" value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="profPass">Password Baru</label>
              <input type="password" class="form-control" id="profPass" name="password" placeholder="Masukkan password baru...">
            </div>
            <div class="form-group">
              <label for="profPassConfirm">Konfirmasi Password Baru</label>
              <input type="password" class="form-control" id="profPassConfirm" name="konfirmasi_password" placeholder="Ulangi password baru...">
            </div>
          </div>
          <small class="text-muted" style="display:block;margin-top:-6px;margin-bottom:16px;font-size:0.8rem;color:#718096;">
            <em>*(Kosongkan kedua kolom jika tidak ingin mengubah password)*</em>
          </small>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" onclick="closeProfileModal()">Tutup</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i> Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>

  <div id="toastContainer" class="toast-container"></div>

  <script src="script.js?v=20261116j"></script>
</body>
</html>
