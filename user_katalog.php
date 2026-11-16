<?php
/**
 * INVENTO — Halaman Katalog & Pinjam Barang (Portal User / Anggota)
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
  <title><?= htmlspecialchars($settings['app_name']) ?> — Katalog &amp; Pinjam Barang</title>
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
<body id="userPage" data-page="user_katalog">

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
            <a href="user_katalog.php" class="nav-link active">
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
            <a href="user_profil.php" class="nav-link">
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
          <span class="page-title"><i class="bi bi-grid-fill" style="color:var(--primary);"></i>&nbsp; Katalog &amp; Pinjam Barang</span>
        </div>
        <div class="navbar-actions">
          <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="text" id="catalogSearchInput" placeholder="Cari nama atau kategori barang...">
          </div>
          <div class="user-avatar" id="topbarUserAvatar" title="Profil Saya" onclick="window.location.href='user_profil.php'" style="cursor:pointer;">
            <?= strtoupper(substr($currentUser['nama_lengkap'] ?? 'U', 0, 1)) ?>
          </div>
        </div>
      </header>

      <!-- Content Body -->
      <main class="content-body">
        
        <!-- Welcome Hero Banner -->
        <section class="hero-section">
          <h2 id="userWelcomeTitle">Selamat Datang, <?= htmlspecialchars($currentUser['nama_lengkap'] ?? 'Anggota') ?> 👋</h2>
          <p>Temukan barang inventaris yang Anda butuhkan dan ajukan peminjaman langsung dengan mengklik tombol <strong>Pinjam</strong> pada barang terkait.</p>
        </section>

        <!-- Stat Cards -->
        <section class="stat-cards" style="margin-bottom:24px;">
          <div class="stat-card">
            <div class="stat-icon bg-primary-light"><i class="bi bi-box-seam"></i></div>
            <div class="stat-info"><h3 id="userStatTotal">—</h3><p>Total Barang</p></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon bg-success-light"><i class="bi bi-check-circle"></i></div>
            <div class="stat-info"><h3 id="userStatAvailable">—</h3><p>Tersedia Dipinjam</p></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon bg-warning-light"><i class="bi bi-arrow-left-right"></i></div>
            <div class="stat-info"><h3 id="userStatBorrowed">—</h3><p>Sedang Dipinjam</p></div>
          </div>
        </section>

        <!-- Filter Kategori -->
        <section class="card-section" style="margin-bottom:24px;">
          <div class="card-header">
            <h2><i class="bi bi-funnel"></i> Filter Kategori</h2>
          </div>
          <div class="card-body">
            <div class="filter-bar" id="categoryFilters"></div>
          </div>
        </section>

        <!-- Katalog Barang -->
        <section class="card-section">
          <div class="card-header">
            <h2><i class="bi bi-grid-3x3-gap-fill"></i> Daftar Barang Inventaris</h2>
            <span style="font-size:0.8rem;color:var(--text-secondary);">Klik tombol <strong>Pinjam</strong> untuk mengajukan peminjaman</span>
          </div>
          <div class="card-body">
            <div class="catalog-grid" id="catalogGrid">
              <!-- Rendered via JavaScript -->
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

  <!-- ═══ Modal Pengajuan Peminjaman ═══ -->
  <div class="modal-overlay" id="loanModal">
    <div class="modal-dialog">
      <div class="modal-header">
        <h3><i class="bi bi-arrow-right-circle-fill" style="color:var(--primary);"></i>&nbsp; Form Pengajuan Peminjaman</h3>
      </div>
      <form id="loanForm" novalidate>
        <div class="modal-body">
          <div class="form-group">
            <label for="loanItemSelect">Barang yang Dipinjam <span style="color:var(--danger);">*</span></label>
            <select class="form-control" id="loanItemSelect" required onchange="handleLoanItemChange()">
              <option value="">-- Pilih Barang --</option>
            </select>
            <div class="form-error"></div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="loanBorrower">Nama Peminjam <span style="color:var(--danger);">*</span></label>
              <input type="text" class="form-control" id="loanBorrower" value="<?= htmlspecialchars($currentUser['nama_lengkap'] ?? '') ?>" readonly style="background:#f1f5f9;cursor:not-allowed;">
              <div class="form-error"></div>
            </div>
            <div class="form-group">
              <label for="loanJumlah">Jumlah (Unit) <span style="color:var(--danger);">*</span></label>
              <input type="number" class="form-control" id="loanJumlah" min="1" value="1" placeholder="Contoh: 1" required>
              <small id="loanStockHint" style="font-size:0.75rem;color:var(--text-secondary);margin-top:2px;display:block;"></small>
              <div class="form-error"></div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="loanDate">Tanggal Pinjam <span style="color:var(--danger);">*</span></label>
              <input type="date" class="form-control" id="loanDate" required>
              <div class="form-error"></div>
            </div>
            <div class="form-group">
              <label for="loanReturnDate">Tanggal Kembali (Rencana) <span style="color:var(--danger);">*</span></label>
              <input type="date" class="form-control" id="loanReturnDate" required>
              <div class="form-error"></div>
            </div>
          </div>

          <div class="form-group">
            <label for="loanKeperluan">Keperluan / Nama Kegiatan <span style="color:var(--danger);">*</span></label>
            <textarea class="form-control" id="loanKeperluan" rows="2" placeholder="Contoh: Digunakan untuk Dokumentasi Rapat Kerja Divisi..." required></textarea>
            <div class="form-error"></div>
          </div>

          <div style="background:var(--primary-light);border-radius:var(--radius-sm);padding:12px 16px;font-size:0.82rem;color:var(--primary);display:flex;gap:8px;align-items:flex-start;">
            <i class="bi bi-info-circle-fill" style="margin-top:2px;"></i>
            <span>Peminjaman tercatat atas nama akun Anda. Pastikan merawat barang dan mengembalikannya tepat waktu.</span>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" id="loanModalCancel" onclick="closeModal('loanModal')">Batal</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-send-fill"></i> Ajukan Peminjaman</button>
        </div>
      </form>
    </div>
  </div>

  <div id="toastContainer" class="toast-container"></div>

  <script src="script.js?v=20261116j"></script>
</body>
</html>
