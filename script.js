/* ============================================
   INVENTO - script.js
   Sistem Inventaris & Peminjaman Himpunan
   (Mazer Dashboard Edition)
   ============================================ */

// ─── API Endpoints ───────────────────────────────────────
const API = {
  getBarang:         'api/get_barang.php',
  tambahBarang:      'api/tambah_barang.php',
  updateBarang:      'api/update_barang.php',
  hapusBarang:       'api/hapus_barang.php',
  getDashboard:      'api/get_dashboard.php',
  getPeminjaman:     'api/get_peminjaman.php',
  tambahPeminjaman:  'api/tambah_peminjaman.php',
  kembalikanBarang:  'api/kembalikan_barang.php',
  getAkun:           'api/get_akun.php',
  tambahAkun:        'api/tambah_akun.php',
  updateAkun:        'api/update_akun.php',
  hapusAkun:         'api/hapus_akun.php',
};

// ─── Fetch Helpers ───────────────────────────────────────
async function apiFetch(url, opts = {}) {
  try {
    const res = await fetch(url, { headers: { 'Content-Type': 'application/json' }, ...opts });
    return await res.json();
  } catch (e) {
    console.error('API Error:', e);
    return { success: false, message: 'Gagal menghubungi server. Pastikan Laragon aktif.' };
  }
}
const apiGet  = url => apiFetch(url);
const apiPost = (url, body) => apiFetch(url, { method: 'POST', body: JSON.stringify(body) });

// ─── Utilities ───────────────────────────────────────────
const CATEGORY_ICONS = { 
  Elektronik:'bi-cpu', Furnitur:'bi-lamp', Perlengkapan:'bi-box-seam', 
  Outdoor:'bi-tree', Kendaraan:'bi-truck', Olahraga:'bi-dribbble', Lainnya:'bi-archive' 
};
function getCategoryIcon(c) { return CATEGORY_ICONS[c] || 'bi-tag'; }
function escHtml(s) { const d = document.createElement('div'); d.textContent = s||''; return d.innerHTML; }
function escAttr(s) { return (s||'').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }
function setText(id, v) { const el = document.getElementById(id); if (el) el.textContent = v; }
function formatDate(ds) { 
  if (!ds) return '-'; 
  return new Date(ds).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'}); 
}

// ─── Toast Notification ──────────────────────────────────
function showToast(msg, type='success') {
  let c = document.getElementById('toastContainer');
  if (!c) { 
    c = document.createElement('div'); 
    c.id='toastContainer'; 
    c.className='toast-container'; 
    document.body.appendChild(c); 
  }
  const icons = { success:'bi-check-circle-fill', danger:'bi-x-circle-fill', warning:'bi-exclamation-triangle-fill' };
  const t = document.createElement('div'); 
  t.className = `toast toast-${type}`;
  t.innerHTML = `<i class="bi ${icons[type]||icons.success} toast-icon"></i><span>${msg}</span><button class="toast-close" onclick="this.parentElement.classList.add('toast-hide');setTimeout(()=>this.parentElement.remove(),300)">&times;</button>`;
  c.appendChild(t);
  setTimeout(() => { t.classList.add('toast-hide'); setTimeout(() => t.remove(), 300); }, 3500);
}

// ─── Confirm Dialog ──────────────────────────────────────
function showConfirm(title, msg, onOk) {
  let o = document.getElementById('confirmOverlay'); 
  if (o) o.remove();
  o = document.createElement('div'); 
  o.id='confirmOverlay'; 
  o.className='confirm-overlay';
  o.innerHTML = `
    <div class="confirm-box">
      <div class="confirm-icon"><i class="bi bi-exclamation-triangle"></i></div>
      <h4>${title}</h4><p>${msg}</p>
      <div class="confirm-actions">
        <button class="btn btn-outline" id="confirmCancel">Batal</button>
        <button class="btn btn-danger" id="confirmOk">Ya, Lanjutkan</button>
      </div>
    </div>`;
  document.body.appendChild(o);
  requestAnimationFrame(() => o.classList.add('active'));
  const close = () => { o.classList.remove('active'); setTimeout(() => o.remove(), 300); };
  document.getElementById('confirmCancel').onclick = close;
  document.getElementById('confirmOk').onclick = () => { close(); onOk(); };
}

// ─── Sidebar / Modal / Form Helpers ─────────────────────
function initSidebar() {
  const btn = document.getElementById('sidebarToggle');
  const sb  = document.getElementById('sidebar');
  const bd  = document.getElementById('sidebarBackdrop');
  if (!btn || !sb) return;
  btn.addEventListener('click', () => { 
    sb.classList.toggle('open'); 
    bd && bd.classList.toggle('active'); 
  });
  if (bd) bd.addEventListener('click', () => { 
    sb.classList.remove('open'); 
    bd.classList.remove('active'); 
  });

  // Profil modal trigger buttons across all admin pages
  document.querySelectorAll('.btn-open-profile, #btnOpenProfile').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      openProfileModal();
    });
  });

  // Close profile modal when clicking on backdrop
  const profModal = document.getElementById('profileModal');
  if (profModal) {
    profModal.addEventListener('click', e => {
      if (e.target === profModal) closeProfileModal();
    });
  }
}

function openModal(id)  { const m = document.getElementById(id); if(m){ m.classList.add('active'); document.body.style.overflow='hidden'; } }
function closeModal(id) { const m = document.getElementById(id); if(m){ m.classList.remove('active'); document.body.style.overflow=''; } }

function showFieldError(fid, msg) { 
  const f = document.getElementById(fid);
  if (!f) return;
  const formGroup = f.closest('.form-group') || f.parentElement;
  const e = formGroup ? formGroup.querySelector('.form-error') : null;
  if (e) { e.textContent = msg; e.classList.add('show'); }
  f.style.borderColor = 'var(--danger)'; 
}
function hideFieldError(fid) { 
  const f = document.getElementById(fid);
  if (!f) return;
  const formGroup = f.closest('.form-group') || f.parentElement;
  const e = formGroup ? formGroup.querySelector('.form-error') : null;
  if (e) e.classList.remove('show');
  f.style.borderColor = ''; 
}
function clearFormErrors(form) { 
  if(!form)return; 
  form.querySelectorAll('.form-error').forEach(e=>e.classList.remove('show')); 
  form.querySelectorAll('.form-control').forEach(e=>e.style.borderColor=''); 
}

// Profile Modal Utilities (Global)
window.openProfileModal = function() {
  const userJson = localStorage.getItem('invento_user');
  if (userJson) {
    try {
      const u = JSON.parse(userJson);
      const namaInp = document.getElementById('profNama');
      const userInp = document.getElementById('profUser');
      const emailInp = document.getElementById('profEmail');
      const passInp = document.getElementById('profPass');
      if (namaInp) namaInp.value = u.nama_lengkap || '';
      if (userInp) userInp.value = u.username || '';
      if (emailInp) emailInp.value = u.email || '';
      if (passInp) passInp.value = '';

      const modalTitle = document.getElementById('modalProfName');
      if (modalTitle) modalTitle.textContent = u.nama_lengkap || 'Pengguna';
      const modalAvatar = document.getElementById('modalProfAvatar');
      if (modalAvatar) modalAvatar.textContent = (u.nama_lengkap || 'U').charAt(0).toUpperCase();
      const modalBadge = document.getElementById('modalProfBadge');
      if (modalBadge) {
        modalBadge.textContent = u.role === 'admin' ? 'Pengurus / Admin' : 'Anggota / Kader';
      }
    } catch (e) {}
  }
  openModal('profileModal');
};

