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

// Ambil semua pesan untuk user dan bot ini, gabungkan dengan data media jika ada
$sql = "
    SELECT
        m.*,
        mf.type as media_type,
        mf.file_name as media_file_name,
        mf.id as media_id,
        mf.media_group_id
    FROM messages m
    LEFT JOIN media_files mf ON m.telegram_message_id = mf.message_id
    WHERE m.user_id = ? AND m.bot_id = ?
    ORDER BY m.created_at ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id, $bot_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kelompokkan pesan berdasarkan media_group_id
$grouped_messages = [];
$processed_message_ids = []; // Lacak ID pesan yang sudah masuk ke dalam grup

foreach ($messages as $message) {
    // Jika pesan ini sudah diproses sebagai bagian dari grup, lewati
    if (in_array($message['id'], $processed_message_ids)) {
        continue;
    }

    // Jika pesan memiliki media_group_id, cari semua anggota grupnya
    if (!empty($message['media_group_id'])) {
        $current_group_id = $message['media_group_id'];
        $group = [
            'type' => 'media_group',
            'items' => [],
            'direction' => $message['direction'], // Arah grup sama dengan pesan pertama
            'bot_id' => $message['bot_id'],
            'media_group_id' => $current_group_id
        ];

        // Iterasi ulang untuk menemukan semua item dalam grup ini
        foreach ($messages as $item) {
            if (($item['media_group_id'] ?? null) === $current_group_id) {
                $group['items'][] = $item;
                $processed_message_ids[] = $item['id']; // Tandai sebagai sudah diproses
            }
        }
        $grouped_messages[] = $group;
    } else {
        // Jika tidak punya grup, ini adalah pesan tunggal
        $message['type'] = 'single';
        $grouped_messages[] = $message;
    }
}


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
        .media-display { display: flex; align-items: center; gap: 10px; margin-bottom: 5px; }
        .media-icon { font-size: 1.8em; }
        .caption { margin-top: 5px; font-style: italic; }
        .message-meta .btn-forward { font-size: 0.9em; padding: 2px 8px; background-color: #17a2b8; color: white; border: none; border-radius: 3px; cursor: pointer; margin-right: 10px; }
        .media-group-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 5px; }
        .media-group-item { text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <nav><a href="index.php?bot_id=<?= $telegram_bot_id ?>">&larr; Kembali ke Daftar Percakapan</a> | <a href="roles.php">Manajemen Peran</a> | <a href="media_logs.php">Log Media</a></nav>
            <h1>Chat dengan <?= htmlspecialchars($user_info['first_name']) ?> (@<?= htmlspecialchars($user_info['username']) ?>) di Bot <?= htmlspecialchars($bot_info['name']) ?></h1>
        </header>
        <div class="chat-window">
            <div class="message-container">
                <?php foreach ($grouped_messages as $item): ?>
                    <div class="message <?= $item['direction'] ?>">
                        <?php if ($item['type'] === 'media_group'): // Render a media group ?>
                            <div class="media-group-container">
                                <?php foreach ($item['items'] as $media_item): ?>
                                    <div class="media-group-item">
                                        <span class="media-icon">
                                            <?php
                                                $icons = ['photo' => '🖼️', 'video' => '🎬', 'audio' => '🎵', 'voice' => '🎤', 'document' => '📄', 'animation' => '✨', 'video_note' => '📹'];
                                                echo $icons[$media_item['media_type']] ?? '❔';
                                            ?>
                                        </span>
                                        <div><small><?= htmlspecialchars($media_item['media_type']) ?></small></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php
                                // Show the caption from the first item in the group
                                $first_item_caption = $item['items'][0]['text'] ?? null;
                                if ($first_item_caption):
                            ?>
                                <p class="caption"><?= nl2br(htmlspecialchars($first_item_caption)) ?></p>
                            <?php endif; ?>
                            <div class="message-meta">
                                <button class="btn-forward"
                                        data-group-id="<?= htmlspecialchars($item['media_group_id']) ?>"
                                        data-bot-id="<?= htmlspecialchars($item['bot_id']) ?>">
                                    Teruskan Grup
                                </button>
                                <span class="json-toggle" onclick="toggleJson(this)">Tampilkan JSON</span>
                            </div>
                            <pre class="raw-json"><?= htmlspecialchars(json_encode(array_column($item['items'], 'raw_data'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>

                        <?php else: // Render a single message (text or single media) ?>
                            <?php if ($item['media_type']): ?>
                                <div class="media-display">
                                    <span class="media-icon">
                                        <?php
                                            $icons = ['photo' => '🖼️', 'video' => '🎬', 'audio' => '🎵', 'voice' => '🎤', 'document' => '📄', 'animation' => '✨', 'video_note' => '📹'];
                                            echo $icons[$item['media_type']] ?? '❔';
                                        ?>
                                    </span>
                                    <span class="media-info">
                                        <strong><?= htmlspecialchars(ucfirst($item['media_type'])) ?></strong>
                                        <?= $item['media_file_name'] ? '<br><small>' . htmlspecialchars($item['media_file_name']) . '</small>' : '' ?>
                                    </span>
                                </div>
                                <?php if (!empty($item['text'])): ?>
                                    <p class="caption"><?= nl2br(htmlspecialchars($item['text'])) ?></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <?= nl2br(htmlspecialchars($item['text'])) ?>
                            <?php endif; ?>

                            <?php if (!empty($item['raw_data'])): ?>
                                <div class="message-meta">
                                    <?php if ($item['direction'] === 'incoming' && $item['media_type']): ?>
                                        <button class="btn-forward"
                                                data-group-id="<?= htmlspecialchars('single_' . $item['media_id']) ?>"
                                                data-bot-id="<?= htmlspecialchars($item['bot_id']) ?>">
                                            Teruskan
                                        </button>
                                    <?php endif; ?>
                                    <span class="json-toggle" onclick="toggleJson(this)">Tampilkan JSON</span>
                                </div>
                                <pre class="raw-json"><?= htmlspecialchars(json_encode(json_decode($item['raw_data']), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
                            <?php endif; ?>
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

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.btn-forward').forEach(button => {
                button.addEventListener('click', async function() {
                    const groupId = this.dataset.groupId;
                    const botId = this.dataset.botId;

                    if (!confirm('Anda yakin ingin meneruskan media ini ke semua admin?')) {
                        return;
                    }

                    const originalText = this.textContent;
                    this.textContent = 'Mengirim...';
                    this.disabled = true;

                    const formData = new FormData();
                    formData.append('group_id', groupId);
                    formData.append('bot_id', botId);

                    try {
                        const response = await fetch('forward_manager.php', {
                            method: 'POST',
                            body: formData
                        });

                        const result = await response.json();
                        if (!response.ok) {
                            throw new Error(result.message || 'Server error');
                        }

                        alert('Hasil: ' + result.message);

                    } catch (error) {
                        alert('Gagal melakukan permintaan: ' + error.message);
                    } finally {
                        this.textContent = originalText;
                        this.disabled = false;
                    }
                });
            });
        });
    </script>
</body>
</html>
