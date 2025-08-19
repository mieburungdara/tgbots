<?php

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

    // Definisikan konstanta global untuk digunakan di semua handler
    $api_for_globals = new TelegramAPI($bot_token, $pdo, $internal_bot_id);
    $bot_info = $api_for_globals->getMe();
    if (!defined('BOT_USERNAME')) {
        define('BOT_USERNAME', $bot_info['result']['username'] ?? '');
    }

    // 3. Baca dan Proses Input dari Telegram
    $update_json = file_get_contents('php://input');
    if (empty($update_json)) {
        exit;
    }

    // Simpan raw update ke database untuk debugging
    $raw_update_repo = new RawUpdateRepository($pdo);
    $raw_update_repo->create($update_json);

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

    // Handle inline query separately as it has a different structure
    if ($update_type === 'inline_query') {
        require_once __DIR__ . '/core/handlers/InlineQueryHandler.php';

        // Gunakan instance API yang sudah dibuat untuk globals
        $handler = new InlineQueryHandler($pdo, $api_for_globals);
        $handler->handle($update['inline_query']);

        http_response_code(200);
        exit;
    }

    // Handle channel_post separately as it has no user context ('from')
    if ($update_type === 'channel_post') {
        $pdo->beginTransaction();
        try {
            $channel_post = $update['channel_post'];
            $telegram_message_id = $channel_post['message_id'];
            $chat_id = $channel_post['chat']['id'];
            $text_content = $channel_post['text'] ?? ($channel_post['caption'] ?? '');
            $timestamp = $channel_post['date'] ?? time();
            $message_date = date('Y-m-d H:i:s', $timestamp);

            // Simpan pesan channel dengan tipe yang sesuai
            $chat_type = $channel_post['chat']['type'] ?? 'channel';
            $stmt = $pdo->prepare(
                "INSERT INTO messages (user_id, bot_id, telegram_message_id, chat_id, chat_type, update_type, text, raw_data, direction, telegram_timestamp)
                 VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, 'incoming', ?)"
            );
            $stmt->execute([$internal_bot_id, $telegram_message_id, $chat_id, $chat_type, 'channel_post', $text_content, $update_json, $message_date]);

            // Panggil handler jika ada logika spesifik untuk channel post
            require_once __DIR__ . '/core/handlers/ChannelPostHandler.php';
            // Note: ChannelPostHandler constructor expects a user, which we don't have.
            // We pass a dummy array for now. This handler is mostly empty anyway.
            $handler = new ChannelPostHandler($pdo, $api_for_globals, new UserRepository($pdo, $internal_bot_id), [], $chat_id, $channel_post);
            $handler->handle();

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            app_log("Error handling channel_post: " . $e->getMessage(), 'error');
        }
        http_response_code(200);
        exit;
    }

    $message_context = UpdateHandler::getMessageContext($update);

    // Handle auto-forwarded messages from discussion groups as a special case
    if (isset($message_context['is_automatic_forward']) && $message_context['is_automatic_forward'] === true) {
        $pdo->beginTransaction();
        try {
            $chat_id = $message_context['chat']['id'];
            $telegram_message_id = $message_context['message_id'];
            $text_content = $message_context['text'] ?? ($message_context['caption'] ?? '');
            $timestamp = $message_context['date'] ?? time();
            $message_date = date('Y-m-d H:i:s', $timestamp);
            $chat_type = $message_context['chat']['type'] ?? 'supergroup';

            // Use the generic 'Telegram' user or a default if 'from' is not available
            $user_id_from_telegram = $message_context['from']['id'] ?? 777000;
            $first_name = $message_context['from']['first_name'] ?? 'Telegram';

            $user_repo = new UserRepository($pdo, $internal_bot_id);
            $current_user = $user_repo->findOrCreateUser($user_id_from_telegram, $first_name, null);
            $internal_user_id = $current_user['id'];

            // Simpan pesan
            $stmt = $pdo->prepare(
                "INSERT INTO messages (user_id, bot_id, telegram_message_id, chat_id, chat_type, update_type, text, raw_data, direction, telegram_timestamp)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'incoming', ?)"
            );
            $stmt->execute([$internal_user_id, $internal_bot_id, $telegram_message_id, $chat_id, $chat_type, 'message', $text_content, $update_json, $message_date]);

            // Delegasikan ke MessageHandler, yang akan memanggil handleAutomaticForward secara internal
            require_once __DIR__ . '/core/handlers/MessageHandler.php';
            $telegram_api = new TelegramAPI($bot_token, $pdo, $internal_bot_id);
            $handler = new MessageHandler($pdo, $telegram_api, $user_repo, $current_user, $chat_id, $message_context);
            $handler->handle();

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            app_log("Error handling auto-forwarded message: " . $e->getMessage(), 'error');
        }
        http_response_code(200);
        exit;
    }

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
    $chat_type = $message_context['chat']['type'] ?? 'unknown';
    $pdo->prepare("INSERT INTO messages (user_id, bot_id, telegram_message_id, chat_id, chat_type, update_type, text, raw_data, direction, telegram_timestamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'incoming', ?)")
        ->execute([$internal_user_id, $internal_bot_id, $telegram_message_id, $chat_id_from_telegram, $chat_type, $update_type, $text_content, $update_json, $message_date]);

    // 8. Inisialisasi API dan delegasikan ke Handler yang Tepat
    $telegram_api = new TelegramAPI($bot_token, $pdo, $internal_bot_id);
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

                // --- Finalization logic for /sell & /addmedia ---

                $media_messages = $state_context['media_messages'] ?? [];
                if (empty($media_messages)) {
                    $user_repo->setUserState($internal_user_id, null, null);
                    $update_handled = true;
                    return;
                }

                require_once __DIR__ . '/core/database/PackageRepository.php';
                require_once __DIR__ . '/core/database/BotChannelUsageRepository.php';
                $package_repo = new PackageRepository($pdo);
                $bot_channel_usage_repo = new BotChannelUsageRepository($pdo);

                // 1. Collect all unique media file info from all messages/groups
                $media_to_process = []; // [ 'db_id' => ['message_id' => ..., 'chat_id' => ...], ... ]
                $all_captions = [];
                $first_message_context = $media_messages[0];

                foreach ($media_messages as $msg_context) {
                    $stmt_info = $pdo->prepare("SELECT id, media_group_id, caption FROM media_files WHERE message_id = ? AND chat_id = ?");
                    $stmt_info->execute([$msg_context['message_id'], $msg_context['chat_id']]);
                    $media_info = $stmt_info->fetch(PDO::FETCH_ASSOC);
                    if (!$media_info) continue;

                    if (!empty($media_info['caption'])) $all_captions[] = $media_info['caption'];

                    if ($media_group_id = $media_info['media_group_id']) {
                        $stmt_group = $pdo->prepare("SELECT id, message_id FROM media_files WHERE media_group_id = ?");
                        $stmt_group->execute([$media_group_id]);
                        while ($row = $stmt_group->fetch(PDO::FETCH_ASSOC)) {
                            $media_to_process[$row['id']] = ['message_id' => $row['message_id'], 'chat_id' => $msg_context['chat_id']];
                        }
                    } else {
                        $media_to_process[$media_info['id']] = ['message_id' => $msg_context['message_id'], 'chat_id' => $msg_context['chat_id']];
                    }
                }

                if (empty($media_to_process)) { /* ... error handling ... */ exit; }

                // 2. Define thumbnail and description
                $stmt_thumb = $pdo->prepare("SELECT id FROM media_files WHERE message_id = ? AND chat_id = ?");
                $stmt_thumb->execute([$first_message_context['message_id'], $first_message_context['chat_id']]);
                $thumbnail_media_id = $stmt_thumb->fetchColumn();
                $description = $all_captions[0] ?? '';

                // 3. Get storage channel and copy all media
                $storage_channel = $bot_channel_usage_repo->getNextChannelForBot($internal_bot_id);
                if (!$storage_channel) { /* ... error handling ... */ exit; }
                $storage_channel_id = $storage_channel['channel_id'];

                $original_db_ids = array_keys($media_to_process);
                $original_message_ids = array_column($media_to_process, 'message_id');
                $from_chat_id = $first_message_context['chat_id']; // Assume all media from one user comes from the same chat

                $copied_messages_result = $telegram_api->copyMessages($storage_channel_id, $from_chat_id, json_encode($original_message_ids));
                if (!$copied_messages_result || !isset($copied_messages_result['ok']) || !$copied_messages_result['ok'] || count($copied_messages_result['result']) !== count($original_message_ids)) {
                    $telegram_api->sendMessage($chat_id_from_telegram, "⚠️ Gagal menyimpan semua media. Proses dibatalkan.");
                    // ... error handling ...
                    exit;
                }

                // 4. Create the package
                $package_id = $package_repo->createPackageWithPublicId($internal_user_id, $internal_bot_id, $description, $thumbnail_media_id);

                // 5. Link files to the package and update price
                $pdo->prepare("UPDATE media_packages SET price = ?, status = 'available' WHERE id = ?")->execute([$price, $package_id]);

                $new_storage_message_ids = $copied_messages_result['result'];
                $stmt_link = $pdo->prepare("UPDATE media_files SET package_id = ?, storage_channel_id = ?, storage_message_id = ? WHERE id = ?");
                for ($i = 0; $i < count($original_db_ids); $i++) {
                    $stmt_link->execute([$package_id, $storage_channel_id, $new_storage_message_ids[$i]['message_id'], $original_db_ids[$i]]);
                }

                // 6. Finalize
                $user_repo->setUserState($internal_user_id, null, null);
                $package = $package_repo->find($package_id);
                $public_id_display = $package['public_id'] ?? 'N/A';
                $telegram_api->sendMessage($chat_id_from_telegram, "✅ Harga telah ditetapkan. Paket media Anda dengan ID *{$public_id_display}* sekarang tersedia untuk dijual.", 'Markdown');

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
    } elseif ($update_type === 'edited_message') {
        require_once __DIR__ . '/core/handlers/EditedMessageHandler.php';
        $handler = new EditedMessageHandler($pdo, $update['edited_message']);
        $handler->handle();
    } elseif ($update_type === 'callback_query') {
        require_once __DIR__ . '/core/handlers/CallbackQueryHandler.php';
        $handler = new CallbackQueryHandler($pdo, $telegram_api, $user_repo, $current_user, $chat_id_from_telegram, $update['callback_query']);
        $handler->handle();
    } elseif ($update_type === 'channel_post') {
        require_once __DIR__ . '/core/handlers/ChannelPostHandler.php';
        $handler = new ChannelPostHandler($pdo, $telegram_api, $user_repo, $current_user, $chat_id_from_telegram, $message_context);
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
