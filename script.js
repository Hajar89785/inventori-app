/* ============================================
   INVENTO - script.js
   Logika Aplikasi (Terhubung ke MySQL via PHP API)
   ============================================ */

// ─── API Base URL ────────────────────────────────────────
const API = {
  getBarang:         'api/get_barang.php',
  tambahBarang:      'api/tambah_barang.php',
  updateBarang:      'api/update_barang.php',
  hapusBarang:       'api/hapus_barang.php',
  getDashboard:      'api/get_dashboard.php',
  getPeminjaman:     'api/get_peminjaman.php',
  tambahPeminjaman:  'api/tambah_peminjaman.php',
};

// ─── Fetch Helper ────────────────────────────────────────
async function apiFetch(url, options = {}) {
  try {
    const res = await fetch(url, {
      headers: { 'Content-Type': 'application/json' },
      ...options,
    });
    const json = await res.json();
    return json;
  } catch (err) {
    console.error('API Error:', err);
    return { success: false, message: 'Gagal menghubungi server. Pastikan Laragon aktif.' };
  }
}

async function apiGet(url) {
  return apiFetch(url);
}

async function apiPost(url, body) {
  return apiFetch(url, {
    method: 'POST',
    body: JSON.stringify(body),
  });
}

// ─── Category Icon Mapping ──────────────────────────────
const CATEGORY_ICONS = {
  'Elektronik':   'bi-cpu',
  'Furnitur':     'bi-lamp',
  'Perlengkapan': 'bi-box-seam',
  'Outdoor':      'bi-tree',
  'Kendaraan':    'bi-truck',
  'Olahraga':     'bi-dribbble',
  'Lainnya':      'bi-archive',
};

function getCategoryIcon(cat) {
  return CATEGORY_ICONS[cat] || 'bi-tag';
}

// ─── Toast Notification ─────────────────────────────────
function showToast(message, type = 'success') {
  let container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  const iconMap = {
    success: 'bi-check-circle-fill',
    danger:  'bi-x-circle-fill',
    warning: 'bi-exclamation-triangle-fill',
  };

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `
    <i class="bi ${iconMap[type] || iconMap.success} toast-icon"></i>
    <span>${message}</span>
    <button class="toast-close" onclick="this.parentElement.classList.add('toast-hide'); setTimeout(() => this.parentElement.remove(), 300);">&times;</button>
  `;
  container.appendChild(toast);

  setTimeout(() => {
    toast.classList.add('toast-hide');
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}

// ─── Confirm Dialog ─────────────────────────────────────
function showConfirm(title, message, onConfirm) {
  let overlay = document.getElementById('confirmOverlay');
  if (overlay) overlay.remove();

  overlay = document.createElement('div');
  overlay.id = 'confirmOverlay';
  overlay.className = 'confirm-overlay';
  overlay.innerHTML = `
    <div class="confirm-box">
      <div class="confirm-icon"><i class="bi bi-exclamation-triangle"></i></div>
      <h4>${title}</h4>
      <p>${message}</p>
      <div class="confirm-actions">
        <button class="btn btn-outline" id="confirmCancel">Batal</button>
        <button class="btn btn-danger" id="confirmOk">Ya, Hapus</button>
      </div>
    </div>
  `;
  document.body.appendChild(overlay);

  requestAnimationFrame(() => overlay.classList.add('active'));

  document.getElementById('confirmCancel').onclick = () => {
    overlay.classList.remove('active');
    setTimeout(() => overlay.remove(), 300);
  };
  document.getElementById('confirmOk').onclick = () => {
    overlay.classList.remove('active');
    setTimeout(() => overlay.remove(), 300);
    onConfirm();
  };
}

// ─── Sidebar Toggle (Responsive) ────────────────────────
function initSidebar() {
  const toggleBtn = document.getElementById('sidebarToggle');
  const sidebar   = document.getElementById('sidebar');
  const backdrop  = document.getElementById('sidebarBackdrop');

  if (!toggleBtn || !sidebar) return;

  toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    if (backdrop) backdrop.classList.toggle('active');
  });

  if (backdrop) {
    backdrop.addEventListener('click', () => {
      sidebar.classList.remove('open');
      backdrop.classList.remove('active');
    });
  }
}

// ─── Modal Utility ──────────────────────────────────────
function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
  }
}

