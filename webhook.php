<?php

// Sertakan file-file yang diperlukan
require_once __DIR__ . '/core/database.php';
require_once __DIR__ . '/core/TelegramAPI.php';
require_once __DIR__ . '/core/helpers.php';

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

// --- 4. Tentukan Jenis Update & Periksa Pengaturan ---
$update_type = null;
$message_context = null;
$setting_to_check = null;
$is_media = false;

if (isset($update['message'])) {
    $update_type = 'message';
    $message_context = $update['message'];
    $media_keys = ['photo', 'video', 'audio', 'voice', 'document', 'animation', 'video_note'];
    foreach ($media_keys as $key) {
        if (isset($message_context[$key])) {
            $is_media = true;
            break;
        }
    }
    $setting_to_check = $is_media ? 'save_media_messages' : 'save_text_messages';
} elseif (isset($update['edited_message'])) {
    $update_type = 'edited_message';
    $message_context = $update['edited_message'];
    $setting_to_check = 'save_edited_messages';
} elseif (isset($update['callback_query'])) {
    $update_type = 'callback_query';
    $message_context = $update['callback_query']['message'];
    $message_context['from'] = $update['callback_query']['from']; // User yang klik
    $message_context['text'] = "Callback: " . ($update['callback_query']['data'] ?? ''); // Simpan data callback
    $message_context['date'] = time(); // Waktu saat tombol diklik
    $setting_to_check = 'save_callback_queries';
}

// Jika jenis update tidak didukung atau dinonaktifkan oleh admin, keluar.
if ($setting_to_check === null || empty($settings[$setting_to_check])) {
    http_response_code(200); // Balas OK ke Telegram tapi tidak melakukan apa-apa
    exit;
}

// --- 5. Ekstrak dan Proses Data ---
app_log("Update '{$update_type}' diterima dari chat_id: {$message_context['chat']['id']}", 'bot');

// Ekstrak data penting dari konteks pesan
$telegram_message_id = $message_context['message_id'];
$chat_id_from_telegram = $message_context['chat']['id'];
$user_id_from_telegram = $message_context['from']['id'];
$first_name = $message_context['from']['first_name'] ?? '';
$username = $message_context['from']['username'] ?? null;
$text_content = $message_context['text'] ?? ($message_context['caption'] ?? '');
$timestamp = $message_context['date'];
$message_date = date('Y-m-d H:i:s', $timestamp);