window.closeProfileModal = function() {
  closeModal('profileModal');
};

window.handleProfileSave = async function(e) {
  if (e) e.preventDefault();
  const userJson = localStorage.getItem('invento_user');
  if (!userJson) {
    showToast('Sesi tidak ditemukan. Silakan login kembali.', 'danger');
    return;
  }
  let u;
  try {
    u = JSON.parse(userJson);
  } catch (err) {
    return;
  }

  const nama = document.getElementById('profNama')?.value.trim();
  const email = document.getElementById('profEmail')?.value.trim() || '';
  const pass = document.getElementById('profPass')?.value.trim() || '';

  if (!nama) {
    showToast('Nama lengkap tidak boleh kosong.', 'danger');
    return;
  }

  try {
    const payload = {
      id: u.id,
      nama_lengkap: nama,
      username: u.username,
      email: email,
      role: u.role,
      status: u.status || 'aktif'
    };
    if (pass) {
      payload.password = pass;
    }

    const r = await apiPost('api/update_akun.php', payload);
    if (r.success) {
      const updatedUser = {
        ...u,
        nama_lengkap: nama,
        email: email
      };
      localStorage.setItem('invento_user', JSON.stringify(updatedUser));
      updateSidebarUser();
      closeProfileModal();
      showToast('Profil berhasil diperbarui!', 'success');
    } else {
      showToast(r.message || 'Gagal menyimpan profil.', 'danger');
    }
  } catch (err) {
    showToast('Terjadi kesalahan saat menyimpan profil.', 'danger');
  }
};

// ─── Logout Helper (Global) ──────────────────────────────
window.handleLogout = function() {
  showConfirm('Konfirmasi Logout', 'Apakah Anda yakin ingin keluar dari sistem INVENTO?', async () => {
    try {
      await apiPost('api/logout.php', {});
    } catch (e) {}
    localStorage.removeItem('invento_user');
    showToast('Berhasil logout. Mengalihkan ke halaman login...', 'info');
    setTimeout(() => {
      window.location.href = 'login.php';
    }, 600);
  });
  setTimeout(() => {
    const okBtn = document.getElementById('confirmOk');
    if (okBtn) {
      okBtn.textContent = 'Ya, Keluar';
      okBtn.className = 'btn btn-danger';
    }
  }, 50);
};

// Update sidebar display with logged in user
function updateSidebarUser() {
  const userJson = localStorage.getItem('invento_user');
  if (userJson) {
    try {
      const u = JSON.parse(userJson);
      const initial = (u.nama_lengkap || 'U').charAt(0).toUpperCase();

      document.querySelectorAll('.sidebar-user-avatar, .user-avatar, #topbarUserAvatar, #sidebarUserAvatar').forEach(el => {
        el.textContent = initial;
      });
      document.querySelectorAll('.sidebar-user-info .user-name, #sidebarUserName').forEach(el => {
        el.textContent = u.nama_lengkap;
      });
      document.querySelectorAll('.sidebar-user-info .user-role, #sidebarUserRole').forEach(el => {
        el.textContent = u.role === 'admin' ? 'Pengurus Inti' : 'Anggota / Kader';
      });

      const welcomeTitle = document.getElementById('userWelcomeTitle');
      if (welcomeTitle) {
        welcomeTitle.innerHTML = `Selamat Datang, ${escHtml(u.nama_lengkap)} 👋`;
      }

      const borrowerInput = document.getElementById('loanBorrower');
      if (borrowerInput) {
        borrowerInput.value = u.nama_lengkap;
      }
    } catch (e) {}
  }
}


// ═════════════════════════════════════════════════════════════
//  PAGE 1: ADMIN — DASHBOARD (Mazer Theme & ApexCharts)
// ═════════════════════════════════════════════════════════════

let annualChartInstance = null;

async function initDashboardPage() {
  initSidebar();

  // Tanggal Hari Ini
  const dateEl = document.getElementById('dashboardDate');
  if (dateEl) {
    dateEl.textContent = new Date().toLocaleDateString('id-ID', {
      weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    });
  }

  // Load Data Dashboard dari API
  const res = await apiGet(API.getDashboard);
  if (!res.success || !res.data) {
    showToast('Gagal memuat data dashboard.', 'danger');
    return;
  }

  const { stats, year, monthly_loans, categories, recent_loans } = res.data;

  // 1. Set Kartu Statistik
  setText('statTotal', stats.total_unit || 0);
  setText('statAvailable', stats.total_tersedia || 0);
  setText('statBorrowed', stats.total_dipinjam || 0);
  setText('statCategories', stats.total_kategori || 0);
  setText('chartYearBadge', `Tahun ${year || new Date().getFullYear()}`);

  // 2. Render Grafik Tren Peminjaman Tahunan (ApexCharts Area Spline)
  renderAnnualLoanChart(monthly_loans || []);

  // 3. Render Proporsi Donut Chart & Category Progress List
  renderAvailabilityDonut(stats || {});
  renderCategoryProgress(categories || [], stats.total_unit || 1);

  // 4. Render Aktivitas Peminjaman Terbaru
  renderRecentLoansTable(recent_loans || []);
}

// ── ApexCharts: Grafik Peminjaman Tahunan ───────────────────
function renderAnnualLoanChart(monthlyData) {
  const chartEl = document.getElementById('annualLoanChart');
  if (!chartEl || typeof ApexCharts === 'undefined') return;

  if (annualChartInstance) {
    annualChartInstance.destroy();
  }

  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

  const options = {
    series: [{
      name: 'Frekuensi Peminjaman',
      data: monthlyData
    }],
    chart: {
      type: 'area',
      height: 330,
      fontFamily: 'Nunito, sans-serif',
      toolbar: { show: false },
      zoom: { enabled: false },
      dropShadow: {
        enabled: true,
        top: 4,
        left: 0,
        blur: 8,
        color: '#435ebe',
        opacity: 0.15
      }
    },
    colors: ['#435ebe'],
    dataLabels: { enabled: false },
    stroke: {
      curve: 'smooth',
      width: 3.5,
      colors: ['#435ebe']
    },
    fill: {
      type: 'gradient',
      gradient: {
        shadeIntensity: 1,
        opacityFrom: 0.45,
        opacityTo: 0.05,
        stops: [0, 90, 100],
        colorStops: [
          { offset: 0, color: '#435ebe', opacity: 0.5 },
          { offset: 100, color: '#ffffff', opacity: 0.05 }
        ]
      }
    },
    markers: {
      size: 5,
      colors: ['#435ebe'],
      strokeColors: '#fff',
      strokeWidth: 2,
      hover: { size: 7 }
    },
    xaxis: {
      categories: months,
      labels: {
        style: {
          colors: '#7c8db5',
          fontSize: '12px',
          fontWeight: 600
        }
      },
      axisBorder: { show: false },
      axisTicks: { show: false }
    },
    yaxis: {
      labels: {
        style: {
          colors: '#7c8db5',
          fontSize: '12px',
          fontWeight: 600
        },
        formatter: val => Math.round(val)
      },
      min: 0
    },
    grid: {
      borderColor: '#eef2f8',
      strokeDashArray: 4,
      xaxis: { lines: { show: false } },
      yaxis: { lines: { show: true } }
    },
    tooltip: {
      theme: 'light',
      y: {
        formatter: val => `${val} transaksi peminjaman`
      }
    }
  };

  annualChartInstance = new ApexCharts(chartEl, options);
  annualChartInstance.render();
}

