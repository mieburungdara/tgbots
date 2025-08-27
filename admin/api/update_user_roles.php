<?php

require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/helpers.php'; // For potential future use of is_admin()

session_start();
header('Content-Type: application/json');

// Security check: ensure the user is an admin
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Metode permintaan tidak valid. Hanya POST yang diizinkan.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['telegram_id']) || !filter_var($data['telegram_id'], FILTER_VALIDATE_INT)) {
    http_response_code(400);
    echo json_encode(['error' => 'Telegram ID tidak valid atau tidak diberikan.']);
    exit;
}

if (!isset($data['role_ids']) || !is_array($data['role_ids'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Role IDs tidak valid atau tidak diberikan.']);
    exit;
}

$user_id_to_update = (int)$data['telegram_id'];
// Sanitize all elements to be integers
$role_ids = array_filter(array_map('intval', $data['role_ids']), fn($id) => $id > 0);

$pdo = get_db_connection();

try {
    $pdo->beginTransaction();

    // 1. Hapus semua peran lama dari pengguna
    $stmt_delete = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?");
    $stmt_delete->execute([$user_id_to_update]);

    // 2. Masukkan peran baru jika ada
    if (!empty($role_ids)) {
        $stmt_insert = $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)");
        foreach ($role_ids as $role_id) {
            $stmt_insert->execute([$user_id_to_update, $role_id]);
        }
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Peran pengguna berhasil diperbarui.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    error_log('API Error in update_user_roles.php: ' . $e->getMessage());
    echo json_encode(['error' => 'Terjadi kesalahan pada server saat memperbarui peran.']);
}
