<?php
/**
 * INVENTO — Halaman Kelola Peminjaman & Persetujuan (Admin Portal)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['invento_logged_in']) || $_SESSION['invento_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['invento_user']['role']) || $_SESSION['invento_user']['role'] !== 'admin') {
    header('Location: user_katalog.php');
    exit;
}

$currentUser = $_SESSION['invento_user'] ?? [];
$userJson = json_encode($currentUser);

require_once __DIR__ . '/app_settings.php';
$settings = getAppSettings($pdo);
$settingsJson = json_encode($settings);
$statTotal = 0;
$statMenunggu = 0;
$statAktif = 0;
$statSelesai = 0;
$statDitolak = 0;
try {
    $stmt = $pdo->query("SELECT status_pinjam, COUNT(*) as jml FROM peminjaman GROUP BY status_pinjam");
    while ($row = $stmt->fetch()) {
        $st = $row['status_pinjam'];
        $c = (int) $row['jml'];
        $statTotal += $c;
        if ($st === 'menunggu') $statMenunggu += $c;
        elseif ($st === 'disetujui' || $st === 'aktif' || $st === 'terlambat') $statAktif += $c;
        elseif ($st === 'selesai' || $st === 'dikembalikan') $statSelesai += $c;
        elseif ($st === 'ditolak') $statDitolak += $c;
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($settings['app_name']) ?> — Kelola Persetujuan &amp; Peminjaman</title>
  <!-- Google Fonts: Nunito (Mazer Theme) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="style.css">
  <style>
    /* ── Soft Badges dengan Titik Indikator (Mirip Badge Tersedia) ── */
    .badge-soft {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 5px 14px;
      border-radius: 50rem;
      font-size: 0.8rem;
      font-weight: 700;
      letter-spacing: 0.2px;
      white-space: nowrap;
      line-height: 1.2;
    }
    .badge-soft .badge-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      display: inline-block;
      flex-shrink: 0;
    }

    /* 1. Status Selesai */
    .badge-soft-selesai {
      background-color: #e6f4ea !important;
      color: #137333 !important;
    }
    .badge-soft-selesai .badge-dot {
      background-color: #137333 !important;
    }

    /* 2. Status Ditolak */
    .badge-soft-ditolak {
      background-color: #fce8e6 !important;
      color: #c5221f !important;
    }
    .badge-soft-ditolak .badge-dot {
      background-color: #c5221f !important;
    }

    /* 3. Status Sedang Dipinjam */
    .badge-soft-dipinjam {
      background-color: #e8f0fe !important;
      color: #1967d2 !important;
    }
    .badge-soft-dipinjam .badge-dot {
      background-color: #1967d2 !important;
    }

    /* 4. Status Menunggu */
    .badge-soft-menunggu {
      background-color: #fef7e0 !important;
      color: #b06000 !important;
    }
    .badge-soft-menunggu .badge-dot {
      background-color: #b06000 !important;
    }
  </style>
  <script>
    localStorage.setItem('invento_user', <?= json_encode($userJson) ?>);
    localStorage.setItem('invento_settings', <?= json_encode($settingsJson) ?>);
  </script>
