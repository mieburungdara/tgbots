<?php

// Sertakan file-file inti
require_once __DIR__ . '/core/database.php';
require_once __DIR__ . '/core/TelegramAPI.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/database/BotRepository.php';
require_once __DIR__ . '/core/database/UserRepository.php';
require_once __DIR__ . '/core/handlers/UpdateHandler.php';

// Bungkus semua dalam try-catch untuk penanganan error terpusat
try {
    // 1. Validasi ID Bot dari URL
    if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
        http_response_code(400);
        app_log("Webhook Error: ID bot dari URL tidak valid atau tidak ada.", 'bot');
        exit;
    }
    $telegram_bot_id = (int)$_GET['id'];

    // 2. Koneksi DB dan Inisialisasi Repository
    $pdo = get_db_connection();
    if (!$pdo) {
        http_response_code(500);
        exit;
    }

    $bot_repo = new BotRepository($pdo);
    $bot = $bot_repo->findBotByTelegramId($telegram_bot_id);

    if (!$bot) {
        http_response_code(404);
        app_log("Webhook Error: Bot dengan ID Telegram {$telegram_bot_id} tidak ditemukan.", 'bot');
        exit;
    }
    $bot_token = $bot['token'];
    $internal_bot_id = (int)$bot['id'];
    $settings = $bot_repo->getBotSettings($internal_bot_id);

    // 3. Baca dan Proses Input dari Telegram
    $update_json = file_get_contents('php://input');
    $update = json_decode($update_json, true);
    if (!$update) {
        exit;
    }

    // 4. Routing dan Validasi Update
    $update_handler = new UpdateHandler($settings);
    $update_type = $update_handler->getUpdateType($update);
    if ($update_type === null) {
        http_response_code(200); // Abaikan update yang tidak didukung atau dinonaktifkan
        exit;
    }

    $message_context = UpdateHandler::getMessageContext($update);
    if (!isset($message_context['from']['id']) || !isset($message_context['chat']['id'])) {
        http_response_code(200); // Abaikan update tanpa konteks user/chat
        exit;
    }

    // 5. Ekstrak Data Penting
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

    // --- Mulai Transaksi dan Logika Utama ---
    $pdo->beginTransaction();

    // 6. Dapatkan atau Buat User
    $user_repo = new UserRepository($pdo, $internal_bot_id);
    $current_user = $user_repo->findOrCreateUser($user_id_from_telegram, $first_name, $username);
    if (!$current_user) {
        app_log("Gagal menemukan atau membuat pengguna untuk telegram_id: {$user_id_from_telegram}", 'error');
        $pdo->rollBack();
        http_response_code(500);
        exit;
    }
    $internal_user_id = $current_user['id'];

    // 7. Simpan Pesan (jika diaktifkan)
    $pdo->prepare("INSERT INTO messages (user_id, bot_id, telegram_message_id, text, raw_data, direction, telegram_timestamp) VALUES (?, ?, ?, ?, ?, 'incoming', ?)")
        ->execute([$internal_user_id, $internal_bot_id, $telegram_message_id, $text_content, $update_json, $message_date]);

    // 8. Inisialisasi API dan delegasikan ke Handler yang Tepat
    $telegram_api = new TelegramAPI($bot_token);
    $update_handled = false;

    // Logika State-based (harus dijalankan sebelum command handler)
    if ($current_user['state'] !== null && $update_type === 'message' && isset($message_context['text'])) {
        $text = $message_context['text'];
        $state_context = json_decode($current_user['state_context'] ?? '{}', true);
        if (strpos($text, '/cancel') === 0) {
            $user_repo->setUserState($internal_user_id, null, null);
            $telegram_api->sendMessage($chat_id_from_telegram, "Operasi dibatalkan.");
            $update_handled = true;
        } else {
            // ... (logika state-based lainnya seperti 'awaiting_price')
            if ($current_user['state'] == 'awaiting_price' && is_numeric($text) && $text >= 0) {
                $price = (float)$text;
                $package_id = $state_context['package_id'];
                $pdo->prepare("UPDATE media_packages SET price = ?, status = 'available' WHERE id = ? AND seller_user_id = ?")->execute([$price, $package_id, $internal_user_id]);
                $user_repo->setUserState($internal_user_id, null, null);
                $telegram_api->sendMessage($chat_id_from_telegram, "✅ Harga telah ditetapkan. Paket media Anda dengan ID #{$package_id} sekarang tersedia untuk dijual.");
                $update_handled = true;
            } elseif ($current_user['state'] == 'awaiting_price') {
                 $telegram_api->sendMessage($chat_id_from_telegram, "⚠️ Harga tidak valid. Harap masukkan angka saja (contoh: 50000).");
                 $update_handled = true;
            }
        }
    }

    if ($update_handled) {
        // Jika sudah ditangani oleh state machine, jangan proses lebih lanjut
    } elseif ($is_media && $update_type === 'message') {
        require_once __DIR__ . '/core/handlers/MediaHandler.php';
        $handler = new MediaHandler($pdo, $update['message'], $user_id_from_telegram, $chat_id_from_telegram, $telegram_message_id);
        $handler->handle();
    } elseif ($update_type === 'callback_query') {
        app_log("Entering callback_query handler block.", 'bot');
        require_once __DIR__ . '/core/handlers/CallbackQueryHandler.php';
        $handler = new CallbackQueryHandler($pdo, $telegram_api, $current_user, $chat_id_from_telegram, $update['callback_query']);
        $handler->handle();
        app_log("Exited callback_query handler block.", 'bot');
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