// ─── Form Validation Helpers ────────────────────────────
function showFieldError(fieldId, message) {
  const field   = document.getElementById(fieldId);
  const errorEl = field ? field.parentElement.querySelector('.form-error') : null;
  if (errorEl) {
    errorEl.textContent = message;
    errorEl.classList.add('show');
  }
  if (field) field.style.borderColor = 'var(--danger)';
}

function hideFieldError(fieldId) {
  const field   = document.getElementById(fieldId);
  const errorEl = field ? field.parentElement.querySelector('.form-error') : null;
  if (errorEl) errorEl.classList.remove('show');
  if (field) field.style.borderColor = '';
}

function clearFormErrors(form) {
  if (!form) return;
  form.querySelectorAll('.form-error').forEach(el => el.classList.remove('show'));
  form.querySelectorAll('.form-control').forEach(el => el.style.borderColor = '');
}

function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}


// ═══════════════════════════════════════════════════════════
//  ADMIN PAGE
// ═══════════════════════════════════════════════════════════

function initAdminPage() {
  initSidebar();
  loadDashboard();
  loadItemsTable();
  initItemForm();
  initAdminSearch();
}

// ─── Dashboard Stats ────────────────────────────────────
async function loadDashboard() {
  const res = await apiGet(API.getDashboard);
  if (res.success && res.data) {
    setText('statTotal',      res.data.total_unit);
    setText('statAvailable',  res.data.total_tersedia);
    setText('statBorrowed',   res.data.total_dipinjam);
    setText('statCategories', res.data.total_kategori);
  }
}

// ─── Items Table ────────────────────────────────────────
async function loadItemsTable(searchQuery = '') {
  const tbody = document.getElementById('itemsTableBody');
  if (!tbody) return;

  // Loading state
  tbody.innerHTML = `
    <tr><td colspan="6" class="text-center" style="padding:32px; color:var(--text-secondary);">
      <i class="bi bi-arrow-repeat" style="font-size:1.5rem;"></i><br>Memuat data...
    </td></tr>`;

  let url = API.getBarang;
  if (searchQuery) url += '?q=' + encodeURIComponent(searchQuery);

  const res = await apiGet(url);

  if (!res.success) {
    tbody.innerHTML = `
      <tr><td colspan="6">
        <div class="empty-state">
          <i class="bi bi-exclamation-circle"></i>
          <h4>Gagal memuat data</h4>
          <p>${res.message}</p>
        </div>
      </td></tr>`;
    return;
  }

  const items = res.data || [];

  if (items.length === 0) {
    tbody.innerHTML = `
      <tr><td colspan="6">
        <div class="empty-state">
          <i class="bi bi-inbox"></i>
          <h4>${searchQuery ? 'Barang tidak ditemukan' : 'Belum ada data barang'}</h4>
          <p>${searchQuery ? 'Coba ubah kata kunci pencarian Anda.' : 'Klik tombol "Tambah Barang" untuk menambahkan data.'}</p>
        </div>
      </td></tr>`;
    return;
  }

  tbody.innerHTML = items.map((item, idx) => {
    const available = item.stok_tersedia;
    const badgeClass = available > 0 ? 'badge-available' : 'badge-empty';
    const badgeText  = available > 0 ? 'Tersedia' : 'Habis';

    return `
      <tr>
        <td><strong>${idx + 1}</strong></td>
        <td>
          <div class="d-flex align-center gap-2">
            <i class="bi ${item.ikon || 'bi-box'}" style="font-size:1.2rem; color:var(--primary);"></i>
            <span>${escHtml(item.nama_barang)}</span>
          </div>
        </td>
        <td><span class="category-tag"><i class="bi ${getCategoryIcon(item.kategori)}"></i> ${escHtml(item.kategori)}</span></td>
        <td><strong>${available}</strong> / ${item.total_stok}</td>
        <td>
          <span class="badge ${badgeClass}">
            <span class="badge-dot"></span>
            ${badgeText}
          </span>
        </td>
        <td>
          <div class="btn-group-actions">
            <button class="btn btn-outline btn-icon" title="Edit" onclick="editItem(${item.id})">
              <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-outline btn-icon" title="Hapus" onclick="deleteItem(${item.id}, '${escAttr(item.nama_barang)}')">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </td>
      </tr>`;
  }).join('');
}

