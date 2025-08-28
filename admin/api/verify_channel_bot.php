<?php
/**
 * API Endpoint untuk memverifikasi apakah sebuah bot adalah admin di sebuah channel.
 */
session_start();
header('Content-Type: application/json');

// --- Dependensi ---
require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/TelegramAPI.php';
require_once __DIR__ . '/../../core/database/BotRepository.php';
require_once __DIR__ . '/../../core/database/PrivateChannelRepository.php';
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

// --- Logika Verifikasi ---
try {
    $botRepo = new BotRepository($pdo);
    $channelRepo = new PrivateChannelRepository($pdo);
    $pcBotRepo = new PrivateChannelBotRepository($pdo);

    // 1. Dapatkan token bot dan ID channel telegram
    $bot = $botRepo->findBotByTelegramId($bot_id);
    $channel = $channelRepo->findByTelegramId($telegram_channel_id);

    if (!$bot) {
        throw new Exception("Bot tidak ditemukan di database.");
    }
    if (!$channel) {
        throw new Exception("Channel tidak ditemukan di database.");
    }

    $token = $bot['token'];
    $internal_channel_id = $channel['id'];

    // 2. Hubungi API Telegram untuk memeriksa status keanggotaan bot
    $telegram_api = new TelegramAPI($token);
    $member_info = $telegram_api->getChatMember($telegram_channel_id, $bot_id);

    if (!$member_info || !$member_info['ok']) {
        throw new Exception("Gagal memeriksa status bot di channel. Pastikan bot sudah ditambahkan ke channel. Pesan dari Telegram: " . ($member_info['description'] ?? 'Tidak ada info'));
    }

    $status = $member_info['result']['status'];
    $is_admin = in_array($status, ['creator', 'administrator']);

    // 3. Update status verifikasi di database jika bot adalah admin
    if ($is_admin) {
        // Pastikan hubungan sudah ada sebelum memverifikasi
        if (!$pcBotRepo->isBotInChannel($internal_channel_id, $bot_id)) {
            $pcBotRepo->addBotToChannel($internal_channel_id, $bot_id);
        }

        if ($pcBotRepo->verifyBotInChannel($internal_channel_id, $bot_id)) {
            echo json_encode(['status' => 'success', 'message' => "Verifikasi berhasil! Bot adalah '{$status}' di channel."]);
        } else {
            throw new Exception("Gagal memperbarui status verifikasi di database.");
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => "Verifikasi Gagal. Bot bukan admin di channel (status: {$status})."]);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

exit;