</head>
<body id="adminPage" data-page="peminjaman">

  <!-- Mobile Sidebar Backdrop -->
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <div class="app-wrapper">
    <!-- ═══ Sidebar Admin (Mazer Style) ═══ -->
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
            <a href="admin_peminjaman.php" class="nav-link active">
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
            <a href="javascript:void(0)" class="nav-link btn-open-profile" onclick="openProfileModal()">
              <i class="bi bi-person-gear"></i> Pengaturan Profil
            </a>
          </li>
          <li class="nav-item">
            <a href="admin_settings.php" class="nav-link">
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
      <!-- Top Navbar -->
      <header class="top-navbar">
        <div class="d-flex align-center gap-2">
          <button class="btn-sidebar-toggle" id="sidebarToggle" aria-label="Toggle Menu">
            <i class="bi bi-list"></i>
          </button>
          <span class="page-title"><i class="bi bi-arrow-left-right" style="color:var(--primary);"></i>&nbsp; Kelola Persetujuan &amp; Peminjaman</span>
        </div>
        <div class="navbar-actions">
          <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="text" id="loanSearchInput" placeholder="Cari nama peminjam / barang...">
          </div>
          <div class="user-avatar" title="Profil Admin" onclick="openProfileModal()" style="cursor:pointer;">
            <?= strtoupper(substr($currentUser['nama_lengkap'] ?? 'A', 0, 1)) ?>
          </div>
        </div>
      </header>

      <!-- Content Body -->
      <main class="content-body">
        
        <!-- Stat Cards (5 Kategori Selaras Laporan Cetak) -->
        <section class="stat-cards" style="margin-bottom:24px;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
          <div class="stat-card" style="cursor:pointer;" onclick="filterLoanByStatus('')" title="Tampilkan Semua Transaksi">
            <div class="stat-icon bg-primary-light"><i class="bi bi-clipboard-data"></i></div>
            <div class="stat-info"><h3 id="loanStatTotal"><?= $statTotal ?></h3><p>Total Pengajuan</p></div>
          </div>
          <div class="stat-card" style="cursor:pointer;border:1.5px solid #fed7aa;" onclick="filterLoanByStatus('menunggu')" title="Filter: Menunggu Persetujuan">
            <div class="stat-icon bg-warning-light"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-info"><h3 id="loanStatMenunggu" style="color:#d97706;"><?= $statMenunggu ?></h3><p>Menunggu Persetujuan</p></div>
          </div>
          <div class="stat-card" style="cursor:pointer;" onclick="filterLoanByStatus('disetujui')" title="Filter: Sedang Dipinjam">
            <div class="stat-icon bg-primary-light"><i class="bi bi-box-seam"></i></div>
            <div class="stat-info"><h3 id="loanStatAktif" style="color:var(--primary);"><?= $statAktif ?></h3><p>Sedang Dipinjam</p></div>
          </div>
          <div class="stat-card" style="cursor:pointer;" onclick="filterLoanByStatus('selesai')" title="Filter: Selesai Dikembalikan">
            <div class="stat-icon bg-success-light"><i class="bi bi-check-circle"></i></div>
            <div class="stat-info"><h3 id="loanStatSelesai" style="color:#10b981;"><?= $statSelesai ?></h3><p>Selesai Dikembalikan</p></div>
          </div>
          <div class="stat-card" style="cursor:pointer;border:1.5px solid #fecaca;" onclick="filterLoanByStatus('ditolak')" title="Filter: Pengajuan Ditolak">
            <div class="stat-icon bg-danger-light"><i class="bi bi-x-circle"></i></div>
            <div class="stat-info"><h3 id="loanStatDitolak" style="color:#ef4444;"><?= $statDitolak ?></h3><p>Ditolak</p></div>
          </div>
        </section>

        <!-- Filter Toolbar & Cetak Laporan -->
        <section class="card-section" style="margin-bottom:0; border-bottom:none; border-radius: var(--radius-md) var(--radius-md) 0 0;">
          <div class="card-header" style="flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;">
            <div class="d-flex align-center gap-2">
              <h2><i class="bi bi-table"></i> Transaksi &amp; Pengajuan Peminjaman</h2>
            </div>
            
            <div class="d-flex align-center gap-2" style="flex-wrap:wrap;">
              <!-- Dropdown Filter Bulan -->
              <div class="filter-select-wrapper" style="position:relative;display:inline-block;">
                <select id="loanMonthSelect" class="form-control" style="min-width:160px;padding:7px 32px 7px 12px;border-radius:18px;font-size:0.83rem;font-weight:700;color:var(--text-primary);background:#fff;border:1.5px solid #dce7f6;appearance:none;-webkit-appearance:none;cursor:pointer;">
                  <option value="">Semua Bulan</option>
                  <option value="2026-11">November 2026</option>
                  <option value="2026-10">Oktober 2026</option>
                  <option value="2026-09">September 2026</option>
                  <option value="2026-08">Agustus 2026</option>
                  <option value="2026-07">Juli 2026</option>
                  <option value="2026-06">Juni 2026</option>
                  <option value="2026-05">Mei 2026</option>
                  <option value="2026-04">April 2026</option>
                  <option value="2026-03">Maret 2026</option>
                  <option value="2026-02">Februari 2026</option>
                  <option value="2026-01">Januari 2026</option>
                </select>
                <i class="bi bi-chevron-down" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);pointer-events:none;color:var(--text-secondary);font-size:0.75rem;"></i>
              </div>

              <!-- Dropdown Filter Status -->
              <div class="filter-select-wrapper" style="position:relative;display:inline-block;">
                <select id="loanStatusSelect" class="form-control" style="min-width:170px;padding:7px 32px 7px 12px;border-radius:18px;font-size:0.83rem;font-weight:700;color:var(--text-primary);background:#fff;border:1.5px solid #dce7f6;appearance:none;-webkit-appearance:none;cursor:pointer;">
                  <option value="">Semua Status</option>
                  <option value="menunggu">⏳ Menunggu</option>
                  <option value="disetujui">📦 Sedang Dipinjam</option>
                  <option value="selesai">✅ Selesai</option>
                  <option value="ditolak">❌ Ditolak</option>
                </select>
                <i class="bi bi-chevron-down" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);pointer-events:none;color:var(--text-secondary);font-size:0.75rem;"></i>
              </div>

              <!-- Tombol Cetak Laporan -->
              <button type="button" class="btn btn-primary btn-sm" onclick="printLoansReport()" title="Buka cetak laporan resmi / simpan ke PDF" style="white-space:nowrap;padding:7px 14px;border-radius:18px;font-weight:700;font-size:0.83rem;">
                <i class="bi bi-printer-fill"></i> Cetak Laporan
              </button>
            </div>
          </div>
        </section>

        <!-- Loans Table -->
        <section class="card-section" style="border-radius: 0 0 var(--radius-md) var(--radius-md); margin-top:0;">
          <div class="card-body" style="padding:0;">
            <div class="table-wrapper">
              <table class="data-table">
                <thead>
                  <tr>
                    <th style="width:45px;" class="text-center">No</th>
                    <th>Nama Peminjam</th>
                    <th>Barang Inventaris</th>
                    <th style="text-align:center;">Jumlah</th>
                    <th>Tanggal Pinjam</th>
                    <th>Jadwal Kembali</th>
                    <th>Keperluan / Kegiatan</th>
                    <th style="text-align:center;">Status</th>
                    <th style="width:190px;text-align:center;">Aksi Admin</th>
                  </tr>
                </thead>
                <tbody id="loansTableBody">
                  <tr><td colspan="9" class="text-center" style="padding:32px;color:var(--text-secondary);">Memuat data peminjaman...</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

      </main>

      <footer class="app-footer">
        INVENTO &copy; 2026 &mdash; Sistem Inventaris &amp; Peminjaman Barang Himpunan
      </footer>
    </div>
  </div>

  <!-- ═══ Modal Pengaturan Profil Pengurus ═══ -->
  <div class="modal-overlay" id="profileModal">
    <div class="modal-dialog">
      <div class="modal-header">
        <h3><i class="bi bi-person-gear" style="color:var(--primary);"></i>&nbsp; Pengaturan Profil Pengurus</h3>
      </div>
      <form id="profileForm" onsubmit="handleProfileSave(event)">
        <div class="modal-body">
          <div style="text-align:center;margin-bottom:20px;">
            <div class="user-avatar" style="width:68px;height:68px;font-size:1.6rem;margin:0 auto 10px;background:linear-gradient(135deg,var(--primary),#57caeb);">
              <?= strtoupper(substr($currentUser['nama_lengkap'] ?? 'A', 0, 1)) ?>
            </div>
            <h4 style="font-size:1.05rem;margin-bottom:2px;"><?= htmlspecialchars($currentUser['nama_lengkap'] ?? 'Administrator') ?></h4>
            <span class="badge badge-role-admin"><i class="bi bi-shield-lock-fill"></i> Pengurus / Admin</span>
          </div>

          <div class="form-group">
            <label for="profNama">Nama Lengkap Pengurus</label>
            <input type="text" class="form-control" id="profNama" value="<?= htmlspecialchars($currentUser['nama_lengkap'] ?? '') ?>">
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="profUser">Username</label>
              <input type="text" class="form-control" id="profUser" value="<?= htmlspecialchars($currentUser['username'] ?? '') ?>" readonly style="background:#f8fafc;">
            </div>
            <div class="form-group">
              <label for="profEmail">Email Himpunan</label>
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

  <!-- Main JavaScript Logic -->
  <script src="script.js?v=20261116j"></script>
</body>
</html>
