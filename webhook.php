<?php
declare(strict_types=1);

/**
 * Titik Masuk Utama (Webhook) untuk Bot Telegram.
 *
 * File ini bertindak sebagai "front controller". Tugasnya adalah:
 * 1. Melakukan validasi dan setup lingkungan dasar (bot, database).
 * 2. Menyimpan data mentah pembaruan untuk debugging.
 * 3. Mendelegasikan seluruh logika pemrosesan pembaruan ke kelas UpdateDispatcher.
 * 4. Menangkap dan mencatat error fatal apa pun yang mungkin terjadi selama proses.
 */

define('ROOT_PATH', __DIR__);

// Sertakan file-file inti yang dibutuhkan untuk inisialisasi
require_once __DIR__ . '/core/database.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/database/BotRepository.php';
require_once __DIR__ . '/core/database/RawUpdateRepository.php';
require_once __DIR__ . '/core/UpdateDispatcher.php';
require_once __DIR__ . '/core/handlers/UpdateHandler.php'; // Diperlukan oleh UpdateDispatcher

try {
    // 1. Validasi ID Bot dari URL
    if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
        http_response_code(400); // Bad Request
        app_log("Webhook Error: ID bot dari URL tidak valid atau tidak ada.", 'bot');
        exit;
    }
    $bot_id = (int)$_GET['id'];

    // 2. Koneksi ke DB
    $pdo = get_db_connection();
    if (!$pdo) {
        // App_log sudah memiliki fallback ke file log jika pdo gagal
        app_log("Webhook Error: Gagal terkoneksi ke database.", 'critical');
        http_response_code(500); // Internal Server Error
        exit;
    }

    // 3. Temukan bot di database
    $bot_repo = new BotRepository($pdo);
    $bot = $bot_repo->findBotByTelegramId($bot_id);

    if (!$bot) {
        http_response_code(404); // Not Found
        app_log("Webhook Error: Bot dengan ID Telegram {$bot_id} tidak ditemukan.", 'bot');
        exit;
    }

    // 4. Definisikan konstanta global yang mungkin dibutuhkan (cth: untuk inline query)
    require_once __DIR__ . '/core/TelegramAPI.php';
    $api_for_globals = new TelegramAPI($bot['token'], $pdo, $bot_id);
    $bot_info = $api_for_globals->getMe();
    if ($bot_info['ok'] && !defined('BOT_USERNAME')) {
        define('BOT_USERNAME', $bot_info['result']['username'] ?? '');
    }

    // 5. Baca input JSON mentah dari Telegram
    $update_json = file_get_contents('php://input');
    if (empty($update_json)) {
        http_response_code(200); // Tidak ada data, abaikan saja.
        exit;
    }

    // 6. Simpan raw update untuk debugging
    $raw_update_repo = new RawUpdateRepository($pdo);
    $raw_update_repo->create($update_json);

    $update = json_decode($update_json, true);
    if (!$update) {
        app_log("Webhook Error: Gagal mendekode JSON dari Telegram.", 'warning');
        http_response_code(200); // JSON tidak valid, abaikan.
        exit;
    }

    // 7. Delegasikan ke Dispatcher
    $dispatcher = new UpdateDispatcher($pdo, $bot, $update);
    $dispatcher->dispatch();

    // Jika dispatch selesai tanpa error, kirim respons OK
    http_response_code(200);

} catch (Throwable $e) {
    // Tangkap semua error yang tidak tertangani oleh dispatcher
    $error_message = sprintf(
        "Fatal Webhook Error: %s in %s on line %d",
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );
    app_log($error_message, 'error');
    http_response_code(500); // Internal Server Error
}