// ── Donut Chart Proporsi ──────────────────────────────────
function renderAvailabilityDonut(stats) {
  const el = document.getElementById('chartAvailability');
  if (!el) return;

  const total    = stats.total_unit || 0;
  const tersedia = stats.total_tersedia || 0;
  const dipinjam = stats.total_dipinjam || 0;
  const pct      = total > 0 ? Math.round((tersedia / total) * 100) : 0;

  const C = 2 * Math.PI * 60;
  const aDash = (tersedia / Math.max(total, 1)) * C;
  const bDash = (dipinjam / Math.max(total, 1)) * C;

  el.innerHTML = `
    <div class="donut-chart" style="width:130px;height:130px;margin:0 auto 10px;">
      <svg viewBox="0 0 160 160">
        <circle style="stroke:var(--body-bg);stroke-width:18;" cx="80" cy="80" r="60"></circle>
        <circle style="stroke:var(--success);stroke-dasharray:${C};stroke-dashoffset:${C - aDash};" cx="80" cy="80" r="60"></circle>
        <circle style="stroke:var(--warning);stroke-dasharray:${C};stroke-dashoffset:${C - bDash};transform:rotate(${(tersedia / Math.max(total, 1)) * 360}deg);transform-origin:center;" cx="80" cy="80" r="60"></circle>
      </svg>
      <div class="donut-center">
        <div class="donut-value" style="font-size:1.5rem;">${pct}%</div>
        <div class="donut-label" style="font-size:0.7rem;">siap pakai</div>
      </div>
    </div>
    <div class="donut-legend" style="gap:8px;">
      <div class="donut-legend-item">
        <span class="legend-dot dot-available"></span>
        <span style="font-size:0.82rem;">Tersedia di Sekretariat</span>
        <span class="legend-value">${tersedia}</span>
      </div>
      <div class="donut-legend-item">
        <span class="legend-dot dot-borrowed"></span>
        <span style="font-size:0.82rem;">Sedang Dipinjam</span>
        <span class="legend-value">${dipinjam}</span>
      </div>
    </div>`;
}

// ── Progress Bar Kategori di Side Widget ───────────────────
function renderCategoryProgress(categories, totalUnit) {
  const el = document.getElementById('categoryProgressList');
  if (!el) return;

  if (!categories.length) {
    el.innerHTML = '<p class="text-muted text-center" style="font-size:0.82rem;">Belum ada kategori.</p>';
    return;
  }

  el.innerHTML = categories.map(cat => {
    const total = parseInt(cat.total, 10);
    const pct = Math.round((total / Math.max(totalUnit, 1)) * 100);
    return `
      <div class="category-progress-item">
        <div class="cat-header">
          <span><i class="bi ${getCategoryIcon(cat.kategori)}"></i> ${escHtml(cat.kategori)}</span>
          <span style="color:var(--text-secondary);">${total} unit (${pct}%)</span>
        </div>
        <div class="cat-bar-bg">
          <div class="cat-bar-fill" style="width: ${pct}%;"></div>
        </div>
      </div>`;
  }).join('');
}

