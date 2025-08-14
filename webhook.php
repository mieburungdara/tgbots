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

                // --- All logic is now deferred to this point ---

                // 1. Get message context from the saved state
                $replied_message_id = $state_context['reply_to_message_id'];
                $replied_chat_id = $state_context['reply_to_chat_id'];

                // We need to instantiate repositories here as they are not in this scope
                require_once __DIR__ . '/core/database/PackageRepository.php';
                require_once __DIR__ . '/core/database/BotChannelUsageRepository.php';
                $package_repo = new PackageRepository($pdo);
                $bot_channel_usage_repo = new BotChannelUsageRepository($pdo);

                // 2. Get description and media group info
                $stmt_media_info = $pdo->prepare("SELECT media_group_id, caption FROM media_files WHERE message_id = ? AND chat_id = ?");
                $stmt_media_info->execute([$replied_message_id, $replied_chat_id]);
                $media_info = $stmt_media_info->fetch(PDO::FETCH_ASSOC);

                $media_group_id = $media_info['media_group_id'] ?? null;
                $description = $media_info['caption'] ?? ''; // Default

                if ($media_group_id) {
                    $stmt_caption = $pdo->prepare("SELECT caption FROM media_files WHERE media_group_id = ? AND caption IS NOT NULL AND caption != '' LIMIT 1");
                    $stmt_caption->execute([$media_group_id]);
                    $group_caption = $stmt_caption->fetchColumn();
                    if ($group_caption) {
                        $description = $group_caption;
                    }
                }

                // 3. Get all media file IDs for the package
                $media_file_ids = [];
                if ($media_group_id) {
                    $stmt_media = $pdo->prepare("SELECT id FROM media_files WHERE media_group_id = ? AND chat_id = ?");
                    $stmt_media->execute([$media_group_id, $replied_chat_id]);
                    $media_file_ids = $stmt_media->fetchAll(PDO::FETCH_COLUMN);
                } else {
                    $stmt_media = $pdo->prepare("SELECT id FROM media_files WHERE message_id = ? AND chat_id = ?");
                    $stmt_media->execute([$replied_message_id, $replied_chat_id]);
                    $media_file_id = $stmt_media->fetchColumn();
                    if ($media_file_id) $media_file_ids[] = $media_file_id;
                }

                if (empty($media_file_ids)) {
                    $telegram_api->sendMessage($chat_id_from_telegram, "⚠️ Terjadi kesalahan internal (media tidak ditemukan). Proses dibatalkan.");
                    $user_repo->setUserState($internal_user_id, null, null);
                    $update_handled = true;
                    $pdo->commit(); // Commit the state change and exit cleanly
                    http_response_code(200);
                    exit;
                }

                // 4. Get thumbnail ID
                $stmt_thumb = $pdo->prepare("SELECT id FROM media_files WHERE message_id = ? AND chat_id = ?");
                $stmt_thumb->execute([$replied_message_id, $replied_chat_id]);
                $thumbnail_media_id = $stmt_thumb->fetchColumn();
                if (!$thumbnail_media_id) {
                    $telegram_api->sendMessage($chat_id_from_telegram, "⚠️ Terjadi kesalahan internal (thumbnail tidak ditemukan). Proses dibatalkan.");
                    $user_repo->setUserState($internal_user_id, null, null);
                    $update_handled = true;
                    $pdo->commit(); // Commit the state change and exit cleanly
                    http_response_code(200);
                    exit;
                }

                // 5. Get storage channel and copy the message
                $storage_channel = $bot_channel_usage_repo->getNextChannelForBot($internal_bot_id);
                if (!$storage_channel) {
                    $telegram_api->sendMessage($chat_id_from_telegram, "⚠️ Terjadi kesalahan sistem (tidak ada channel penyimpanan). Harap hubungi admin.");
                    $user_repo->setUserState($internal_user_id, null, null);
                    $update_handled = true;
                    $pdo->commit();
                    http_response_code(200);
                    exit;
                }
                $copied_message = $telegram_api->copyMessage($storage_channel['channel_id'], $replied_chat_id, $replied_message_id);
                if (!$copied_message || !isset($copied_message['ok']) || !$copied_message['ok']) {
                    $telegram_api->sendMessage($chat_id_from_telegram, "⚠️ Gagal menyimpan media. Harap coba lagi atau hubungi admin.");
                    $user_repo->setUserState($internal_user_id, null, null);
                    $update_handled = true;
                    $pdo->commit();
                    http_response_code(200);
                    exit;
                }
                $storage_message_id = $copied_message['result']['message_id'];

                // 6. Create the package (NOW we increment the sequence)
                $package_id = $package_repo->createPackageWithPublicId($internal_user_id, $internal_bot_id, $description, $thumbnail_media_id);

                // 7. Link files to the package and update price
                $pdo->prepare("UPDATE media_packages SET price = ?, status = 'available' WHERE id = ?")->execute([$price, $package_id]);

                $stmt_link = $pdo->prepare("UPDATE media_files SET package_id = ?, storage_channel_id = ?, storage_message_id = ? WHERE id = ?");
                foreach ($media_file_ids as $media_id) {
                    $stmt_link->execute([$package_id, $storage_channel['channel_id'], $storage_message_id, $media_id]);
                }

                // 8. Finalize
                $user_repo->setUserState($internal_user_id, null, null);
                $package = $package_repo->find($package_id);
                $public_id_display = $package['public_id'] ?? 'N/A';
                $telegram_api->sendMessage($chat_id_from_telegram, "✅ Harga telah ditetapkan. Paket media Anda dengan ID *{$public_id_display}* sekarang tersedia untuk dijual.");

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