// ─── Add / Edit Item Modal ──────────────────────────────
let editingItemId = null;

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
    document.getElementById('itemSubmitBtn').textContent  = 'Simpan Barang';
    form.reset();
    clearFormErrors(form);
    openModal('itemModal');
  });

  closeBtn.addEventListener('click',  () => closeModal('itemModal'));
  cancelBtn.addEventListener('click', () => closeModal('itemModal'));
  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeModal('itemModal');
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    handleItemSubmit();
  });
}

async function handleItemSubmit() {
  const nama      = document.getElementById('inputName').value.trim();
  const kategori  = document.getElementById('inputCategory').value;
  const totalStok = parseInt(document.getElementById('inputStock').value, 10);
  const ikon      = document.getElementById('inputIcon').value;
  const deskripsi = document.getElementById('inputDesc')?.value.trim() || '';

  // ── Client-side validation ──
  let valid = true;

  if (!nama) { showFieldError('inputName', 'Nama barang wajib diisi.'); valid = false; }
  else { hideFieldError('inputName'); }

  if (!kategori) { showFieldError('inputCategory', 'Pilih kategori barang.'); valid = false; }
  else { hideFieldError('inputCategory'); }

  if (isNaN(totalStok) || totalStok < 1) { showFieldError('inputStock', 'Jumlah stok minimal 1.'); valid = false; }
  else { hideFieldError('inputStock'); }

  if (!valid) return;

  // Disable submit button
  const submitBtn = document.getElementById('itemSubmitBtn');
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Menyimpan...';

  let res;

  if (editingItemId) {
    // Update
    res = await apiPost(API.updateBarang, {
      id: editingItemId,
      nama_barang: nama,
      kategori,
      total_stok: totalStok,
      ikon,
      deskripsi,
    });
  } else {
    // Create
    res = await apiPost(API.tambahBarang, {
      nama_barang: nama,
      kategori,
      total_stok: totalStok,
      ikon,
      deskripsi,
    });
  }

  // Re-enable button
  submitBtn.disabled = false;
  submitBtn.textContent = editingItemId ? 'Perbarui Barang' : 'Simpan Barang';

  if (res.success) {
    showToast(res.message, 'success');
    closeModal('itemModal');
    loadDashboard();
    loadItemsTable();
  } else {
    showToast(res.message, 'danger');
  }
}

async function editItem(id) {
  const res = await apiGet(API.getBarang + '?id=' + id);
  if (!res.success || !res.data) {
    showToast('Gagal memuat data barang.', 'danger');
    return;
  }

  const item = res.data;
  editingItemId = item.id;

  document.getElementById('itemModalTitle').textContent = 'Edit Barang';
  document.getElementById('itemSubmitBtn').textContent  = 'Perbarui Barang';
  document.getElementById('inputName').value     = item.nama_barang;
  document.getElementById('inputCategory').value = item.kategori;
  document.getElementById('inputStock').value    = item.total_stok;
  document.getElementById('inputIcon').value     = item.ikon || '';
  const descEl = document.getElementById('inputDesc');
  if (descEl) descEl.value = item.deskripsi || '';

  clearFormErrors(document.getElementById('itemForm'));
  openModal('itemModal');
}

function deleteItem(id, nama) {
  showConfirm(
    'Hapus Barang?',
    `Apakah Anda yakin ingin menghapus <strong>"${nama}"</strong>? Tindakan ini tidak dapat dibatalkan.`,
    async () => {
      const res = await apiPost(API.hapusBarang, { id });
      if (res.success) {
        showToast(res.message, 'danger');
        loadDashboard();
        loadItemsTable();
      } else {
        showToast(res.message, 'warning');
      }
    }
  );
}

// ─── Admin Search ───────────────────────────────────────
function initAdminSearch() {
  const input = document.getElementById('adminSearchInput');
  if (!input) return;

  let debounceTimer;
  input.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      loadItemsTable(input.value.trim());
    }, 300);
  });
}


// ═══════════════════════════════════════════════════════════
//  USER / CATALOG PAGE
// ═══════════════════════════════════════════════════════════

let currentCatalogSearch   = '';
let currentCategoryFilter  = 'Semua';

