<?php
/**
 * API Endpoint untuk mendapatkan daftar bot yang terhubung ke sebuah channel.
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

$pdo = get_db_connection();
if (!$pdo) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal.']);
    exit;
}

// --- Input ---
$telegram_channel_id = filter_input(INPUT_GET, 'channel_id', FILTER_VALIDATE_INT);
if (!$telegram_channel_id) {
    echo json_encode(['status' => 'error', 'message' => 'Input tidak valid: channel_id diperlukan.']);
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
    $bots = $pcBotRepo->getBotsForChannel($internal_channel_id);

    echo json_encode(['status' => 'success', 'bots' => $bots]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

exit;
