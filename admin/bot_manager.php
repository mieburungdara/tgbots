<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/TelegramAPI.php';

// Basic security check - ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

// TODO: Add admin authentication check here

$action = $_POST['action'] ?? null;
$bot_id = isset($_POST['bot_id']) ? (int)$_POST['bot_id'] : 0;
$response = ['status' => 'error', 'message' => 'Aksi tidak diketahui.'];

if ($action === 'get_me' && $bot_id > 0) {
    try {
        $pdo = get_db_connection();
        if (!$pdo) {
            throw new Exception("Koneksi database gagal.");
        }

        // 1. Ambil token bot dari database
        $stmt_token = $pdo->prepare("SELECT token FROM bots WHERE id = ?");
        $stmt_token->execute([$bot_id]);
        $token = $stmt_token->fetchColumn();

        if (!$token) {
            throw new Exception("Bot dengan ID {$bot_id} tidak ditemukan.");
        }

        // 2. Panggil API Telegram
        $telegram_api = new TelegramAPI($token);
        $bot_info = $telegram_api->getMe();

        if (!isset($bot_info['ok']) || !$bot_info['ok']) {
            throw new Exception("Gagal mendapatkan informasi dari Telegram: " . ($bot_info['description'] ?? 'Error tidak diketahui'));
        }

        // 3. Perbarui database
        $bot_result = $bot_info['result'];
        $first_name = $bot_result['first_name'];
        $username = $bot_result['username'] ?? null;

        $stmt_update = $pdo->prepare("UPDATE bots SET first_name = ?, username = ? WHERE id = ?");
        $stmt_update->execute([$first_name, $username, $bot_id]);

        $response = [
            'status' => 'success',
            'message' => 'Informasi bot berhasil diperbarui.',
            'data' => [
                'first_name' => $first_name,
                'username' => $username
            ]
        ];

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
}

echo json_encode($response);
exit;