function initUserPage() {
  initSidebar();
  loadUserStats();
  loadCatalog();
  initCatalogSearch();
  loadCategoryFilters();
  initLoanForm();
}

// ─── User Stats ─────────────────────────────────────────
async function loadUserStats() {
  const res = await apiGet(API.getDashboard);
  if (res.success && res.data) {
    setText('userStatTotal',     res.data.total_unit);
    setText('userStatAvailable', res.data.total_tersedia);
    setText('userStatBorrowed',  res.data.total_dipinjam);
  }
}

// ─── Catalog Grid ───────────────────────────────────────
async function loadCatalog() {
  const grid = document.getElementById('catalogGrid');
  if (!grid) return;

  // Loading
  grid.innerHTML = `
    <div class="empty-state" style="grid-column:1/-1;">
      <i class="bi bi-arrow-repeat" style="font-size:2rem;"></i>
      <p>Memuat katalog...</p>
    </div>`;

  let url = API.getBarang + '?';
  if (currentCatalogSearch)  url += 'q=' + encodeURIComponent(currentCatalogSearch) + '&';
  if (currentCategoryFilter !== 'Semua') url += 'kategori=' + encodeURIComponent(currentCategoryFilter);

  const res = await apiGet(url);

  if (!res.success) {
    grid.innerHTML = `
      <div class="empty-state" style="grid-column:1/-1;">
        <i class="bi bi-exclamation-circle"></i>
        <h4>Gagal memuat katalog</h4>
        <p>${res.message}</p>
      </div>`;
    return;
  }

  const items = res.data || [];

  if (items.length === 0) {
    grid.innerHTML = `
      <div class="empty-state" style="grid-column:1/-1;">
        <i class="bi bi-search"></i>
        <h4>Barang tidak ditemukan</h4>
        <p>Coba ubah kata kunci atau filter kategori Anda.</p>
      </div>`;
    return;
  }

  grid.innerHTML = items.map(item => {
    const available = item.stok_tersedia;
    const canBorrow = available > 0;

    return `
      <div class="catalog-card">
        <div class="card-img">
          <i class="bi ${item.ikon || 'bi-box'}"></i>
        </div>
        <div class="card-content">
          <div class="card-category">
            <i class="bi ${getCategoryIcon(item.kategori)}"></i>
            ${escHtml(item.kategori)}
          </div>
          <h4>${escHtml(item.nama_barang)}</h4>
          ${item.deskripsi ? `<p style="font-size:0.8rem; color:var(--text-secondary); margin-top:4px;">${escHtml(item.deskripsi)}</p>` : ''}
          <div class="card-meta">
            <div class="stock-info">
              Tersedia: <strong>${available}</strong> / ${item.total_stok}
            </div>
            <button class="btn btn-sm ${canBorrow ? 'btn-primary' : 'btn-outline'}"
              ${canBorrow ? `onclick="openLoanForm(${item.id})"` : 'disabled'}
              style="${canBorrow ? '' : 'opacity:0.5; cursor:not-allowed;'}">
              <i class="bi ${canBorrow ? 'bi-arrow-right-circle' : 'bi-x-circle'}"></i>
              ${canBorrow ? 'Pinjam' : 'Habis'}
            </button>
          </div>
        </div>
      </div>`;
  }).join('');
}

// ─── Catalog Search ─────────────────────────────────────
function initCatalogSearch() {
  const input = document.getElementById('catalogSearchInput');
  if (!input) return;

  let debounceTimer;
  input.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      currentCatalogSearch = input.value.trim();
      loadCatalog();
    }, 300);
  });
}

// ─── Category Filter Chips ──────────────────────────────
async function loadCategoryFilters() {
  const container = document.getElementById('categoryFilters');
  if (!container) return;

  // Ambil semua barang untuk extract kategori unik
  const res = await apiGet(API.getBarang);
  if (!res.success) return;

  const items = res.data || [];
  const categories = ['Semua', ...new Set(items.map(i => i.kategori))];

  container.innerHTML = categories.map(cat =>
    `<button class="filter-chip ${cat === 'Semua' ? 'active' : ''}" data-category="${escAttr(cat)}">${escHtml(cat)}</button>`
  ).join('');

  container.addEventListener('click', (e) => {
    if (!e.target.classList.contains('filter-chip')) return;
    container.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
    e.target.classList.add('active');
    currentCategoryFilter = e.target.dataset.category;
    loadCatalog();
  });
}

