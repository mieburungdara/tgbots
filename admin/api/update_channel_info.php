<?php
/**
 * API Endpoint untuk menyegarkan informasi channel dari Telegram dan menyimpannya ke DB.
 */
session_start();
header('Content-Type: application/json');

// --- Dependensi ---
require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/TelegramAPI.php';
require_once __DIR__ . '/../../core/database/BotRepository.php';
require_once __DIR__ . '/../../core/database/PrivateChannelRepository.php';

// --- Keamanan & Inisialisasi ---
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Sesi tidak valid.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Metode permintaan tidak valid.']);
    exit;
}

$pdo = get_db_connection();
if (!$pdo) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal.']);
    exit;
}

// --- Input ---
$telegram_channel_id = filter_input(INPUT_POST, 'channel_id', FILTER_VALIDATE_INT);
if (!$telegram_channel_id) {
    echo json_encode(['status' => 'error', 'message' => 'Input tidak valid: channel_id diperlukan.']);
    exit;
}

// --- Logika ---
try {
    // 1. Dapatkan bot pertama yang tersedia untuk membuat panggilan API
    $botRepo = new BotRepository($pdo);
    $bots = $botRepo->getAllBots();
    if (empty($bots)) {
        throw new Exception("Tidak ada bot yang terdaftar di sistem untuk melakukan panggilan API.");
    }
    // Ambil token dari bot pertama dalam daftar
    $bot_token = $pdo->query("SELECT token FROM bots WHERE id = " . $bots[0]['id'])->fetchColumn();

    // 2. Hubungi API Telegram untuk mendapatkan info chat
    $telegram_api = new TelegramAPI($bot_token);
    $chat_info = $telegram_api->getChat($telegram_channel_id);

    if (!$chat_info || !$chat_info['ok']) {
        throw new Exception("Gagal mendapatkan info dari Telegram. Pesan: " . ($chat_info['description'] ?? 'Tidak diketahui'));
    }

    $new_name = $chat_info['result']['title'] ?? null;
    if (empty($new_name)) {
        throw new Exception("Tidak dapat menemukan nama (title) dari respons API Telegram.");
    }

    // 3. Perbarui nama di database
    $channelRepo = new PrivateChannelRepository($pdo);
    if ($channelRepo->updateNameByTelegramId($telegram_channel_id, $new_name)) {
        echo json_encode(['status' => 'success', 'message' => 'Nama channel berhasil diperbarui.', 'newName' => $new_name]);
    } else {
        throw new Exception("Gagal memperbarui nama channel di database.");
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

exit;