try {
    $pdo->beginTransaction();

    // --- Cari atau buat pengguna di tabel 'users' ---
    $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
    $stmt->execute([$user_id_from_telegram]);
    $user = $stmt->fetch();

    if ($user) {
        $internal_user_id = $user['id'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (telegram_id, first_name, username) VALUES (?, ?, ?)");
        $stmt->execute([$user_id_from_telegram, $first_name, $username]);
        $internal_user_id = $pdo->lastInsertId();
        app_log("Pengguna baru dibuat: telegram_id: {$user_id_from_telegram}, user: {$first_name}", 'bot');
    }

    // --- Pastikan relasi user-bot dan entri member ada ---
    $stmt = $pdo->prepare("SELECT id FROM rel_user_bot WHERE user_id = ? AND bot_id = ?");
    $stmt->execute([$internal_user_id, $internal_bot_id]);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO rel_user_bot (user_id, bot_id) VALUES (?, ?)");
        $stmt->execute([$internal_user_id, $internal_bot_id]);
    }
    $stmt = $pdo->prepare("SELECT id FROM members WHERE user_id = ?");
    $stmt->execute([$internal_user_id]);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO members (user_id) VALUES (?)");
        $stmt->execute([$internal_user_id]);
    }

    // --- Simpan pesan ke tabel 'messages' (termasuk raw_data) ---
    $stmt = $pdo->prepare(
        "INSERT INTO messages (user_id, bot_id, telegram_message_id, text, raw_data, direction, telegram_timestamp) VALUES (?, ?, ?, ?, ?, 'incoming', ?)"
    );
    $stmt->execute([$internal_user_id, $internal_bot_id, $telegram_message_id, $text_content, $update_json, $message_date]);

    // --- Simpan file media jika ada ---
    if ($is_media && isset($update['message'])) { // Pastikan ini adalah pesan media asli
        try {
            $media_type = null;
            $media_info = null;

            $media_keys = ['photo', 'video', 'audio', 'voice', 'document', 'video_note', 'animation'];
            foreach ($media_keys as $key) {
                if (isset($update['message'][$key])) {
                    $media_type = $key;
                    $media_info = ($key === 'photo') ? end($update['message']['photo']) : $update['message'][$key];
                    break;
                }
            }

            if ($media_type && $media_info) {
                $sql = "INSERT INTO media_files (
                            file_id, file_unique_id, type, file_size, width, height, duration,
                            mime_type, file_name, caption, caption_entities, user_id, chat_id,
                            message_id, performer, title, has_spoiler, is_animated
                        ) VALUES (
                            :file_id, :file_unique_id, :type, :file_size, :width, :height, :duration,
                            :mime_type, :file_name, :caption, :caption_entities, :user_id, :chat_id,
                            :message_id, :performer, :title, :has_spoiler, :is_animated
                        )";

                $stmt_media = $pdo->prepare($sql);

                $stmt_media->execute([
                    ':file_id' => $media_info['file_id'],
                    ':file_unique_id' => $media_info['file_unique_id'],
                    ':type' => $media_type,
                    ':file_size' => $media_info['file_size'] ?? null,
                    ':width' => $media_info['width'] ?? ($media_type === 'video_note' ? ($media_info['length'] ?? null) : null),
                    ':height' => $media_info['height'] ?? ($media_type === 'video_note' ? ($media_info['length'] ?? null) : null),
                    ':duration' => $media_info['duration'] ?? null,
                    ':mime_type' => $media_info['mime_type'] ?? null,
                    ':file_name' => $media_info['file_name'] ?? null,
                    ':caption' => $update['message']['caption'] ?? null,
                    ':caption_entities' => isset($update['message']['caption_entities']) ? json_encode($update['message']['caption_entities']) : null,
                    ':user_id' => $update['message']['from']['id'],
                    ':chat_id' => $update['message']['chat']['id'],
                    ':message_id' => $update['message']['message_id'],
                    ':performer' => $media_info['performer'] ?? null,
                    ':title' => $media_info['title'] ?? null,
                    ':has_spoiler' => $update['message']['has_media_spoiler'] ?? false,
                    ':is_animated' => $media_info['is_animated'] ?? ($media_type === 'animation'),
                ]);

                app_log("Media {$media_type} dari chat_id: {$update['message']['chat']['id']} disimpan ke media_files.", 'bot');
            }
        } catch (Exception $media_exception) {
            app_log("Gagal menyimpan detail media: " . $media_exception->getMessage(), 'database');
        }
    }

    // --- Handle perintah ---
    $telegram_api = new TelegramAPI($bot_token);
    // Perintah hanya dieksekusi jika itu adalah pesan teks baru
    if (isset($update['message']['text'])) {
        $text_for_command = $update['message']['text'];

        if ($text_for_command === '/start') {
            $reply_text = "Selamat datang! Silakan kirim pesan Anda, dan admin kami akan segera merespons.";
            $telegram_api->sendMessage($chat_id_from_telegram, $reply_text);
            app_log("Perintah /start dieksekusi untuk chat_id: {$chat_id_from_telegram}", 'bot');
        } elseif ($text_for_command === '/login') {
            app_log("Perintah /login diterima dari chat_id: {$chat_id_from_telegram}", 'bot');

            if (!defined('BASE_URL') || empty(BASE_URL)) {
                app_log("Pembuatan token gagal: BASE_URL tidak terdefinisi. Chat ID: {$chat_id_from_telegram}", 'error');
                $telegram_api->sendMessage($chat_id_from_telegram, "Maaf, terjadi kesalahan teknis (ERR:CFG01). Tidak dapat membuat link login saat ini.");
            } else {
                try {
                    $login_token = bin2hex(random_bytes(32));
                    $token_creation_time = date('Y-m-d H:i:s');

                    $stmt = $pdo->prepare("UPDATE members SET login_token = ?, token_created_at = ?, token_used = 0 WHERE user_id = ?");
                    $stmt->execute([$login_token, $token_creation_time, $internal_user_id]);

                    app_log("Token login berhasil dibuat untuk user_id: {$internal_user_id}", 'bot');

                    $login_link = rtrim(BASE_URL, '/') . '/member/index.php?token=' . $login_token;
                    $reply_text = "Klik tombol di bawah ini untuk masuk ke Panel Member Anda.\n\nTombol ini hanya dapat digunakan satu kali.";
                    $keyboard = ['inline_keyboard' => [[['text' => 'Login ke Panel Member', 'url' => $login_link]]]];
                    $telegram_api->sendMessage($chat_id_from_telegram, $reply_text, null, json_encode($keyboard));

                } catch (Exception $e) {
                    app_log("Pembuatan token gagal (DB Error): " . $e->getMessage() . ". Chat ID: {$chat_id_from_telegram}", 'database');
                    $telegram_api->sendMessage($chat_id_from_telegram, "Maaf, terjadi kesalahan teknis (ERR:DB01). Gagal membuat token login.");
                }
            }
        }
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    app_log("Database Error saat proses pesan: " . $e->getMessage(), 'database');
    http_response_code(500);
    exit;
}




// Beri respons OK ke Telegram untuk menandakan update sudah diterima.
http_response_code(200);
echo "OK";
