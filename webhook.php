<?php

// Sertakan file-file yang diperlukan
require_once __DIR__ . '/core/database.php';
require_once __DIR__ . '/core/TelegramAPI.php';
require_once __DIR__ . '/core/helpers.php';

// Seluruh logika webhook dibungkus dalam blok try-catch untuk menangani semua kemungkinan error.
try {

// --- 1. Validasi ID Bot Telegram dari URL ---
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    http_response_code(400); // Bad Request
    app_log("Webhook Error: ID bot dari URL tidak valid atau tidak ada.", 'bot');
    echo "Error: ID bot tidak valid.";
    exit;
}
$telegram_bot_id = $_GET['id'];

// --- 2. Dapatkan koneksi database dan token bot ---
$pdo = get_db_connection();
if (!$pdo) {
    http_response_code(500); // Internal Server Error
    // Pesan ini sudah dicatat di dalam get_db_connection()
    exit;
}

// Cari bot berdasarkan ID bot dari token, bukan ID internal
$stmt = $pdo->prepare("SELECT id, token FROM bots WHERE token LIKE ?");
$stmt->execute(["{$telegram_bot_id}:%"]);
$bot = $stmt->fetch();

if (!$bot) {
    http_response_code(404); // Not Found
    app_log("Webhook Error: Bot dengan ID Telegram {$telegram_bot_id} tidak ditemukan.", 'bot');
    echo "Error: Bot tidak ditemukan.";
    exit;
}
$bot_token = $bot['token'];
$internal_bot_id = $bot['id']; // ID internal untuk foreign key

// Ambil pengaturan untuk bot ini
$stmt_settings = $pdo->prepare("SELECT setting_key, setting_value FROM bot_settings WHERE bot_id = ?");
$stmt_settings->execute([$internal_bot_id]);
$bot_settings_raw = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);

// Tetapkan pengaturan default jika tidak ada di database
$settings = [
    'save_text_messages'    => $bot_settings_raw['save_text_messages'] ?? '1',
    'save_media_messages'   => $bot_settings_raw['save_media_messages'] ?? '1',
    'save_callback_queries' => $bot_settings_raw['save_callback_queries'] ?? '0',
    'save_edited_messages'  => $bot_settings_raw['save_edited_messages'] ?? '0',
];

// --- 3. Baca dan proses input dari Telegram ---
$update_json = file_get_contents('php://input');
$update = json_decode($update_json, true);

if (!$update) {
    exit; // Keluar jika tidak ada update
}

require_once __DIR__ . '/core/handlers/UpdateHandler.php';

// --- 4. Tentukan Jenis Update & Periksa Pengaturan ---
$update_handler = new UpdateHandler($settings);
$update_type = $update_handler->getUpdateType($update);

// Jika jenis update tidak didukung atau dinonaktifkan oleh admin, keluar.
if ($update_type === null) {
    http_response_code(200); // Balas OK ke Telegram tapi tidak melakukan apa-apa
    exit;
}

// Dapatkan konteks pesan (message, edited_message, atau callback_query.message)
$message_context = UpdateHandler::getMessageContext($update);

// --- 5. Validasi dan Ekstrak Data Penting ---
// Lakukan validasi untuk memastikan struktur dasar ada sebelum diekstrak
if (!isset($message_context['from']['id']) || !isset($message_context['chat']['id'])) {
    app_log("Update '{$update_type}' tidak memiliki 'from' atau 'chat' id, diabaikan.", 'bot');
    http_response_code(200);
    exit;
}

$chat_id_from_telegram = $message_context['chat']['id'];
app_log("Update '{$update_type}' diterima dari chat_id: {$chat_id_from_telegram}", 'bot');

// Variabel $is_media diperlukan oleh logika di bawahnya.
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

