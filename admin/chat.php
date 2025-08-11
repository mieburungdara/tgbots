<?php
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/TelegramAPI.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("Koneksi database gagal.");
}

$telegram_bot_id = isset($_GET['bot_id']) ? (string)$_GET['bot_id'] : '';
$telegram_chat_id = isset($_GET['chat_id']) ? (int)$_GET['chat_id'] : 0;

if (empty($telegram_bot_id) || !$telegram_chat_id) {
    header("Location: index.php");
    exit;
}

// Cari ID internal bot berdasarkan ID bot telegram
$stmt = $pdo->prepare("SELECT id FROM bots WHERE token LIKE ?");
$stmt->execute([$telegram_bot_id . ':%']);
$internal_bot_id = $stmt->fetchColumn();

if (!$internal_bot_id) {
    die("Bot tidak ditemukan.");
}

// Ambil id internal dari tabel chats menggunakan ID bot internal
$stmt = $pdo->prepare("SELECT id FROM chats WHERE bot_id = ? AND chat_id = ?");
$stmt->execute([$internal_bot_id, $telegram_chat_id]);
$chat_internal_id = $stmt->fetchColumn();

if (!$chat_internal_id) {
    die("Chat tidak ditemukan.");
}

// Handle pengiriman balasan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $reply_text = trim($_POST['reply_text']);
    if (!empty($reply_text)) {
        // Ambil info bot dan chat untuk mengirim pesan
        $stmt = $pdo->prepare(
            "SELECT c.chat_id AS telegram_chat_id, b.token
             FROM chats c
             JOIN bots b ON c.bot_id = b.id
             WHERE c.id = ?"
        );
        $stmt->execute([$chat_internal_id]);
        $target = $stmt->fetch();

        if ($target) {
            $telegram_api = new TelegramAPI($target['token']);
            $result = $telegram_api->sendMessage($target['telegram_chat_id'], $reply_text);

            // Simpan pesan keluar ke database
            if ($result && $result['ok']) {
                $stmt = $pdo->prepare(
                    "INSERT INTO messages (chat_id, telegram_message_id, text, direction, telegram_timestamp)
                     VALUES (?, ?, ?, 'outgoing', NOW())"
                );
                $stmt->execute([$chat_internal_id, $result['result']['message_id'], $reply_text]);
            }
        }
        // Redirect untuk mencegah resubmit dan menampilkan pesan baru
        header("Location: chat.php?bot_id=" . $telegram_bot_id . "&chat_id=" . $telegram_chat_id);
        exit;
    }
}

// Ambil detail chat dan semua pesan
$stmt = $pdo->prepare("SELECT * FROM chats WHERE id = ?");
$stmt->execute([$chat_internal_id]);
$chat_info = $stmt->fetch();

if (!$chat_info) {
    die("Chat tidak ditemukan.");
}

$stmt = $pdo->prepare("SELECT * FROM messages WHERE chat_id = ? ORDER BY telegram_timestamp ASC");
$stmt->execute([$chat_internal_id]);
$messages = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat dengan <?= htmlspecialchars($chat_info['first_name']) ?> - Admin Panel</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; background-color: #f4f6f8; color: #333; }
        .container { max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; height: 100vh; }
        header { background-color: #fff; padding: 15px 20px; border-bottom: 1px solid #ddd; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        h1 { margin: 0; font-size: 1.2em; }
        .chat-window { flex-grow: 1; padding: 20px; overflow-y: auto; background-color: #e5ddd5; }
        .message { margin-bottom: 15px; max-width: 70%; padding: 10px 15px; border-radius: 15px; line-height: 1.4; }
        .message.incoming { background-color: #fff; align-self: flex-start; border-bottom-left-radius: 2px; }
        .message.outgoing { background-color: #dcf8c6; align-self: flex-end; border-bottom-right-radius: 2px; margin-left: auto; }
        .message-container { display: flex; flex-direction: column; }
        .reply-form { padding: 20px; background-color: #f0f0f0; border-top: 1px solid #ccc; }
        textarea { width: calc(100% - 22px); padding: 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 1em; resize: vertical; }
        button { padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; float: right; margin-top: 10px; }
        nav { margin-bottom: 10px; }
        nav a { text-decoration: none; color: #007bff; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <nav><a href="index.php?bot_id=<?= $telegram_bot_id ?>">&larr; Kembali ke Daftar Percakapan</a></nav>
            <h1>Chat dengan <?= htmlspecialchars($chat_info['first_name']) ?> (@<?= htmlspecialchars($chat_info['username']) ?>)</h1>
        </header>
        <div class="chat-window">
            <div class="message-container">
                <?php foreach ($messages as $message): ?>
                    <div class="message <?= $message['direction'] ?>">
                        <?= nl2br(htmlspecialchars($message['text'])) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="reply-form">
            <form action="chat.php?bot_id=<?= $telegram_bot_id ?>&chat_id=<?= $telegram_chat_id ?>" method="post">
                <textarea name="reply_text" rows="3" placeholder="Ketik balasan Anda..." required></textarea>
                <button type="submit" name="reply_message">Kirim</button>
            </form>
        </div>
    </div>
</body>
</html>
