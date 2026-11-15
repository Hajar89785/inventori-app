<?php
/**
 * ============================================
 * INVENTO - Koneksi Database (PDO)
 * ============================================
 *
 * File ini menangani koneksi ke MySQL menggunakan PDO.
 * Disertakan (require) oleh setiap endpoint API.
 */

// ─── Konfigurasi Database ────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'invento_db');
define('DB_USER', 'root');
define('DB_PASS', '');           // Laragon default: tanpa password
define('DB_CHARSET', 'utf8mb4');

// ─── Buat Koneksi PDO ────────────────────────────────────
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // Lempar exception saat error
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // Hasil fetch berupa array asosiatif
        PDO::ATTR_EMULATE_PREPARES   => false,                    // Gunakan prepared statement native
        PDO::ATTR_STRINGIFY_FETCHES  => false,                    // Pertahankan tipe data asli
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Koneksi database gagal: ' . $e->getMessage(),
    ]);
    exit;
}

// ─── Helper: JSON Response ───────────────────────────────
/**
 * Mengirim response JSON yang konsisten.
 *
 * @param bool   $success  Status operasi
 * @param string $message  Pesan untuk ditampilkan
 * @param mixed  $data     Data opsional
 * @param int    $httpCode HTTP status code
 */
function jsonResponse(bool $success, string $message = '', $data = null, int $httpCode = 200): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');

    $response = ['success' => $success];
    if ($message !== '') {
        $response['message'] = $message;
    }
    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── CORS Header (untuk akses AJAX dari front-end) ──────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Tangani preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

