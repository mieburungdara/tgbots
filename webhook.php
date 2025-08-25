<?php
declare(strict_types=1);
/**
 * Titik Masuk Utama (Webhook) untuk Bot Telegram.
 *
 * File ini adalah satu-satunya skrip yang dipanggil oleh server Telegram saat ada
 * pembaruan baru (pesan, callback, dll.). URL file ini diatur sebagai webhook
 * untuk setiap bot.
 *
 * Alur Kerja Utama:
 * 1. Validasi permintaan dan ID bot dari URL (`?id=<bot_id>`).
 * 2. Inisialisasi koneksi database dan temukan bot yang sesuai.
 * 3. Baca payload JSON mentah dari Telegram dan simpan untuk debugging.
 * 4. Gunakan `UpdateHandler` untuk menentukan jenis pembaruan (pesan, callback, dll.).
 * 5. Berdasarkan jenis pembaruan, inisialisasi dan panggil handler yang sesuai
 *    (MessageHandler, CallbackQueryHandler, dll.).
 * 6. Seluruh proses (kecuali untuk update yang diabaikan) dibungkus dalam
 *    transaksi database untuk memastikan integritas data.
 * 7. Menangani error terpusat dan melakukan rollback jika terjadi kesalahan.
 */

// Definisikan path root untuk akses file yang andal
define('ROOT_PATH', __DIR__);

// Sertakan file-file inti
require_once __DIR__ . '/core/database.php';
require_once __DIR__ . '/core/TelegramAPI.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/database/BotRepository.php';
require_once __DIR__ . '/core/database/UserRepository.php';
require_once __DIR__ . '/core/handlers/UpdateHandler.php';
require_once __DIR__ . '/core/database/RawUpdateRepository.php';

