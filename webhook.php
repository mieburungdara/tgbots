<?php

// Aktifkan logging error untuk debugging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/webhook_debug.log');

// Sertakan file-file yang diperlukan
require_once __DIR__ . '/core/database.php';
require_once __DIR__ . '/core/TelegramAPI.php';

// --- 1. Validasi bot_id dari URL ---
if (!isset($_GET['bot_id']) || !filter_var($_GET['bot_id'], FILTER_VALIDATE_INT)) {
    http_response_code(400); // Bad Request
    error_log("Webhook Error: bot_id tidak valid atau tidak ada.");
    echo "Error: bot_id tidak valid.";
    exit;
}
$bot_id = (int)$_GET['bot_id'];

// --- 2. Dapatkan koneksi database dan token bot ---
$pdo = get_db_connection();
if (!$pdo) {
    http_response_code(500); // Internal Server Error
    error_log("Webhook Error: Gagal terhubung ke database.");
    exit;
}

$stmt = $pdo->prepare("SELECT token FROM bots WHERE id = ?");
$stmt->execute([$bot_id]);
$bot = $stmt->fetch();

if (!$bot) {
    http_response_code(404); // Not Found
    error_log("Webhook Error: Bot dengan ID {$bot_id} tidak ditemukan.");
    echo "Error: Bot tidak ditemukan.";
    exit;
}
$bot_token = $bot['token'];

// --- 3. Baca dan proses input dari Telegram ---
$update_json = file_get_contents('php://input');
$update = json_decode($update_json, true);

if (!$update) {
    // Jika tidak ada update, keluar dengan tenang.
    exit;
}

// Fokus pada 'message' untuk saat ini.
if (!isset($update['message'])) {
    exit;
}

$message = $update['message'];

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

    // --- 4. Cari atau buat entri di tabel 'chats' ---
    $stmt = $pdo->prepare("SELECT id FROM chats WHERE bot_id = ? AND chat_id = ?");
    $stmt->execute([$bot_id, $chat_id_from_telegram]);
    $chat = $stmt->fetch();

    if ($chat) {
        $internal_chat_id = $chat['id'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO chats (bot_id, chat_id, first_name, username) VALUES (?, ?, ?, ?)");
        $stmt->execute([$bot_id, $chat_id_from_telegram, $first_name, $username]);
        $internal_chat_id = $pdo->lastInsertId();
    }

    // --- 5. Simpan pesan ke tabel 'messages' ---
    $stmt = $pdo->prepare(
        "INSERT INTO messages (chat_id, telegram_message_id, text, direction, telegram_timestamp) VALUES (?, ?, ?, 'incoming', ?)"
    );
    $stmt->execute([$internal_chat_id, $telegram_message_id, $text, $message_date]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Database Error: " . $e->getMessage());
    http_response_code(500);
    exit;
}


// --- 6. Handle perintah /start ---
if ($text === '/start') {
    $telegram_api = new TelegramAPI($bot_token);
    $reply_text = "Selamat datang! Silakan kirim pesan Anda, dan admin kami akan segera merespons.";
    $telegram_api->sendMessage($chat_id_from_telegram, $reply_text);
}

// Beri respons OK ke Telegram untuk menandakan update sudah diterima.
http_response_code(200);
echo "OK";