// ── Tabel Ringkasan Aktivitas Peminjaman Terbaru ──────────
function renderRecentLoansTable(loans) {
  const tbody = document.getElementById('recentLoansBody');
  if (!tbody) return;

  if (!loans.length) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7">
          <div class="empty-state">
            <i class="bi bi-clock-history"></i>
            <h4>Belum ada aktivitas peminjaman</h4>
            <p>Data peminjaman barang himpunan akan ditampilkan di sini.</p>
          </div>
        </td>
      </tr>`;
    return;
  }

  const SL = {
    aktif:        { t: 'Sedang Dipinjam', c: 'badge-aktif' },
    dikembalikan: { t: 'Dikembalikan',    c: 'badge-dikembalikan' },
    terlambat:    { t: 'Terlambat',       c: 'badge-terlambat' }
  };

  tbody.innerHTML = loans.map(l => {
    const s = SL[l.status_pinjam] || SL.aktif;
    const canReturn = l.status_pinjam === 'aktif';
    const initial = (l.nama_peminjam || 'A').charAt(0).toUpperCase();

    return `
      <tr>
        <td>
          <div class="borrower-pill">
            <div class="borrower-avatar">${initial}</div>
            <div>
              <strong style="color:var(--text-primary);">${escHtml(l.nama_peminjam)}</strong>
            </div>
          </div>
        </td>
        <td>
          <div class="d-flex align-center gap-2">
            <i class="bi ${l.ikon || 'bi-box'}" style="font-size:1.1rem;color:var(--primary);"></i>
            <span>${escHtml(l.nama_barang)}</span>
          </div>
        </td>
        <td>${formatDate(l.tanggal_pinjam)}</td>
        <td>${formatDate(l.tanggal_kembali)}</td>
        <td>${l.durasi} hari</td>
        <td><span class="badge ${s.c}"><span class="badge-dot"></span> ${s.t}</span></td>
        <td>
          ${canReturn
            ? `<button class="btn btn-success btn-sm" onclick="returnItemDashboard(${l.id})">
                 <i class="bi bi-box-arrow-in-left"></i> Kembali
               </button>`
            : `<span class="badge" style="background:#e8faf4;color:#42b893;font-size:0.75rem;"><i class="bi bi-check-all"></i> Selesai</span>`
          }
        </td>
      </tr>`;
  }).join('');
}

async function returnItemDashboard(loanId) {
  showConfirm('Konfirmasi Pengembalian', 'Tandai bahwa barang inventaris ini telah dikembalikan ke sekretariat?', async () => {
    const res = await apiPost(API.kembalikanBarang, { id: loanId });
    if (res.success) {
      showToast(res.message, 'success');
      initDashboardPage(); // reload dashboard stats & table
    } else {
      showToast(res.message, 'danger');
    }
  });
}


// ═════════════════════════════════════════════════════════════
//  PAGE 2: ADMIN — KELOLA BARANG  (data-page="kelola")
// ═════════════════════════════════════════════════════════════

let editingItemId = null;

function initKelolaPage() {
  initSidebar(); 
  loadKelolaStats(); 
  loadItemsTable(); 
  initItemForm(); 
  initAdminSearch();
}

async function loadKelolaStats() {
  const r = await apiGet(API.getDashboard);
  if (r.success && r.data && r.data.stats) {
    setText('statTotal', r.data.stats.total_unit);
    setText('statAvailable', r.data.stats.total_tersedia);
    setText('statBorrowed', r.data.stats.total_dipinjam);
    setText('statCategories', r.data.stats.total_kategori);
  }
}

async function loadItemsTable(q = '') {
  const tbody = document.getElementById('itemsTableBody'); 
  if (!tbody) return;

  tbody.innerHTML = '<tr><td colspan="6" class="text-center" style="padding:32px;color:var(--text-secondary);"><i class="bi bi-arrow-repeat" style="font-size:1.5rem;"></i><br>Memuat data inventaris...</td></tr>';

  let url = API.getBarang; 
  if (q) url += '?q=' + encodeURIComponent(q);

  const r = await apiGet(url);
  if (!r.success) { 
    tbody.innerHTML = `<tr><td colspan="6"><div class="empty-state"><i class="bi bi-exclamation-circle"></i><h4>Gagal memuat</h4><p>${r.message}</p></div></td></tr>`; 
    return; 
  }

  const items = r.data || [];
  if (!items.length) { 
    tbody.innerHTML = `<tr><td colspan="6"><div class="empty-state"><i class="bi bi-inbox"></i><h4>${q ? 'Barang tidak ditemukan' : 'Belum ada data barang'}</h4><p>${q ? 'Coba kata kunci pencarian lain.' : 'Klik tombol "Tambah Barang" untuk mencatat inventaris baru.'}</p></div></td></tr>`; 
    return; 
  }

  tbody.innerHTML = items.map((it, i) => {
    const av = it.stok_tersedia;
    const bc = av > 0 ? 'badge-available' : 'badge-empty';
    const bt = av > 0 ? 'Tersedia' : 'Habis';

    return `
      <tr>
        <td><strong>${i + 1}</strong></td>
        <td>
          <div class="d-flex align-center gap-2">
            <i class="bi ${it.ikon || 'bi-box'}" style="font-size:1.2rem;color:var(--primary);"></i>
            <div>
              <span>${escHtml(it.nama_barang)}</span>
              ${it.deskripsi ? `<br><small class="text-muted">${escHtml(it.deskripsi.substring(0, 50))}${it.deskripsi.length > 50 ? '...' : ''}</small>` : ''}
            </div>
          </div>
        </td>
        <td><span class="category-tag"><i class="bi ${getCategoryIcon(it.kategori)}"></i> ${escHtml(it.kategori)}</span></td>
        <td><strong>${av}</strong> / ${it.total_stok}</td>
        <td><span class="badge ${bc}"><span class="badge-dot"></span> ${bt}</span></td>
        <td>
          <div class="btn-group-actions">
            <button class="btn btn-outline btn-icon" title="Edit" onclick="editItem(${it.id})"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-outline btn-icon" title="Hapus" onclick="deleteItem(${it.id},'${escAttr(it.nama_barang)}')"><i class="bi bi-trash"></i></button>
          </div>
        </td>
      </tr>`;
  }).join('');
}

function initItemForm() {
  const openBtn   = document.getElementById('btnAddItem');
  const modal     = document.getElementById('itemModal');
  const closeBtn  = document.getElementById('itemModalClose');
  const cancelBtn = document.getElementById('itemModalCancel');
  const form      = document.getElementById('itemForm');

  if (!openBtn || !modal) return;

  openBtn.addEventListener('click', () => { 
    editingItemId = null; 
    document.getElementById('itemModalTitle').textContent = 'Tambah Barang Baru'; 
    document.getElementById('itemSubmitBtn').textContent = 'Simpan Barang'; 
    form.reset(); 
    clearFormErrors(form); 
    openModal('itemModal'); 
  });

  closeBtn.addEventListener('click', () => closeModal('itemModal'));
  cancelBtn.addEventListener('click', () => closeModal('itemModal'));
  modal.addEventListener('click', e => { if (e.target === modal) closeModal('itemModal'); });
  form.addEventListener('submit', e => { e.preventDefault(); handleItemSubmit(); });
}

async function handleItemSubmit() {
  const nama  = document.getElementById('inputName').value.trim();
  const kat   = document.getElementById('inputCategory').value;
  const stok  = parseInt(document.getElementById('inputStock').value, 10);
  const ikon  = document.getElementById('inputIcon').value;
  const desc  = document.getElementById('inputDesc')?.value.trim() || '';

  let v = true;
  if (!nama) { showFieldError('inputName', 'Nama barang wajib diisi.'); v = false; } else hideFieldError('inputName');
  if (!kat)  { showFieldError('inputCategory', 'Pilih kategori.'); v = false; } else hideFieldError('inputCategory');
  if (isNaN(stok) || stok < 1) { showFieldError('inputStock', 'Stok minimal 1.'); v = false; } else hideFieldError('inputStock');
  if (!v) return;

  const btn = document.getElementById('itemSubmitBtn'); 
  btn.disabled = true; 
  btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Menyimpan...';

  const payload = { nama_barang: nama, kategori: kat, total_stok: stok, ikon, deskripsi: desc };
  const r = editingItemId ? await apiPost(API.updateBarang, { id: editingItemId, ...payload }) : await apiPost(API.tambahBarang, payload);

  btn.disabled = false; 
  btn.textContent = editingItemId ? 'Perbarui Barang' : 'Simpan Barang';

  if (r.success) { 
    showToast(r.message, 'success'); 
    closeModal('itemModal'); 
    loadKelolaStats(); 
    loadItemsTable(); 
  } else {
    showToast(r.message, 'danger'); 
  }
}

async function editItem(id) {
  const r = await apiGet(API.getBarang + '?id=' + id); 
  if (!r.success || !r.data) { showToast('Gagal memuat data.', 'danger'); return; }

  const it = r.data; 
  editingItemId = it.id;

  document.getElementById('itemModalTitle').textContent = 'Edit Barang'; 
  document.getElementById('itemSubmitBtn').textContent = 'Perbarui Barang';
  document.getElementById('inputName').value = it.nama_barang; 
  document.getElementById('inputCategory').value = it.kategori;
  document.getElementById('inputStock').value = it.total_stok; 
  document.getElementById('inputIcon').value = it.ikon || '';
  const d = document.getElementById('inputDesc'); 
  if (d) d.value = it.deskripsi || '';

  clearFormErrors(document.getElementById('itemForm')); 
  openModal('itemModal');
}

function deleteItem(id, nama) {
  showConfirm('Hapus Barang?', `Yakin ingin menghapus <strong>"${nama}"</strong> dari database?`, async () => {
    const r = await apiPost(API.hapusBarang, { id }); 
    if (r.success) { 
      showToast(r.message, 'danger'); 
      loadKelolaStats(); 
      loadItemsTable(); 
    } else {
      showToast(r.message, 'warning'); 
    }
  });
}

function initAdminSearch() {
  const inp = document.getElementById('adminSearchInput'); 
  if (!inp) return; 
  let t;
  inp.addEventListener('input', () => { 
    clearTimeout(t); 
    t = setTimeout(() => loadItemsTable(inp.value.trim()), 300); 
  });
}


// ═════════════════════════════════════════════════════════════
//  PAGE 3: ADMIN — PEMINJAMAN (data-page="peminjaman")
// ═════════════════════════════════════════════════════════════

let currentLoanFilter = '';
let currentLoanSearch = '';
let allLoansData = [];

function initPeminjamanPage() {
  initSidebar(); 
  loadLoansData(); 
  initLoanFilterTabs(); 
  initLoanSearch();
}

async function loadLoansData() {
  const r = await apiGet(API.getPeminjaman);
  allLoansData = (r.success && r.data) ? r.data : [];
  updateLoanStats();
  renderLoansTable();
}

function updateLoanStats() {
  const total = allLoansData.length;
  const aktif = allLoansData.filter(l => l.status_pinjam === 'aktif').length;
  const kembali = allLoansData.filter(l => l.status_pinjam === 'dikembalikan').length;
  const terlambat = allLoansData.filter(l => l.status_pinjam === 'terlambat').length;
  setText('loanStatTotal', total);
  setText('loanStatAktif', aktif);
  setText('loanStatDikembalikan', kembali);
  setText('loanStatTerlambat', terlambat);
}

function renderLoansTable() {
  const tbody = document.getElementById('loansTableBody'); 
  if (!tbody) return;
  let data = [...allLoansData];

  if (currentLoanFilter) data = data.filter(l => l.status_pinjam === currentLoanFilter);

  if (currentLoanSearch) {
    const q = currentLoanSearch.toLowerCase();
    data = data.filter(l => l.nama_peminjam.toLowerCase().includes(q) || l.nama_barang.toLowerCase().includes(q));
  }

  if (!data.length) {
    tbody.innerHTML = `<tr><td colspan="8"><div class="empty-state"><i class="bi bi-clipboard-x"></i><h4>Tidak ada data peminjaman</h4><p>${currentLoanFilter || currentLoanSearch ? 'Coba ubah filter atau kata kunci.' : 'Belum ada peminjaman yang tercatat.'}</p></div></td></tr>`;
    return;
  }

  const SL = { 
    aktif:        { t: 'Sedang Dipinjam', c: 'badge-aktif' }, 
    dikembalikan: { t: 'Dikembalikan',    c: 'badge-dikembalikan' }, 
    terlambat:    { t: 'Terlambat',       c: 'badge-terlambat' } 
  };

  tbody.innerHTML = data.map((l, i) => {
    const s = SL[l.status_pinjam] || SL.aktif;
    const canReturn = l.status_pinjam === 'aktif';
    const initial = (l.nama_peminjam || 'A').charAt(0).toUpperCase();

    return `
      <tr>
        <td><strong>${i + 1}</strong></td>
        <td>
          <div class="borrower-pill">
            <div class="borrower-avatar">${initial}</div>
            <div><strong>${escHtml(l.nama_peminjam)}</strong></div>
          </div>
        </td>
        <td><div class="d-flex align-center gap-2"><i class="bi ${l.ikon || 'bi-box'}" style="color:var(--primary);"></i> ${escHtml(l.nama_barang)}</div></td>
        <td>${formatDate(l.tanggal_pinjam)}</td>
        <td>${formatDate(l.tanggal_kembali)}</td>
        <td>${l.durasi} hari</td>
        <td><span class="badge ${s.c}"><span class="badge-dot"></span> ${s.t}</span></td>
        <td>
          ${canReturn
            ? `<button class="btn btn-success btn-sm" onclick="returnItem(${l.id})"><i class="bi bi-box-arrow-in-left"></i> Kembalikan</button>`
            : `<span class="badge" style="background:#e8faf4;color:#42b893;font-size:0.75rem;"><i class="bi bi-check-all"></i> Selesai</span>`
          }
        </td>
      </tr>`;
  }).join('');
}

async function returnItem(loanId) {
  showConfirm('Kembalikan Barang?', 'Konfirmasi bahwa barang inventaris telah dikembalikan oleh peminjam ke himpunan.', async () => {
    const r = await apiPost(API.kembalikanBarang, { id: loanId });
    if (r.success) {
      showToast(r.message, 'success');
      loadLoansData();
    } else {
      showToast(r.message, 'danger');
    }
  });
}

function initLoanFilterTabs() {
  const tabs = document.getElementById('loanFilterTabs'); 
  if (!tabs) return;
  tabs.addEventListener('click', e => {
    if (!e.target.classList.contains('filter-chip')) return;
    tabs.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
    e.target.classList.add('active');
    currentLoanFilter = e.target.dataset.status;
    renderLoansTable();
  });
}

function initLoanSearch() {
  const inp = document.getElementById('loanSearchInput'); 
  if (!inp) return;
  let t;
  inp.addEventListener('input', () => { 
    clearTimeout(t); 
    t = setTimeout(() => { currentLoanSearch = inp.value.trim(); renderLoansTable(); }, 300); 
  });
}


// ═════════════════════════════════════════════════════════════
//  PAGE 4: ADMIN — MANAJEMEN AKUN (data-page="akun")
// ═════════════════════════════════════════════════════════════

let editingAkunId = null;
let currentAkunRoleFilter = '';

function initAkunPage() {
  initSidebar(); 
  loadAkunStats(); 

  // Cek apakah ada query param role (misal dari bookmark lama)
  const urlParams = new URLSearchParams(window.location.search);
  const roleParam = urlParams.get('role');
  if (roleParam) {
    switchAkunTab(roleParam);
  } else {
    loadAkunTable();
  }

  initAkunForm(); 
  initAkunSearch();
  initAkunFilterTabs();
}

// Global Tab Switcher untuk Akun (bisa dipanggil dari Tab maupun Stat Card)
window.switchAkunTab = function(role) {
  currentAkunRoleFilter = role || '';
  const tabs = document.getElementById('akunFilterTabs');
  if (tabs) {
    tabs.querySelectorAll('.filter-chip').forEach(c => {
      if ((c.dataset.role || '') === (role || '')) {
        c.classList.add('active');
      } else {
        c.classList.remove('active');
      }
    });
  }
  loadAkunTable();
};

async function loadAkunStats() {
  const r = await apiGet(API.getAkun);
  if (!r.success) return;
  const data = r.data || [];
  setText('akunStatTotal', data.length);
  setText('akunStatAdmin', data.filter(a => a.role === 'admin').length);
  setText('akunStatUser', data.filter(a => a.role === 'user').length);
  setText('akunStatNonaktif', data.filter(a => a.status === 'nonaktif').length);
}

async function loadAkunTable(q = '') {
  const tbody = document.getElementById('akunTableBody'); 
  if (!tbody) return;

  tbody.innerHTML = '<tr><td colspan="7" class="text-center" style="padding:32px;color:var(--text-secondary);"><i class="bi bi-arrow-repeat" style="font-size:1.5rem;"></i><br>Memuat data akun...</td></tr>';

  let url = API.getAkun;
  const params = [];
  if (q) params.push('q=' + encodeURIComponent(q));
  if (currentAkunRoleFilter) params.push('role=' + encodeURIComponent(currentAkunRoleFilter));
  if (params.length) url += '?' + params.join('&');

  const r = await apiGet(url);
  if (!r.success) { 
    tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state"><i class="bi bi-exclamation-circle"></i><h4>Gagal memuat</h4><p>${r.message}</p></div></td></tr>`; 
    return; 
  }

  const data = r.data || [];
  if (!data.length) { 
    tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><i class="bi bi-people"></i><h4>Tidak ada akun ditemukan</h4><p>Silakan sesuaikan filter atau tambahkan akun baru.</p></div></td></tr>'; 
    return; 
  }

  tbody.innerHTML = data.map((a, i) => {
    const roleBadge = a.role === 'admin' ? 'badge-role-admin' : 'badge-role-user';
    const statusBadge = a.status === 'aktif' ? 'badge-available' : 'badge-empty';
    const roleLabel = a.role === 'admin' ? 'Pengurus / Admin' : 'Anggota / Kader';

    return `
      <tr>
        <td><strong>${i + 1}</strong></td>
        <td>
          <div class="d-flex align-center gap-2">
            <div class="borrower-avatar">${escHtml(a.nama_lengkap.charAt(0).toUpperCase())}</div>
            <div>
              <strong>${escHtml(a.nama_lengkap)}</strong>
            </div>
          </div>
        </td>
        <td><code style="font-size:0.82rem;">${escHtml(a.username)}</code></td>
        <td>${a.email ? escHtml(a.email) : '<span class="text-muted">—</span>'}</td>
        <td><span class="badge ${roleBadge}"><i class="bi ${a.role === 'admin' ? 'bi-shield-lock-fill' : 'bi-people-fill'}"></i> ${roleLabel}</span></td>
        <td><span class="badge ${statusBadge}"><span class="badge-dot"></span> ${a.status === 'aktif' ? 'Aktif' : 'Nonaktif'}</span></td>
        <td>
          <div class="btn-group-actions">
            <button class="btn btn-outline btn-icon" title="Edit" onclick="editAkun(${a.id})"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-outline btn-icon" title="Hapus" onclick="deleteAkun(${a.id},'${escAttr(a.nama_lengkap)}')"><i class="bi bi-trash"></i></button>
          </div>
        </td>
      </tr>`;
  }).join('');
}

