<?php

require_once __DIR__ . '/../../core/database.php';

header('Content-Type: application/json');

if (!isset($_GET['telegram_id']) || !filter_var($_GET['telegram_id'], FILTER_VALIDATE_INT)) {
    http_response_code(400);
    echo json_encode(['error' => 'Telegram ID tidak valid atau tidak diberikan.']);
    exit;
}

$user_id = (int)$_GET['telegram_id'];
$pdo = get_db_connection();

try {
    $stmt = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    // Mengambil semua role_id sebagai array of integers
    $role_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    // Konversi ke integer karena beberapa driver PDO mungkin mengembalikan string
    $role_ids = array_map('intval', $role_ids);

    echo json_encode(['role_ids' => $role_ids]);
} catch (Exception $e) {
    http_response_code(500);
    // Sebaiknya tidak menampilkan pesan error teknis ke user di production
    error_log('API Error in get_user_roles.php: ' . $e->getMessage());
    echo json_encode(['error' => 'Terjadi kesalahan pada server saat mengambil data peran.']);
}