// ─── Loan Form ──────────────────────────────────────────
function initLoanForm() {
  const modal     = document.getElementById('loanModal');
  const closeBtn  = document.getElementById('loanModalClose');
  const cancelBtn = document.getElementById('loanModalCancel');
  const form      = document.getElementById('loanForm');

  if (!modal) return;

  closeBtn.addEventListener('click',  () => closeModal('loanModal'));
  cancelBtn.addEventListener('click', () => closeModal('loanModal'));
  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeModal('loanModal');
  });

  // Set default date
  const dateInput = document.getElementById('loanDate');
  if (dateInput) dateInput.value = new Date().toISOString().split('T')[0];

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    handleLoanSubmit();
  });
}

async function populateItemSelect() {
  const select = document.getElementById('loanItemSelect');
  if (!select) return;

  const res = await apiGet(API.getBarang);
  if (!res.success) return;

  const items = (res.data || []).filter(i => i.stok_tersedia > 0);

  select.innerHTML = '<option value="">-- Pilih Barang --</option>' +
    items.map(item =>
      `<option value="${item.id}">${escHtml(item.nama_barang)} (tersedia: ${item.stok_tersedia})</option>`
    ).join('');
}

async function openLoanForm(itemId) {
  await populateItemSelect();

  const select = document.getElementById('loanItemSelect');
  if (select) select.value = itemId;

  const dateInput = document.getElementById('loanDate');
  if (dateInput) dateInput.value = new Date().toISOString().split('T')[0];

  clearFormErrors(document.getElementById('loanForm'));
  openModal('loanModal');
}

async function handleLoanSubmit() {
  const idBarang  = parseInt(document.getElementById('loanItemSelect').value, 10);
  const peminjam  = document.getElementById('loanBorrower').value.trim();
  const tglPinjam = document.getElementById('loanDate').value;
  const durasi    = parseInt(document.getElementById('loanDuration').value, 10);

  // ── Client-side validation ──
  let valid = true;

  if (!idBarang) { showFieldError('loanItemSelect', 'Pilih barang yang ingin dipinjam.'); valid = false; }
  else { hideFieldError('loanItemSelect'); }

  if (!peminjam || peminjam.length < 3) { showFieldError('loanBorrower', 'Nama peminjam minimal 3 karakter.'); valid = false; }
  else { hideFieldError('loanBorrower'); }

  if (!tglPinjam) { showFieldError('loanDate', 'Tanggal pinjam wajib diisi.'); valid = false; }
  else { hideFieldError('loanDate'); }

  if (isNaN(durasi) || durasi < 1) { showFieldError('loanDuration', 'Durasi minimal 1 hari.'); valid = false; }
  else if (durasi > 30) { showFieldError('loanDuration', 'Durasi maksimal 30 hari.'); valid = false; }
  else { hideFieldError('loanDuration'); }

  if (!valid) return;

  // Disable submit
  const submitBtn = document.querySelector('#loanForm button[type="submit"]');
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Memproses...';
  }

  const res = await apiPost(API.tambahPeminjaman, {
    id_barang:      idBarang,
    nama_peminjam:  peminjam,
    tanggal_pinjam: tglPinjam,
    durasi:         durasi,
  });

  // Re-enable
  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.innerHTML = '<i class="bi bi-send"></i> Ajukan Peminjaman';
  }

  if (res.success) {
    showToast(res.message, 'success');
    closeModal('loanModal');
    document.getElementById('loanForm').reset();
    loadCatalog();
    loadUserStats();
  } else {
    showToast(res.message, 'danger');
  }
}


// ═══════════════════════════════════════════════════════════
//  UTILITY
// ═══════════════════════════════════════════════════════════

function escHtml(str) {
  const div = document.createElement('div');
  div.textContent = str || '';
  return div.innerHTML;
}

function escAttr(str) {
  return (str || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}


// ═══════════════════════════════════════════════════════════
//  INITIALIZATION
// ═══════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', () => {
  const isAdmin = document.getElementById('adminPage');
  const isUser  = document.getElementById('userPage');

  if (isAdmin) initAdminPage();
  if (isUser)  initUserPage();
});