function initAkunFilterTabs() {
  const tabs = document.getElementById('akunFilterTabs');
  if (!tabs) return;

  tabs.addEventListener('click', e => {
    const chip = e.target.closest('.filter-chip');
    if (!chip) return;
    const role = chip.dataset.role || '';
    switchAkunTab(role);
  });
}

window.toggleAkunPassword = function(inpId, iconId) {
  const inp = document.getElementById(inpId);
  const icon = document.getElementById(iconId);
  if (!inp || !icon) return;
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    inp.type = 'password';
    icon.className = 'bi bi-eye';
  }
};

function resetAkunPasswordFields() {
  const pass = document.getElementById('akunPassword');
  const passConf = document.getElementById('akunPasswordConfirm');
  const eye1 = document.getElementById('eyeAkunPass');
  const eye2 = document.getElementById('eyeAkunPassConfirm');
  if (pass) { pass.value = ''; pass.type = 'password'; }
  if (passConf) { passConf.value = ''; passConf.type = 'password'; }
  if (eye1) eye1.className = 'bi bi-eye';
  if (eye2) eye2.className = 'bi bi-eye';
}

function initAkunForm() {
  const openBtn   = document.getElementById('btnAddAkun');
  const modal     = document.getElementById('akunModal');
  const closeBtn  = document.getElementById('akunModalClose');
  const cancelBtn = document.getElementById('akunModalCancel');
  const form      = document.getElementById('akunForm');

  if (!openBtn || !modal) return;

  openBtn.addEventListener('click', () => { 
    editingAkunId = null; 
    document.getElementById('akunModalTitle').innerHTML = '<i class="bi bi-person-plus-fill" style="color:var(--primary);"></i>&nbsp; Tambah Akun Baru'; 
    document.getElementById('akunSubmitBtn').innerHTML = '<i class="bi bi-check2"></i> Simpan Akun'; 
    const hint = document.getElementById('akunPassHintText');
    if (hint) hint.textContent = 'Default: admin123 (untuk Admin) atau user123 (untuk User) jika dikosongkan.';

    form.reset(); 
    resetAkunPasswordFields();
    clearFormErrors(form); 
    openModal('akunModal'); 
  });

  if (closeBtn) closeBtn.addEventListener('click', () => closeModal('akunModal'));
  if (cancelBtn) cancelBtn.addEventListener('click', () => closeModal('akunModal'));
  modal.addEventListener('click', e => { if (e.target === modal) closeModal('akunModal'); });
  form.addEventListener('submit', e => { e.preventDefault(); handleAkunSubmit(); });
}

