<?php
/**
 * POST /api/update_settings.php
 * Endpoint update pengaturan sistem & branding (Khusus Admin)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../app_settings.php';

// Validasi otentikasi & hak akses Admin
if (empty($_SESSION['invento_logged_in']) || empty($_SESSION['invento_user'])) {
    jsonResponse(false, 'Akses ditolak. Silakan login terlebih dahulu.', null, 401);
}

if (($_SESSION['invento_user']['role'] ?? '') !== 'admin') {
    jsonResponse(false, 'Akses ditolak. Fitur ini hanya dapat diakses oleh Administrator.', null, 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Metode request tidak diizinkan.', null, 405);
}

$currentSettings = getAppSettings($pdo);

// Ambil input dari form-data atau json
$appName     = trim($_POST['app_name'] ?? '');
$appSubtitle = trim($_POST['app_subtitle'] ?? '');
$hapusLogo   = isset($_POST['hapus_logo']) && ($_POST['hapus_logo'] === '1' || $_POST['hapus_logo'] === 'true');

if ($appName === '') {
    $rawInput = json_decode(file_get_contents('php://input'), true);
    if ($rawInput) {
        $appName     = trim($rawInput['app_name'] ?? '');
        $appSubtitle = trim($rawInput['app_subtitle'] ?? '');
        $hapusLogo   = !empty($rawInput['hapus_logo']);
    }
}

if ($appName === '') {
    jsonResponse(false, 'Nama aplikasi tidak boleh kosong.', null, 422);
}

$logoPath = $currentSettings['app_logo'] ?? '';

// Jika user memilih untuk menghapus logo
if ($hapusLogo) {
    if (!empty($logoPath) && file_exists(__DIR__ . '/../' . ltrim($logoPath, '/'))) {
        @unlink(__DIR__ . '/../' . ltrim($logoPath, '/'));
    }
    $logoPath = '';
}

// Proses upload logo baru jika ada
if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $fileTmp  = $_FILES['logo']['tmp_name'];
    $fileSize = $_FILES['logo']['size'];
    $fileName = $_FILES['logo']['name'];

    // Batas ukuran 2MB
    if ($fileSize > 2 * 1024 * 1024) {
        jsonResponse(false, 'Ukuran file logo maksimal 2MB.', null, 422);
    }

    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedExts = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
    if (!in_array($ext, $allowedExts, true)) {
        jsonResponse(false, 'Format file logo harus berupa PNG, JPG, JPEG, WEBP, atau SVG.', null, 422);
    }

    // Buat folder uploads jika belum ada
    $uploadDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $newFileName = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath  = $uploadDir . '/' . $newFileName;

    if (move_uploaded_file($fileTmp, $targetPath)) {
        // Hapus logo lama jika ada
        if (!empty($logoPath) && file_exists(__DIR__ . '/../' . ltrim($logoPath, '/'))) {
            @unlink(__DIR__ . '/../' . ltrim($logoPath, '/'));
        }
        $logoPath = 'uploads/' . $newFileName;
    } else {
        jsonResponse(false, 'Gagal mengunggah file logo ke server.', null, 500);
    }
}

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

    $updated = [
        'app_name'     => $appName,
        'app_subtitle' => $appSubtitle,
        'app_logo'     => $logoPath,
    ];

    jsonResponse(true, 'Pengaturan sistem & branding berhasil disimpan!', $updated);

} catch (PDOException $e) {
    jsonResponse(false, 'Gagal menyimpan pengaturan: ' . $e->getMessage(), null, 500);
}
