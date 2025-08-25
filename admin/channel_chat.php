<?php
/**
 * Halaman Riwayat Pesan Channel/Grup (Admin) - Tampilan Tabel.
 *
 * Halaman ini menampilkan riwayat pesan untuk channel/grup dalam bentuk tabel
 * dengan pagination dan fungsionalitas hapus massal.
 */
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/TelegramAPI.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("Koneksi database gagal.");
}

// Validasi input
$chat_id = isset($_GET['chat_id']) ? (int)$_GET['chat_id'] : 0;
$telegram_bot_id = isset($_GET['bot_id']) ? (int)$_GET['bot_id'] : 0;

if (!$chat_id || !$telegram_bot_id) {
    header("Location: index.php");
    exit;
}

// Ambil info bot
$stmt_bot = $pdo->prepare("SELECT * FROM bots WHERE telegram_bot_id = ?");
$stmt_bot->execute([$telegram_bot_id]);
$bot_info = $stmt_bot->fetch();

if (!$bot_info) {
    die("Bot tidak ditemukan.");
}

// Ambil info chat dari pesan terakhir untuk judul halaman
$stmt_chat_info = $pdo->prepare("SELECT raw_data FROM messages WHERE chat_id = ? AND bot_id = ? ORDER BY id DESC LIMIT 1");
$stmt_chat_info->execute([$chat_id, $telegram_bot_id]);
$last_message_raw = $stmt_chat_info->fetchColumn();
$chat_title = "Chat ID: $chat_id";
if ($last_message_raw) {
    $raw = json_decode($last_message_raw, true);
    $chat_title = $raw['channel_post']['chat']['title'] ?? $raw['message']['chat']['title'] ?? $chat_title;
}

// --- LOGIKA PAGINATION ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE chat_id = ? AND bot_id = ?");
$count_stmt->execute([$chat_id, $telegram_bot_id]);
$total_messages = $count_stmt->fetchColumn();
$total_pages = ceil($total_messages / $limit);

// Query untuk mengambil pesan dengan limit dan offset
$sql = "SELECT m.*, mf.type as media_type, u.first_name as sender_first_name
        FROM messages m
        LEFT JOIN media_files mf ON m.telegram_message_id = mf.message_id AND m.chat_id = mf.chat_id
        LEFT JOIN users u ON m.user_id = u.telegram_id
        WHERE m.chat_id = ? AND m.bot_id = ?
        ORDER BY m.created_at DESC
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(1, $chat_id, PDO::PARAM_INT);
$stmt->bindValue(2, $telegram_bot_id, PDO::PARAM_INT);
$stmt->bindValue(3, $limit, PDO::PARAM_INT);
$stmt->bindValue(4, $offset, PDO::PARAM_INT);
$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
// --- AKHIR LOGIKA PAGINATION ---

$page_title = "Riwayat Chat: " . htmlspecialchars($chat_title);
require_once __DIR__ . '/../partials/header.php';
?>

<div class="chat-container">
    <div class="chat-header">
        <a href="index.php?bot_id=<?= $telegram_bot_id ?>" class="btn">&larr; Kembali</a>
        <h3>Riwayat Chat: <?= htmlspecialchars($chat_title) ?></h3>
        <p>Total Pesan: <?= $total_messages ?></p>
    </div>

    <form id="bulk-action-form" action="delete_messages_handler.php" method="post">
        <!-- Kirim chat_id karena ini bukan halaman user-specific -->
        <input type="hidden" name="chat_id" value="<?= $chat_id ?>">
        <input type="hidden" name="bot_id" value="<?= $telegram_bot_id ?>">
        <input type="hidden" name="source_page" value="channel_chat">


        <div class="bulk-actions-bar">
            <button type="submit" name="action" value="delete_db" class="btn btn-warning" disabled>Hapus dari DB</button>
            <button type="submit" name="action" value="delete_telegram" class="btn btn-danger" disabled>Hapus dari Telegram</button>
            <button type="submit" name="action" value="delete_both" class="btn btn-danger" disabled>Hapus dari Keduanya</button>
            <span id="selection-counter" style="margin-left: 10px;">0 item dipilih</span>
        </div>

        <div class="table-responsive">
            <table class="chat-log-table">
                <thead>
                    <tr>
                        <th class="col-checkbox"><input type="checkbox" id="select-all-checkbox"></th>
                        <th class="col-id">ID</th>
                        <th class="col-time">Waktu</th>
                        <th class="col-direction">Pengirim</th>
                        <th class="col-type">Tipe</th>
                        <th class="col-content">Konten</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($messages)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">Tidak ada pesan ditemukan.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                            <tr>
                                <td class="col-checkbox"><input type="checkbox" class="message-checkbox" name="message_ids[]" value="<?= $msg['id'] ?>"></td>
                                <td class="col-id"><?= $msg['id'] ?></td>
                                <td class="col-time"><?= htmlspecialchars($msg['created_at']) ?></td>
                                <td class="col-direction">
                                    <span class="direction-<?= htmlspecialchars($msg['direction']) ?>">
                                        <?php
                                            if ($msg['direction'] === 'outgoing') {
                                                echo htmlspecialchars($bot_info['first_name']);
                                            } else {
                                                echo htmlspecialchars($msg['sender_first_name'] ?? 'Channel Post');
                                            }
                                        ?>
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
    </form>

    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?chat_id=<?= $chat_id ?>&bot_id=<?= $bot_id ?>&page=<?= $page - 1 ?>">&laquo; Sebelumnya</a>
        <?php else: ?>
            <span class="disabled">&laquo; Sebelumnya</span>
        <?php endif; ?>

        <span class="current-page">Halaman <?= $page ?> dari <?= $total_pages ?></span>

        <?php if ($page < $total_pages): ?>
            <a href="?chat_id=<?= $chat_id ?>&bot_id=<?= $bot_id ?>&page=<?= $page + 1 ?>">Berikutnya &raquo;</a>
        <?php else: ?>
            <span class="disabled">Berikutnya &raquo;</span>
        <?php endif; ?>
    </div>

     <div class="chat-reply-form" style="text-align: center; color: #888; padding: 20px 0;">
        <p>Ini adalah tampilan read-only untuk pesan channel/grup.</p>
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

document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    const messageCheckboxes = document.querySelectorAll('.message-checkbox');
    const actionButtons = document.querySelectorAll('.bulk-actions-bar button');
    const selectionCounter = document.getElementById('selection-counter');
    const form = document.getElementById('bulk-action-form');

    function updateButtonState() {
        const checkedCount = document.querySelectorAll('.message-checkbox:checked').length;
        const anyChecked = checkedCount > 0;
        actionButtons.forEach(button => {
            button.disabled = !anyChecked;
        });
        selectionCounter.textContent = checkedCount + ' item dipilih';
    }

    selectAllCheckbox.addEventListener('change', function() {
        messageCheckboxes.forEach(checkbox => {
            checkbox.checked = selectAllCheckbox.checked;
        });
        updateButtonState();
    });

    messageCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (!this.checked) {
                selectAllCheckbox.checked = false;
            } else {
                if (document.querySelectorAll('.message-checkbox:checked').length === messageCheckboxes.length) {
                    selectAllCheckbox.checked = true;
                }
            }
            updateButtonState();
        });
    });

    actionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const action = this.value;
            if (!confirm(`Anda yakin ingin menjalankan aksi "${action}" pada item yang dipilih?`)) {
                e.preventDefault();
            }
        });
    });

    updateButtonState();
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
