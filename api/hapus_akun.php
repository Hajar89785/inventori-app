<?php
/**
 * POST /api/hapus_akun.php
 * Body JSON: { "id": 1 }
 */

require_once __DIR__ . '/../koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method tidak diizinkan.', null, 405);

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) jsonResponse(false, 'Data input tidak valid.', null, 400);

$id = (int) ($input['id'] ?? 0);
if ($id < 1) jsonResponse(false, 'ID akun tidak valid.', null, 400);

try {
    $stmt = $pdo->prepare('SELECT * FROM akun WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $akun = $stmt->fetch();
    if (!$akun) jsonResponse(false, 'Akun tidak ditemukan.', null, 404);

    // Jangan hapus akun admin terakhir
    if ($akun['role'] === 'admin') {
        $countAdmin = $pdo->query("SELECT COUNT(*) FROM akun WHERE role = 'admin'")->fetchColumn();
        if ($countAdmin <= 1) {
            jsonResponse(false, 'Tidak dapat menghapus akun admin terakhir.', null, 409);
        }
    }

    $stmt = $pdo->prepare('DELETE FROM akun WHERE id = :id');
    $stmt->execute([':id' => $id]);

    jsonResponse(true, 'Akun berhasil dihapus.');

} catch (PDOException $e) {
    jsonResponse(false, 'Gagal menghapus akun: ' . $e->getMessage(), null, 500);
}
