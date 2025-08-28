<?php
/**
 * API Endpoint untuk menghapus hubungan bot dari sebuah channel.
 */
session_start();
header('Content-Type: application/json');

// --- Dependensi ---
require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/database/PrivateChannelBotRepository.php';

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
$bot_id = filter_input(INPUT_POST, 'bot_id', FILTER_VALIDATE_INT);

if (!$telegram_channel_id || !$bot_id) {
    echo json_encode(['status' => 'error', 'message' => 'Input tidak valid: channel_id dan bot_id diperlukan.']);
    exit;
}

// --- Logika ---
try {
    require_once __DIR__ . '/../../core/database/PrivateChannelRepository.php';
    $channelRepo = new PrivateChannelRepository($pdo);
    $channel = $channelRepo->findByTelegramId($telegram_channel_id);

    if (!$channel) {
        throw new Exception("Channel tidak ditemukan.");
    }

    $internal_channel_id = $channel['id'];
    $pcBotRepo = new PrivateChannelBotRepository($pdo);

    if ($pcBotRepo->removeBotFromChannel($internal_channel_id, $bot_id)) {
        echo json_encode(['status' => 'success', 'message' => 'Bot berhasil dihapus dari channel.']);
    } else {
        throw new Exception("Gagal menghapus bot dari channel di database.");
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

exit;