// Ekstrak data penting dari konteks pesan dengan aman
$telegram_message_id = $message_context['message_id'] ?? 0;
$user_id_from_telegram = $message_context['from']['id'];
$first_name = $message_context['from']['first_name'] ?? '';
$username = $message_context['from']['username'] ?? null;
$text_content = $message_context['text'] ?? ($message_context['caption'] ?? '');
$timestamp = $message_context['date'] ?? time();
$message_date = date('Y-m-d H:i:s', $timestamp);


    // --- Mulai Transaksi Database ---
    $pdo->beginTransaction();

    // --- Cari atau buat pengguna, dapatkan data lengkap termasuk state dan saldo ---
    $stmt_user = $pdo->prepare(
        "SELECT u.id, u.telegram_id, u.role, u.balance, r.state, r.state_context
         FROM users u
         LEFT JOIN rel_user_bot r ON u.id = r.user_id AND r.bot_id = ?
         WHERE u.telegram_id = ?"
    );
    $stmt_user->execute([$internal_bot_id, $user_id_from_telegram]);
    $current_user = $stmt_user->fetch();

    if ($current_user) {
        $internal_user_id = $current_user['id'];
    } else {
        $initial_role = (defined('SUPER_ADMIN_TELEGRAM_ID') && (string)$user_id_from_telegram === (string)SUPER_ADMIN_TELEGRAM_ID) ? 'admin' : 'user';
        $stmt_insert = $pdo->prepare("INSERT INTO users (telegram_id, first_name, username, role) VALUES (?, ?, ?, ?)");
        $stmt_insert->execute([$user_id_from_telegram, $first_name, $username, $initial_role]);
        $internal_user_id = $pdo->lastInsertId();
        $stmt_user->execute([$internal_bot_id, $user_id_from_telegram]);
        $current_user = $stmt_user->fetch();
        app_log("Pengguna baru dibuat: telegram_id: {$user_id_from_telegram}, user: {$first_name}, peran: {$initial_role}", 'bot');
    }

    if ($current_user) {
        if (defined('SUPER_ADMIN_TELEGRAM_ID') && !empty(SUPER_ADMIN_TELEGRAM_ID) && (string)$user_id_from_telegram === (string)SUPER_ADMIN_TELEGRAM_ID) {
            if ($current_user['role'] !== 'admin') {
                $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$internal_user_id]);
                $current_user['role'] = 'admin';
                app_log("Peran admin diberikan kepada super admin: {$user_id_from_telegram}", 'bot');
            }
        }
        $stmt_rel_check = $pdo->prepare("SELECT state FROM rel_user_bot WHERE user_id = ? AND bot_id = ?");
        $stmt_rel_check->execute([$internal_user_id, $internal_bot_id]);
        if ($stmt_rel_check->fetch() === false) {
             $pdo->prepare("INSERT INTO rel_user_bot (user_id, bot_id) VALUES (?, ?)")->execute([$internal_user_id, $internal_bot_id]);
             $stmt_user->execute([$internal_bot_id, $user_id_from_telegram]);
             $current_user = $stmt_user->fetch();
        }
        $stmt_member = $pdo->prepare("SELECT id FROM members WHERE user_id = ?");
        $stmt_member->execute([$internal_user_id]);
        if (!$stmt_member->fetch()) {
            $pdo->prepare("INSERT INTO members (user_id) VALUES (?)")->execute([$internal_user_id]);
        }
    }

    // Simpan pesan ke tabel 'messages'
    $pdo->prepare("INSERT INTO messages (user_id, bot_id, telegram_message_id, text, raw_data, direction, telegram_timestamp) VALUES (?, ?, ?, ?, ?, 'incoming', ?)")
        ->execute([$internal_user_id, $internal_bot_id, $telegram_message_id, $text_content, $update_json, $message_date]);

    // ===============================================================
    // =========== BLOK LOGIKA APLIKASI MARKETPLACE DIMULAI ==========
    // ===============================================================
    $telegram_api = new TelegramAPI($bot_token);

    function setUserState($pdo, $user_id, $bot_id, $state, $context = null) {
        $stmt = $pdo->prepare("UPDATE rel_user_bot SET state = ?, state_context = ? WHERE user_id = ? AND bot_id = ?");
        $stmt->execute([$state, $context ? json_encode($context) : null, $user_id, $bot_id]);
    }

    $update_handled = false;

    // 1. HANDLE STATE-BASED CONVERSATION
    if ($current_user['state'] !== null && $update_type === 'message' && isset($message_context['text'])) {
        $text = $message_context['text'];
        $state_context = json_decode($current_user['state_context'] ?? '{}', true);

        if (strpos($text, '/cancel') === 0) {
            setUserState($pdo, $internal_user_id, $internal_bot_id, null, null);
            $telegram_api->sendMessage($chat_id_from_telegram, "Operasi dibatalkan.");
            $update_handled = true;
        } else {
            switch ($current_user['state']) {
                case 'awaiting_price':
                    if (is_numeric($text) && $text >= 0) {
                        $price = (float)$text;
                        $package_id = $state_context['package_id'];
                        $pdo->prepare("UPDATE media_packages SET price = ?, status = 'available' WHERE id = ? AND seller_user_id = ?")->execute([$price, $package_id, $internal_user_id]);
                        setUserState($pdo, $internal_user_id, $internal_bot_id, null, null);
                        $telegram_api->sendMessage($chat_id_from_telegram, "✅ Harga telah ditetapkan. Paket media Anda dengan ID #{$package_id} sekarang tersedia untuk dijual.");
                    } else {
                        $telegram_api->sendMessage($chat_id_from_telegram, "⚠️ Harga tidak valid. Harap masukkan angka saja (contoh: 50000).");
                    }
                    $update_handled = true;
                    break;
            }
        }
    }

    // 2. HANDLE MEDIA SUBMISSION
    if (!$update_handled && $is_media && isset($update['message'])) {
        require_once __DIR__ . '/core/handlers/MediaHandler.php';
        $media_handler = new MediaHandler($pdo, $update['message'], $user_id_from_telegram, $chat_id_from_telegram, $telegram_message_id);
        $media_handler->handle();
        $update_handled = true;
    }

    // 3. HANDLE CALLBACK QUERIES
    if (!$update_handled && $update_type === 'callback_query') {
        require_once __DIR__ . '/core/handlers/CallbackQueryHandler.php';
        $callback_handler = new CallbackQueryHandler($pdo, $telegram_api, $current_user, $chat_id_from_telegram, $update['callback_query']);
        $callback_handler->handle();
        $update_handled = true;
    }

    // 4. HANDLE TEXT MESSAGES (COMMANDS) & STATE-BASED CONVERSATIONS
    if (!$update_handled && $update_type === 'message' && isset($message_context['text'])) {
        $text = $message_context['text'];

        // Handle state-based conversation first
        if ($current_user['state'] !== null) {
            $state_context = json_decode($current_user['state_context'] ?? '{}', true);
            if (strpos($text, '/cancel') === 0) {
                $user_repo->setUserState($internal_user_id, null, null);
                $telegram_api->sendMessage($chat_id_from_telegram, "Operasi dibatalkan.");
                $update_handled = true;
            } else {
                switch ($current_user['state']) {
                    case 'awaiting_price':
                        if (is_numeric($text) && $text >= 0) {
                            $price = (float)$text;
                            $package_id = $state_context['package_id'];
                            $pdo->prepare("UPDATE media_packages SET price = ?, status = 'available' WHERE id = ? AND seller_user_id = ?")->execute([$price, $package_id, $internal_user_id]);
                            $user_repo->setUserState($internal_user_id, null, null);
                            $telegram_api->sendMessage($chat_id_from_telegram, "✅ Harga telah ditetapkan. Paket media Anda dengan ID #{$package_id} sekarang tersedia untuk dijual.");
                        } else {
                            $telegram_api->sendMessage($chat_id_from_telegram, "⚠️ Harga tidak valid. Harap masukkan angka saja (contoh: 50000).");
                        }
                        $update_handled = true;
                        break;
                }
            }
        }

        // If not handled by a state, treat as a command
        if (!$update_handled) {
            require_once __DIR__ . '/core/handlers/MessageHandler.php';
            $message_handler = new MessageHandler($pdo, $telegram_api, $user_repo, $current_user, $chat_id_from_telegram, $message_context);
            $message_handler->handle();
            $update_handled = true;
        }
    }

    $pdo->commit();

    // Beri respons OK ke Telegram untuk menandakan update sudah diterima.
    http_response_code(200);
    echo "OK";

} catch (Throwable $e) {
    // Jika terjadi error di mana pun, tangkap di sini.
    // Rollback transaksi jika sedang berjalan.
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Catat error fatal ke log.
    $error_message = sprintf(
        "Fatal Webhook Error: %s in %s on line %d",
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );
    app_log($error_message, 'error');

    // Beri respons 500 ke Telegram.
    http_response_code(500);
    echo "Internal Server Error";
}