// Bungkus semua dalam try-catch untuk penanganan error terpusat
try {
    // Langkah 1: Validasi ID Bot dari URL.
    if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
        http_response_code(400); // Bad Request
        app_log("Webhook Error: ID bot dari URL tidak valid atau tidak ada.", 'bot');
        exit;
    }
    $telegram_bot_id = (int)$_GET['id'];

    // Langkah 2: Koneksi ke DB dan inisialisasi repository dasar.
    $pdo = get_db_connection();
    if (!$pdo) {
        http_response_code(500); // Internal Server Error
        exit;
    }

    // Temukan bot di database berdasarkan ID Telegram-nya.
    $bot_repo = new BotRepository($pdo);
    $bot = $bot_repo->findBotByTelegramId($telegram_bot_id);

    if (!$bot) {
        http_response_code(404); // Not Found
        app_log("Webhook Error: Bot dengan ID Telegram {$telegram_bot_id} tidak ditemukan.", 'bot');
        exit;
    }
    $bot_token = $bot['token'];
    $settings = $bot_repo->getBotSettings($telegram_bot_id);

    // Definisikan konstanta global yang mungkin dibutuhkan oleh handler lain.
    $api_for_globals = new TelegramAPI($bot_token, $pdo, $telegram_bot_id);
    $bot_info = $api_for_globals->getMe();
    if (!defined('BOT_USERNAME')) {
        define('BOT_USERNAME', $bot_info['result']['username'] ?? '');
    }

    // Langkah 3: Baca dan proses input JSON mentah dari Telegram.
    $update_json = file_get_contents('php://input');
    if (empty($update_json)) {
        exit; // Tidak ada data, abaikan.
    }

    // Simpan raw update ke database untuk keperluan debugging.
    $raw_update_repo = new RawUpdateRepository($pdo);
    $raw_update_repo->create($update_json);

    $update = json_decode($update_json, true);
    if (!$update) {
        exit; // JSON tidak valid.
    }

    // Langkah 4: Tentukan jenis update dan apakah perlu diproses.
    $update_handler = new UpdateHandler($settings);
    $update_type = $update_handler->getUpdateType($update);
    if ($update_type === null) {
        http_response_code(200); // Abaikan update yang tidak didukung atau dinonaktifkan
        exit;
    }

    // Penanganan kasus khusus: `inline_query`.
    if ($update_type === 'inline_query') {
        require_once __DIR__ . '/core/handlers/InlineQueryHandler.php';
        $handler = new InlineQueryHandler($pdo, $api_for_globals);
        $handler->handle($update['inline_query']);
        http_response_code(200);
        exit;
    }

    // Penanganan kasus khusus: `channel_post`.
    if ($update_type === 'channel_post') {
        $pdo->beginTransaction();
        try {
            $channel_post = $update['channel_post'];
            $telegram_message_id = $channel_post['message_id'];
            $chat_id = $channel_post['chat']['id'];
            $text_content = $channel_post['text'] ?? ($channel_post['caption'] ?? '');
            $timestamp = $channel_post['date'] ?? time();
            $message_date = date('Y-m-d H:i:s', $timestamp);
            $chat_type = $channel_post['chat']['type'] ?? 'channel';

            $stmt = $pdo->prepare(
                "INSERT INTO messages (user_id, bot_id, telegram_message_id, chat_id, chat_type, update_type, text, raw_data, direction, telegram_timestamp)
                 VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, 'incoming', ?)"
            );
            $stmt->execute([$telegram_bot_id, $telegram_message_id, $chat_id, $chat_type, 'channel_post', $text_content, $update_json, $message_date]);

            require_once __DIR__ . '/core/handlers/ChannelPostHandler.php';
            $handler = new ChannelPostHandler($pdo, $api_for_globals, new UserRepository($pdo, $telegram_bot_id), [], $chat_id, $channel_post);
            $handler->handle();

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            app_log("Error handling channel_post: " . $e->getMessage(), 'error');
        }
        http_response_code(200);
        exit;
    }

    $message_context = UpdateHandler::getMessageContext($update);

    // Penanganan kasus khusus: Pesan yang diteruskan otomatis.
    if (isset($message_context['is_automatic_forward']) && $message_context['is_automatic_forward'] === true) {
        $pdo->beginTransaction();
        try {
            $chat_id = $message_context['chat']['id'];
            $telegram_message_id = $message_context['message_id'];
            $text_content = $message_context['text'] ?? ($message_context['caption'] ?? '');
            $timestamp = $message_context['date'] ?? time();
            $message_date = date('Y-m-d H:i:s', $timestamp);
            $chat_type = $message_context['chat']['type'] ?? 'supergroup';
            $user_id_from_telegram = $message_context['from']['id'] ?? 777000;
            $first_name = $message_context['from']['first_name'] ?? 'Telegram';

            $user_repo = new UserRepository($pdo, $telegram_bot_id);
            $current_user = $user_repo->findOrCreateUser($user_id_from_telegram, $first_name, null);
            
            $stmt = $pdo->prepare(
                "INSERT INTO messages (user_id, bot_id, telegram_message_id, chat_id, chat_type, update_type, text, raw_data, direction, telegram_timestamp)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'incoming', ?)"
            );
            $stmt->execute([$current_user['telegram_id'], $telegram_bot_id, $telegram_message_id, $chat_id, $chat_type, 'message', $text_content, $update_json, $message_date]);

            require_once __DIR__ . '/core/handlers/MessageHandler.php';
            $telegram_api = new TelegramAPI($bot_token, $pdo, $telegram_bot_id);
            $handler = new MessageHandler($pdo, $telegram_api, $user_repo, $current_user, $chat_id, $message_context);
            $handler->handle();

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            app_log("Error handling auto-forwarded message: " . $e->getMessage(), 'error');
        }
        http_response_code(200);
        exit;
    }

    // Untuk update standar, harus ada ID pengguna dan chat.
    if (!isset($message_context['from']['id']) || !isset($message_context['chat']['id'])) {
        http_response_code(200);
        exit;
    }

    // --- Mulai Transaksi dan Logika Utama ---
    $pdo->beginTransaction();

    // Ekstrak data penting.
    $chat_id_from_telegram = $message_context['chat']['id'];
    $user_id_from_telegram = $message_context['from']['id'];
    $first_name = $message_context['from']['first_name'] ?? '';
    $username = $message_context['from']['username'] ?? null;
    $telegram_message_id = $message_context['message_id'] ?? 0;
    $text_content = $message_context['text'] ?? ($message_context['caption'] ?? '');
    $timestamp = $message_context['date'] ?? time();
    $message_date = date('Y-m-d H:i:s', $timestamp);

    $is_media = false;
    if (isset($update['message'])) {
        $media_keys = ['photo', 'video', 'audio', 'voice', 'document', 'animation', 'video_note'];
        foreach ($media_keys as $key) {
            if (isset($update['message'][$key])) {
                $is_media = true;
                break;
            }
        }
    }

    // Dapatkan atau buat pengguna.
    $user_repo = new UserRepository($pdo, $telegram_bot_id);
    $current_user = $user_repo->findOrCreateUser($user_id_from_telegram, $first_name, $username);
    if (!$current_user) {
        app_log("Gagal menemukan atau membuat pengguna untuk telegram_id: {$user_id_from_telegram}", 'error');
        $pdo->rollBack();
        http_response_code(500);
        exit;
    }

    // Simpan pesan masuk.
    $chat_type = $message_context['chat']['type'] ?? 'unknown';
    $pdo->prepare("INSERT INTO messages (user_id, bot_id, telegram_message_id, chat_id, chat_type, update_type, text, raw_data, direction, telegram_timestamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'incoming', ?)")
        ->execute([$current_user['telegram_id'], $telegram_bot_id, $telegram_message_id, $chat_id_from_telegram, $chat_type, $update_type, $text_content, $update_json, $message_date]);

    // Inisialisasi API
    $telegram_api = new TelegramAPI($bot_token, $pdo, $telegram_bot_id);
    $update_handled = false;

    // --- State Machine ---
    if ($current_user['state'] !== null && $update_type === 'message' && isset($message_context['text'])) {
        $text = $message_context['text'];
        $state_context = json_decode($current_user['state_context'] ?? '{}', true);
        if (strpos($text, '/cancel') === 0) {
            $user_repo->setUserState($current_user['telegram_id'], null, null);
            $telegram_api->sendMessage($chat_id_from_telegram, "Operasi dibatalkan.");
            $update_handled = true;
        } else {
            // ... (logic state machine kamu, contoh: awaiting_price, dsb.)
        }
    }

    // --- Delegasi ke handler sesuai jenis update ---
    if ($update_handled) {
        // sudah ditangani oleh state machine
    } elseif ($is_media && $update_type === 'message') {
        require_once __DIR__ . '/core/handlers/MediaHandler.php';
        $handler = new MediaHandler($pdo, $update['message'], $user_id_from_telegram, $chat_id_from_telegram, $telegram_message_id);
        $handler->handle();
    } elseif ($update_type === 'edited_message') {
        require_once __DIR__ . '/core/handlers/EditedMessageHandler.php';
        $handler = new EditedMessageHandler($pdo, $update['edited_message']);
        $handler->handle();
    } elseif ($update_type === 'callback_query') {
        require_once __DIR__ . '/core/handlers/CallbackQueryHandler.php';
        $handler = new CallbackQueryHandler($pdo, $telegram_api, $user_repo, $current_user, $chat_id_from_telegram, $update['callback_query']);
        $handler->handle();
    } elseif ($update_type === 'message') {
        require_once __DIR__ . '/core/handlers/MessageHandler.php';
        $handler = new MessageHandler($pdo, $telegram_api, $user_repo, $current_user, $chat_id_from_telegram, $message_context);
        $handler->handle();
    }

    $pdo->commit();
    http_response_code(200);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error_message = sprintf("Fatal Webhook Error: %s in %s on line %d", $e->getMessage(), $e->getFile(), $e->getLine());
    app_log($error_message, 'error');
    http_response_code(500);
}