async function handleAkunSubmit() {
  const nama     = document.getElementById('akunNama').value.trim();
  const username = document.getElementById('akunUsername').value.trim();
  const email    = document.getElementById('akunEmail').value.trim();
  const role     = document.getElementById('akunRole').value;
  const status   = document.getElementById('akunStatus').value;
  const pass     = document.getElementById('akunPassword')?.value.trim() || '';
  const passConf = document.getElementById('akunPasswordConfirm')?.value.trim() || '';

  let v = true;
  if (!nama) { showFieldError('akunNama', 'Nama lengkap wajib diisi.'); v = false; } else hideFieldError('akunNama');
  if (!username || username.length < 3) { showFieldError('akunUsername', 'Username minimal 3 karakter.'); v = false; } else hideFieldError('akunUsername');

  // Validasi password dan konfirmasi password
  if (pass || passConf) {
    if (!pass || pass.length < 5) {
      showFieldError('akunPassword', 'Password baru minimal 5 karakter.');
      v = false;
    } else {
      hideFieldError('akunPassword');
    }

    if (!passConf) {
      showFieldError('akunPasswordConfirm', 'Konfirmasi password wajib diisi.');
      v = false;
    } else if (pass !== passConf) {
      showFieldError('akunPasswordConfirm', 'Konfirmasi password tidak cocok dengan password baru!');
      v = false;
    } else {
      hideFieldError('akunPasswordConfirm');
    }
  } else {
    hideFieldError('akunPassword');
    hideFieldError('akunPasswordConfirm');
  }

  if (!v) return;

  const btn = document.getElementById('akunSubmitBtn'); 
  btn.disabled = true; 
  btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Menyimpan...';

  const payload = { nama_lengkap: nama, username, email, role, status };
  if (pass) {
    payload.password = pass;
  }

  const r = editingAkunId ? await apiPost(API.updateAkun, { id: editingAkunId, ...payload }) : await apiPost(API.tambahAkun, payload);

  btn.disabled = false; 
  btn.innerHTML = editingAkunId ? '<i class="bi bi-check2"></i> Perbarui Akun' : '<i class="bi bi-check2"></i> Simpan Akun';

  if (r.success) { 
    showToast(r.message, 'success'); 
    closeModal('akunModal'); 
    loadAkunStats(); 
    loadAkunTable(); 
  } else {
    showToast(r.message, 'danger'); 
  }
}

