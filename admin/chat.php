<?php
/**
 * Halaman Percakapan Pribadi (Admin) - Tampilan Tabel.
 *
 * Halaman ini menampilkan riwayat percakapan dalam bentuk tabel dengan pagination.
 */
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/TelegramAPI.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("Koneksi database gagal.");
}

// Validasi input
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$bot_id = isset($_GET['bot_id']) ? (int)$_GET['bot_id'] : 0;

if (!$user_id || !$bot_id) {
    header("Location: index.php");
    exit;
}

// Ambil info pengguna dan bot
$stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$user_info = $stmt_user->fetch();

$stmt_bot = $pdo->prepare("SELECT * FROM bots WHERE id = ?");
$stmt_bot->execute([$bot_id]);
$bot_info = $stmt_bot->fetch();

if (!$user_info || !$bot_info) {
    die("Pengguna atau bot tidak ditemukan.");
}

// Logika Balasan Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $reply_text = trim($_POST['reply_text']);
    if (!empty($reply_text)) {
        $telegram_api = new TelegramAPI($bot_info['token']);
        $result = $telegram_api->sendMessage($user_info['telegram_id'], $reply_text);

        if ($result && $result['ok']) {
            $raw_data_json = json_encode($result['result']);
            $stmt = $pdo->prepare(
                "INSERT INTO messages (user_id, bot_id, telegram_message_id, text, raw_data, direction, telegram_timestamp)
                 VALUES (?, ?, ?, ?, ?, 'outgoing', NOW())"
            );
            $stmt->execute([$user_id, $bot_id, $result['result']['message_id'], $reply_text, $raw_data_json]);
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// --- LOGIKA PAGINATION ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50; // Jumlah pesan per halaman
$offset = ($page - 1) * $limit;

// Query untuk menghitung total pesan
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND bot_id = ?");
$count_stmt->execute([$user_id, $bot_id]);
$total_messages = $count_stmt->fetchColumn();
$total_pages = ceil($total_messages / $limit);

// Query untuk mengambil pesan dengan limit dan offset
$sql = "
    SELECT
        m.*,
        mf.type as media_type
    FROM messages m
    LEFT JOIN media_files mf ON m.telegram_message_id = mf.message_id AND m.chat_id = mf.chat_id
    WHERE m.user_id = ? AND m.bot_id = ?
    ORDER BY m.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $pdo->prepare($sql);
// Bind values sebagai integer
$stmt->bindValue(1, $user_id, PDO::PARAM_INT);
$stmt->bindValue(2, $bot_id, PDO::PARAM_INT);
$stmt->bindValue(3, $limit, PDO::PARAM_INT);
$stmt->bindValue(4, $offset, PDO::PARAM_INT);
$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
// --- AKHIR LOGIKA PAGINATION ---

$telegram_bot_id_for_back_link = explode(':', $bot_info['token'])[0];
$page_title = "Chat dengan " . htmlspecialchars($user_info['first_name']);
require_once __DIR__ . '/../partials/header.php';
?>

<div class="chat-container">
    <div class="chat-header">
        <a href="index.php?bot_id=<?= $telegram_bot_id_for_back_link ?>" class="btn">&larr; Kembali</a>
        <h3>
            Riwayat Chat dengan <?= htmlspecialchars($user_info['first_name'] ?? '') ?>
        </h3>
        <p>Total Pesan: <?= $total_messages ?></p>
    </div>

    <div class="table-responsive">
        <table class="chat-log-table">
            <thead>
                <tr>
                    <th class="col-id">ID</th>
                    <th class="col-time">Waktu</th>
                    <th class="col-direction">Arah</th>
                    <th class="col-type">Tipe</th>
                    <th class="col-content">Konten</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($messages)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">Tidak ada pesan ditemukan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <tr>
                            <td class="col-id"><?= $msg['id'] ?></td>
                            <td class="col-time"><?= htmlspecialchars($msg['created_at']) ?></td>
                            <td class="col-direction">
                                <span class="direction-<?= htmlspecialchars($msg['direction']) ?>">
                                    <?= htmlspecialchars(ucfirst($msg['direction'])) ?>
                                </span>
                            </td>
                            <td class="col-type"><?= htmlspecialchars($msg['media_type'] ?? 'Teks') ?></td>
                            <td class="col-content">
                                <?php if (!empty($msg['text'])): ?>
                                    <p><?= nl2br(htmlspecialchars($msg['text'])) ?></p>
                                <?php endif; ?>
                                <span class="json-toggle" onclick="toggleJson(this, 'msg-json-<?= $msg['id'] ?>')">
                                    Lihat Raw Data
                                </span>
                                <pre class="raw-json" id="msg-json-<?= $msg['id'] ?>"><?= htmlspecialchars(json_encode(json_decode($msg['raw_data']), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?user_id=<?= $user_id ?>&bot_id=<?= $bot_id ?>&page=<?= $page - 1 ?>">&laquo; Sebelumnya</a>
        <?php else: ?>
            <span class="disabled">&laquo; Sebelumnya</span>
        <?php endif; ?>

        <span class="current-page">Halaman <?= $page ?> dari <?= $total_pages ?></span>

        <?php if ($page < $total_pages): ?>
            <a href="?user_id=<?= $user_id ?>&bot_id=<?= $bot_id ?>&page=<?= $page + 1 ?>">Berikutnya &raquo;</a>
        <?php else: ?>
            <span class="disabled">Berikutnya &raquo;</span>
        <?php endif; ?>
    </div>

    <div class="chat-reply-form">
        <form action="chat.php?user_id=<?= $user_id ?>&bot_id=<?= $bot_id ?>" method="post">
            <textarea name="reply_text" rows="3" placeholder="Ketik balasan Anda..." required></textarea>
            <button type="submit" name="reply_message" class="btn">Kirim</button>
        </form>
    </div>
</div>

<script>
function toggleJson(element, id) {
    const pre = document.getElementById(id);
    if (pre.style.display === 'none' || pre.style.display === '') {
        pre.style.display = 'block';
        element.textContent = 'Sembunyikan JSON';
    } else {
        pre.style.display = 'none';
        element.textContent = 'Tampilkan JSON';
    }
}
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
