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

// --- 3. Baca dan proses input dari Telegram ---
$update_json = file_get_contents('php://input');
$update = json_decode($update_json, true);

if (!$update) {
    // Jika tidak ada update, keluar dengan tenang.
    exit;
}

// Fokus pada 'message' untuk saat ini.
if (!isset($update['message'])) {
    // Bisa jadi callback_query atau tipe lain, abaikan untuk saat ini.
    exit;
}

$message = $update['message'];
app_log("Pesan diterima dari chat_id: {$message['chat']['id']}", 'bot');

// Ekstrak data penting dari pesan
$telegram_message_id = $message['message_id'];
$chat_id_from_telegram = $message['chat']['id'];
$first_name = $message['from']['first_name'] ?? '';
$username = $message['from']['username'] ?? null;
$text = $message['text'] ?? ''; // Bisa jadi kosong jika media
$timestamp = $message['date'];
$message_date = date('Y-m-d H:i:s', $timestamp);


try {
    $pdo->beginTransaction();

    // --- 4. Cari atau buat pengguna di tabel 'users' ---
    $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
    $stmt->execute([$chat_id_from_telegram]);
    $user = $stmt->fetch();

    if ($user) {
        $internal_user_id = $user['id'];
    } else {
        // Pengguna tidak ada, buat baru
        $stmt = $pdo->prepare("INSERT INTO users (telegram_id, first_name, username) VALUES (?, ?, ?)");
        $stmt->execute([$chat_id_from_telegram, $first_name, $username]);
        $internal_user_id = $pdo->lastInsertId();
        app_log("Pengguna baru dibuat: telegram_id: {$chat_id_from_telegram}, user: {$first_name}", 'bot');
    }

    // --- 5. Pastikan relasi user-bot ada ---
    $stmt = $pdo->prepare("SELECT id FROM rel_user_bot WHERE user_id = ? AND bot_id = ?");
    $stmt->execute([$internal_user_id, $internal_bot_id]);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO rel_user_bot (user_id, bot_id) VALUES (?, ?)");
        $stmt->execute([$internal_user_id, $internal_bot_id]);
        app_log("Relasi baru dibuat antara user_id: {$internal_user_id} dan bot_id: {$internal_bot_id}", 'bot');
    }

    // --- 6. Pastikan entri member ada (untuk fitur login) ---
    $stmt = $pdo->prepare("SELECT id FROM members WHERE user_id = ?");
    $stmt->execute([$internal_user_id]);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO members (user_id) VALUES (?)");
        $stmt->execute([$internal_user_id]);
        app_log("Member baru dibuat untuk user_id: {$internal_user_id}", 'bot');
    }

    // --- 7. Simpan pesan ke tabel 'messages' ---
    $stmt = $pdo->prepare(
        "INSERT INTO messages (user_id, bot_id, telegram_message_id, text, direction, telegram_timestamp) VALUES (?, ?, ?, ?, 'incoming', ?)"
    );
    $stmt->execute([$internal_user_id, $internal_bot_id, $telegram_message_id, $text, $message_date]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    app_log("Database Error saat proses pesan: " . $e->getMessage(), 'database');
    http_response_code(500);
    exit;
}


// --- 8. Handle perintah ---
$telegram_api = new TelegramAPI($bot_token);

if ($text === '/start') {
    $reply_text = "Selamat datang! Silakan kirim pesan Anda, dan admin kami akan segera merespons.";
    $telegram_api->sendMessage($chat_id_from_telegram, $reply_text);
    app_log("Perintah /start dieksekusi untuk chat_id: {$chat_id_from_telegram}", 'bot');
} elseif ($text === '/login') {
    app_log("Perintah /login diterima dari chat_id: {$chat_id_from_telegram}", 'bot');

    // Pastikan BASE_URL sudah didefinisikan di config.php
    if (!defined('BASE_URL') || empty(BASE_URL)) {
        app_log("Pembuatan token gagal: BASE_URL tidak terdefinisi. Chat ID: {$chat_id_from_telegram}", 'error');
        $telegram_api->sendMessage($chat_id_from_telegram, "Maaf, terjadi kesalahan teknis (ERR:CFG01). Tidak dapat membuat link login saat ini.");
        exit;
    }

    try {
        // Buat token login unik
        $login_token = bin2hex(random_bytes(32));
        $token_creation_time = date('Y-m-d H:i:s');

        // Simpan token ke database menggunakan user_id
        $stmt = $pdo->prepare("UPDATE members SET login_token = ?, token_created_at = ?, token_used = 0 WHERE user_id = ?");
        $stmt->execute([$login_token, $token_creation_time, $internal_user_id]);

        app_log("Token login berhasil dibuat untuk user_id: {$internal_user_id}", 'bot');

        // Buat link login
        $login_link = rtrim(BASE_URL, '/') . '/member/index.php?token=' . $login_token;

        // Kirim link ke pengguna
        $reply_text = "Klik tombol di bawah ini untuk masuk ke Panel Member Anda.\n\nTombol ini hanya dapat digunakan satu kali.";
        $keyboard = [
            'inline_keyboard' => [
                [['text' => 'Login ke Panel Member', 'url' => $login_link]]
            ]
        ];
        $telegram_api->sendMessage($chat_id_from_telegram, $reply_text, null, json_encode($keyboard));

    } catch (Exception $e) {
        app_log("Pembuatan token gagal (DB Error): " . $e->getMessage() . ". Chat ID: {$chat_id_from_telegram}", 'database');
        $telegram_api->sendMessage($chat_id_from_telegram, "Maaf, terjadi kesalahan teknis (ERR:DB01). Gagal membuat token login.");
        exit;
    }
}


// Beri respons OK ke Telegram untuk menandakan update sudah diterima.
http_response_code(200);
echo "OK";