async function editAkun(id) {
  const r = await apiGet(API.getAkun + '?id=' + id); 
  if (!r.success || !r.data) { showToast('Gagal memuat data akun.', 'danger'); return; }

  const a = r.data; 
  editingAkunId = a.id;

  document.getElementById('akunModalTitle').innerHTML = '<i class="bi bi-person-gear" style="color:var(--primary);"></i>&nbsp; Edit Akun: ' + escHtml(a.nama_lengkap); 
  document.getElementById('akunSubmitBtn').innerHTML = '<i class="bi bi-check2"></i> Perbarui Akun'; 
  const hint = document.getElementById('akunPassHintText');
  if (hint) hint.textContent = 'Kosongkan kedua kolom jika tidak ingin mengubah password akun ini.';

  document.getElementById('akunNama').value = a.nama_lengkap; 
  document.getElementById('akunUsername').value = a.username;
  document.getElementById('akunEmail').value = a.email || ''; 
  document.getElementById('akunRole').value = a.role;
  document.getElementById('akunStatus').value = a.status;

  resetAkunPasswordFields();
  clearFormErrors(document.getElementById('akunForm')); 
  openModal('akunModal');
}

function deleteAkun(id, nama) {
  showConfirm('Hapus Akun?', `Yakin ingin menghapus akun <strong>"${nama}"</strong>?`, async () => {
    const r = await apiPost(API.hapusAkun, { id }); 
    if (r.success) { 
      showToast(r.message, 'danger'); 
      loadAkunStats(); 
      loadAkunTable(); 
    } else {
      showToast(r.message, 'warning'); 
    }
  });
}

function initAkunSearch() {
  const inp = document.getElementById('akunSearchInput'); 
  if (!inp) return; 
  let t;
  inp.addEventListener('input', () => { 
    clearTimeout(t); 
    t = setTimeout(() => loadAkunTable(inp.value.trim()), 300); 
  });
}


// ═════════════════════════════════════════════════════════════
//  PAGE 5: USER — KATALOG BARANG
// ═════════════════════════════════════════════════════════════

let currentCatalogSearch = '', currentCategoryFilter = 'Semua';

function initUserPage() {
  initSidebar(); 
  updateSidebarUser();
  loadUserStats(); 
  loadCatalog(); 
  initCatalogSearch(); 
  loadCategoryFilters(); 
  initLoanForm();
  loadMyLoans();
}

