<?php
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/TelegramAPI.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("Koneksi database gagal.");
}

// Menggunakan ID internal dari database, bukan ID telegram
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$bot_id = isset($_GET['bot_id']) ? (int)$_GET['bot_id'] : 0;

if (!$user_id || !$bot_id) {
    header("Location: index.php");
    exit;
}

// Ambil info pengguna
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch();

// Ambil info bot
$stmt = $pdo->prepare("SELECT * FROM bots WHERE id = ?");
$stmt->execute([$bot_id]);
$bot_info = $stmt->fetch();

if (!$user_info || !$bot_info) {
    die("Pengguna atau bot tidak ditemukan.");
}

// Handle pengiriman balasan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $reply_text = trim($_POST['reply_text']);
    if (!empty($reply_text)) {
        $telegram_api = new TelegramAPI($bot_info['token']);
        // Kirim pesan ke ID telegram pengguna
        $result = $telegram_api->sendMessage($user_info['telegram_id'], $reply_text);

        // Simpan pesan keluar ke database dengan user_id, bot_id, dan raw_data
        if ($result && $result['ok']) {
            $raw_data_json = json_encode($result['result']);
            $stmt = $pdo->prepare(
                "INSERT INTO messages (user_id, bot_id, telegram_message_id, text, raw_data, direction, telegram_timestamp)
                 VALUES (?, ?, ?, ?, ?, 'outgoing', NOW())"
            );
            $stmt->execute([$user_id, $bot_id, $result['result']['message_id'], $reply_text, $raw_data_json]);
        }
        // Redirect untuk mencegah resubmit dan menampilkan pesan baru
        header("Location: chat.php?user_id=" . $user_id . "&bot_id=" . $bot_id);
        exit;
    }
}

// Ambil semua pesan untuk user dan bot ini
$stmt = $pdo->prepare("SELECT * FROM messages WHERE user_id = ? AND bot_id = ? ORDER BY telegram_timestamp ASC");
$stmt->execute([$user_id, $bot_id]);
$messages = $stmt->fetchAll();

// Dapatkan ID bot telegram dari token untuk link "kembali"
$telegram_bot_id = explode(':', $bot_info['token'])[0];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat dengan <?= htmlspecialchars($user_info['first_name']) ?> - Admin Panel</title>
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
        .message-meta { font-size: 0.8em; color: #888; margin-top: 5px; text-align: right; }
        .json-toggle { color: #007bff; cursor: pointer; text-decoration: none; }
        .json-toggle:hover { text-decoration: underline; }
        .raw-json { display: none; margin-top: 10px; padding: 10px; background-color: #2d2d2d; color: #f1f1f1; border-radius: 5px; white-space: pre-wrap; word-break: break-all; max-height: 300px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <nav><a href="index.php?bot_id=<?= $telegram_bot_id ?>">&larr; Kembali ke Daftar Percakapan</a></nav>
            <h1>Chat dengan <?= htmlspecialchars($user_info['first_name']) ?> (@<?= htmlspecialchars($user_info['username']) ?>) di Bot <?= htmlspecialchars($bot_info['name']) ?></h1>
        </header>
        <div class="chat-window">
            <div class="message-container">
                <?php foreach ($messages as $message): ?>
                    <div class="message <?= $message['direction'] ?>">
                        <?= nl2br(htmlspecialchars($message['text'])) ?>
                        <?php if (!empty($message['raw_data'])): ?>
                            <div class="message-meta">
                                <span class="json-toggle" onclick="toggleJson(this)">Tampilkan JSON</span>
                            </div>
                            <pre class="raw-json"><?= htmlspecialchars(json_encode(json_decode($message['raw_data']), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="reply-form">
            <form action="chat.php?user_id=<?= $user_id ?>&bot_id=<?= $bot_id ?>" method="post">
                <textarea name="reply_text" rows="3" placeholder="Ketik balasan Anda..." required></textarea>
                <button type="submit" name="reply_message">Kirim</button>
            </form>
        </div>
    </div>
    <script>
        function toggleJson(element) {
            const pre = element.closest('.message').querySelector('.raw-json');
            if (pre.style.display === 'none' || pre.style.display === '') {
                pre.style.display = 'block';
                element.textContent = 'Sembunyikan JSON';
            } else {
                pre.style.display = 'none';
                element.textContent = 'Tampilkan JSON';
            }
        }
    </script>
</body>
</html>