async function loadMyLoans() {
  const tbody = document.getElementById('myLoansTableBody');
  if (!tbody) return;

  const userJson = localStorage.getItem('invento_user');
  if (!userJson) return;
  let u;
  try {
    u = JSON.parse(userJson);
  } catch (e) {
    return;
  }

  try {
    const r = await apiGet('api/get_peminjaman.php?peminjam=' + encodeURIComponent(u.nama_lengkap));
    if (!r.success || !r.data || !r.data.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="7" class="text-center" style="padding:32px;color:var(--text-secondary);">
            <i class="bi bi-inbox" style="font-size:2rem;color:var(--text-muted);display:block;margin-bottom:8px;"></i>
            Belum ada riwayat peminjaman untuk akun <strong>${escHtml(u.nama_lengkap)}</strong>.
          </td>
        </tr>`;
      return;
    }

    tbody.innerHTML = r.data.map((item, idx) => {
      let badgeClass = 'badge-warning';
      let badgeIcon = 'bi-clock';
      let badgeLabel = 'Sedang Dipinjam';

      if (item.status_pinjam === 'kembali' || item.status_pinjam === 'dikembalikan') {
        badgeClass = 'badge-success';
        badgeIcon = 'bi-check-circle-fill';
        badgeLabel = 'Sudah Dikembalikan';
      } else if (item.status_pinjam === 'terlambat') {
        badgeClass = 'badge-danger';
        badgeIcon = 'bi-exclamation-triangle-fill';
        badgeLabel = 'Terlambat';
      }

      return `
        <tr>
          <td class="text-center">${idx + 1}</td>
          <td><strong>${escHtml(item.nama_barang)}</strong></td>
          <td><span class="badge badge-primary">${escHtml(item.kategori || 'Inventaris')}</span></td>
          <td>${formatDate(item.tanggal_pinjam)}</td>
          <td>${formatDate(item.tanggal_kembali)}</td>
          <td>${item.durasi} Hari</td>
          <td style="text-align:center;">
            <span class="badge ${badgeClass}"><i class="bi ${badgeIcon}"></i> ${badgeLabel}</span>
          </td>
        </tr>`;
    }).join('');
  } catch (err) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" class="text-center" style="padding:20px;color:var(--danger);">
          Gagal memuat riwayat peminjaman.
        </td>
      </tr>`;
  }
}

async function loadUserStats() {
  const r = await apiGet(API.getDashboard);
  if (r.success && r.data && r.data.stats) {
    setText('userStatTotal', r.data.stats.total_unit);
    setText('userStatAvailable', r.data.stats.total_tersedia);
    setText('userStatBorrowed', r.data.stats.total_dipinjam);
  }
}

async function loadCatalog() {
  const grid = document.getElementById('catalogGrid'); 
  if (!grid) return;

  grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1;"><i class="bi bi-arrow-repeat" style="font-size:2rem;"></i><p>Memuat katalog inventaris...</p></div>';

  let url = API.getBarang + '?';
  if (currentCatalogSearch) url += 'q=' + encodeURIComponent(currentCatalogSearch) + '&';
  if (currentCategoryFilter !== 'Semua') url += 'kategori=' + encodeURIComponent(currentCategoryFilter);

  const r = await apiGet(url);
  if (!r.success) {
    grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1;"><i class="bi bi-exclamation-circle"></i><h4>Gagal memuat</h4><p>${r.message}</p></div>`;
    return;
  }

  const items = r.data || [];
  if (!items.length) {
    grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1;"><i class="bi bi-search"></i><h4>Barang tidak ditemukan</h4><p>Coba ubah kata kunci atau filter kategori.</p></div>';
    return;
  }

  grid.innerHTML = items.map(it => {
    const av = it.stok_tersedia;
    const cb = av > 0;
    return `
      <div class="catalog-card">
        <div class="card-img"><i class="bi ${it.ikon || 'bi-box'}"></i></div>
        <div class="card-content">
          <div class="card-category"><i class="bi ${getCategoryIcon(it.kategori)}"></i> ${escHtml(it.kategori)}</div>
          <h4>${escHtml(it.nama_barang)}</h4>
          ${it.deskripsi ? `<p style="font-size:0.8rem;color:var(--text-secondary);margin-top:4px;">${escHtml(it.deskripsi)}</p>` : ''}
          <div class="card-meta">
            <div class="stock-info">Tersedia: <strong>${av}</strong> / ${it.total_stok}</div>
            <button class="btn btn-sm ${cb ? 'btn-primary' : 'btn-outline'}" ${cb ? `onclick="openLoanForm(${it.id})"` : 'disabled'} style="${cb ? '' : 'opacity:0.5;cursor:not-allowed;'}">
              <i class="bi ${cb ? 'bi-arrow-right-circle' : 'bi-x-circle'}"></i> ${cb ? 'Pinjam' : 'Habis'}
            </button>
          </div>
        </div>
      </div>`;
  }).join('');
}

function initCatalogSearch() {
  const inp = document.getElementById('catalogSearchInput');
  if (!inp) return;
  let t;
  inp.addEventListener('input', () => { 
    clearTimeout(t); 
    t = setTimeout(() => { currentCatalogSearch = inp.value.trim(); loadCatalog(); }, 300); 
  });
}

async function loadCategoryFilters() {
  const c = document.getElementById('categoryFilters');
  if (!c) return;

  const r = await apiGet(API.getBarang);
  if (!r.success) return;

  const cats = ['Semua', ...new Set((r.data || []).map(i => i.kategori))];
  c.innerHTML = cats.map(cat => `<button class="filter-chip ${cat === 'Semua' ? 'active' : ''}" data-category="${escAttr(cat)}">${escHtml(cat)}</button>`).join('');

  c.addEventListener('click', e => {
    if (!e.target.classList.contains('filter-chip')) return;
    c.querySelectorAll('.filter-chip').forEach(x => x.classList.remove('active'));
    e.target.classList.add('active');
    currentCategoryFilter = e.target.dataset.category;
    loadCatalog();
  });
}

function initLoanForm() {
  const modal     = document.getElementById('loanModal');
  const closeBtn  = document.getElementById('loanModalClose');
  const cancelBtn = document.getElementById('loanModalCancel');
  const form      = document.getElementById('loanForm');
  if (!modal) return;

  closeBtn.addEventListener('click', () => closeModal('loanModal'));
  cancelBtn.addEventListener('click', () => closeModal('loanModal'));
  modal.addEventListener('click', e => { if (e.target === modal) closeModal('loanModal'); });

  const di = document.getElementById('loanDate');
  if (di) di.value = new Date().toISOString().split('T')[0];

  form.addEventListener('submit', e => { e.preventDefault(); handleLoanSubmit(); });
}

async function populateItemSelect() {
  const sel = document.getElementById('loanItemSelect');
  if (!sel) return;
  const r = await apiGet(API.getBarang);
  if (!r.success) return;
  const avail = (r.data || []).filter(i => i.stok_tersedia > 0);
  sel.innerHTML = '<option value="">-- Pilih Barang Inventaris --</option>' +
    avail.map(i => `<option value="${i.id}">${escHtml(i.nama_barang)} (tersedia: ${i.stok_tersedia})</option>`).join('');
}

async function openLoanForm(itemId) {
  await populateItemSelect();
  const sel = document.getElementById('loanItemSelect');
  if (sel) sel.value = itemId;
  const di = document.getElementById('loanDate');
  if (di) di.value = new Date().toISOString().split('T')[0];

  // Auto-fill logged in user name
  const userJson = localStorage.getItem('invento_user');
  if (userJson) {
    try {
      const u = JSON.parse(userJson);
      const borrowerInput = document.getElementById('loanBorrower');
      if (borrowerInput && u.nama_lengkap) {
        borrowerInput.value = u.nama_lengkap;
      }
    } catch(e) {}
  }

  clearFormErrors(document.getElementById('loanForm'));
  openModal('loanModal');
}

async function handleLoanSubmit() {
  const idB = parseInt(document.getElementById('loanItemSelect').value, 10);
  const nm  = document.getElementById('loanBorrower').value.trim();
  const dt  = document.getElementById('loanDate').value;
  const dur = parseInt(document.getElementById('loanDuration').value, 10);

  let v = true;
  if (!idB) { showFieldError('loanItemSelect', 'Pilih barang.'); v = false; } else hideFieldError('loanItemSelect');
  if (!nm || nm.length < 3) { showFieldError('loanBorrower', 'Nama minimal 3 karakter.'); v = false; } else hideFieldError('loanBorrower');
  if (!dt) { showFieldError('loanDate', 'Tanggal pinjam wajib diisi.'); v = false; } else hideFieldError('loanDate');
  if (isNaN(dur) || dur < 1) { showFieldError('loanDuration', 'Durasi minimal 1 hari.'); v = false; } 
  else if (dur > 30) { showFieldError('loanDuration', 'Durasi maksimal 30 hari.'); v = false; } 
  else hideFieldError('loanDuration');
  if (!v) return;

  const btn = document.querySelector('#loanForm button[type="submit"]');
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Memproses...'; }

  const r = await apiPost(API.tambahPeminjaman, { id_barang: idB, nama_peminjam: nm, tanggal_pinjam: dt, durasi: dur });
  if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-send"></i> Ajukan Peminjaman'; }

  if (r.success) {
    showToast(r.message, 'success');
    closeModal('loanModal');
    document.getElementById('loanForm').reset();
    loadCatalog();
    loadUserStats();
    if (typeof loadMyLoans === 'function') loadMyLoans();
  } else {
    showToast(r.message, 'danger');
  }
}


// ═════════════════════════════════════════════════════════════
//  AUTH GUARD & INIT ROUTER
// ═════════════════════════════════════════════════════════════

function checkAuthGuard() {
  const body = document.body;
  const isLoginPage = body.classList.contains('login-body');
  const isAdminPage = body.id === 'adminPage';
  const isUserPage = body.id === 'userPage';

  const userJson = localStorage.getItem('invento_user');
  let user = null;
  try {
    user = userJson ? JSON.parse(userJson) : null;
  } catch (e) {
    user = null;
  }

  // Jika di halaman login tapi sudah memiliki sesi login aktif
  if (isLoginPage) {
    if (user && user.role) {
      window.location.replace(user.role === 'admin' ? 'admin.html' : 'user.html');
    }
    return;
  }

  // Jika mencoba membuka halaman admin tanpa login atau role bukan admin
  if (isAdminPage) {
    if (!user) {
      window.location.replace('login.php');
      return;
    }
    if (user.role !== 'admin') {
      window.location.replace('user_katalog.php');
      return;
    }
  }

  // Jika mencoba membuka portal peminjam tanpa login
  if (isUserPage) {
    if (!user) {
      window.location.replace('login.php');
      return;
    }
  }
}

document.addEventListener('DOMContentLoaded', () => {
  // Jalankan pemeriksaan akses wajib login
  checkAuthGuard();

  const body = document.body;
  const page = body.dataset.page;

  updateSidebarUser();

  if (body.id === 'adminPage') {
    switch (page) {
      case 'dashboard':  initDashboardPage();  break;
      case 'kelola':     initKelolaPage();     break;
      case 'peminjaman': initPeminjamanPage(); break;
      case 'akun':       initAkunPage();       break;
    }
  }
  if (body.id === 'userPage') initUserPage();
});
