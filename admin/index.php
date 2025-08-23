<?php
/**
 * Halaman Utama Panel Admin (Dasbor Percakapan).
 *
 * Halaman ini menampilkan antarmuka dua kolom untuk menjelajahi percakapan:
 * - Sidebar Kiri: Daftar semua bot yang terdaftar.
 * - Area Utama: Daftar percakapan untuk bot yang dipilih, dengan opsi filter pengguna.
 */
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';

// Fungsi helper untuk mendapatkan inisial dari nama
function get_initials($name) {
    $words = explode(' ', $name);
    $initials = '';
    $wordCount = 0;
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper($word[0]);
            $wordCount++;
        }
        if ($wordCount >= 2) {
            break;
        }
    }
    return $initials ?: '?';
}

$pdo = get_db_connection();
if (!$pdo) {
    die("Koneksi database gagal.");
}

// Pengecekan awal dan setup database otomatis jika diperlukan
try {
    if (!check_tables_exist($pdo)) {
        if (setup_database($pdo)) {
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            $setup_error_message = "<strong>Gagal melakukan setup database secara otomatis!</strong><br>" .
                "Pastikan kredensial database di `config.php` sudah benar dan server database berjalan.<br><br>" .
                "Anda juga bisa mencoba mengimpor file <code>setup.sql</code> secara manual.";
            include __DIR__ . '/../core/templates/setup_error.php';
            exit;
        }
    }
} catch (PDOException $e) {
    die("Error saat memeriksa database: " . $e->getMessage());
}

// Ambil semua bot untuk sidebar
$bots = $pdo->query("SELECT id, first_name, token FROM bots ORDER BY first_name ASC")->fetchAll();

// Dapatkan parameter dari URL
$selected_telegram_bot_id = $_GET['bot_id'] ?? null;
$search_user = trim($_GET['search_user'] ?? '');

$conversations = [];
$channel_chats = [];
$internal_bot_id = null;

if ($selected_telegram_bot_id) {
    // Dapatkan ID internal bot
    $stmt = $pdo->prepare("SELECT id FROM bots WHERE token LIKE ?");
    $stmt->execute([$selected_telegram_bot_id . ':%']);
    $internal_bot_id = $stmt->fetchColumn();

    if ($internal_bot_id) {
        // --- Ambil percakapan PRIBADI ---
        $params = [$internal_bot_id];
        $user_where_clause = '';
        if (!empty($search_user)) {
            $user_where_clause = "AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ?)";
            $params = array_merge($params, ["%$search_user%", "%$search_user%", "%$search_user%"]);
        }

        $sql_users = "
            SELECT u.id as user_internal_id, u.telegram_id, u.first_name, u.username,
                (SELECT text FROM messages WHERE user_id = u.id AND bot_id = r.bot_id ORDER BY id DESC LIMIT 1) as last_message,
                (SELECT telegram_timestamp FROM messages WHERE user_id = u.id AND bot_id = r.bot_id ORDER BY id DESC LIMIT 1) as last_message_time
            FROM users u
            JOIN rel_user_bot r ON u.id = r.user_id
            WHERE r.bot_id = ? {$user_where_clause}
            ORDER BY last_message_time DESC";

        $stmt_users = $pdo->prepare($sql_users);
        $stmt_users->execute($params);
        $conversations = $stmt_users->fetchAll();

        // --- Ambil percakapan CHANNEL dan GRUP (hanya jika tidak ada filter user) ---
        if (empty($search_user)) {
            $stmt_channels = $pdo->prepare(
                "SELECT DISTINCT m.chat_id,
                    (SELECT raw_data FROM messages WHERE chat_id = m.chat_id AND bot_id = m.bot_id ORDER BY id DESC LIMIT 1) as last_message_raw,
                    (SELECT text FROM messages WHERE chat_id = m.chat_id AND bot_id = m.bot_id ORDER BY id DESC LIMIT 1) as last_message,
                    (SELECT telegram_timestamp FROM messages WHERE chat_id = m.chat_id AND bot_id = m.bot_id ORDER BY id DESC LIMIT 1) as last_message_time
                FROM messages m
                WHERE m.bot_id = ? AND m.chat_id < 0 ORDER BY last_message_time DESC"
            );
            $stmt_channels->execute([$internal_bot_id]);
            $channel_chats = $stmt_channels->fetchAll();
        }
    }
}

$page_title = 'Percakapan';
require_once __DIR__ . '/../partials/header.php';
?>

<div class="conv-layout">
    <aside class="conv-sidebar">
        <div class="conv-sidebar-header">
            <h2>Pilih Bot</h2>
        </div>
        <div class="conv-bot-list">
            <?php if (empty($bots)): ?>
                <p style="padding: 15px;">Tidak ada bot ditemukan.</p>
            <?php else: ?>
                <?php foreach ($bots as $bot): ?>
                    <?php
                        $telegram_bot_id_loop = explode(':', $bot['token'])[0];
                        $link_params = ['bot_id' => $telegram_bot_id_loop];
                        if (!empty($search_user)) {
                            $link_params['search_user'] = $search_user;
                        }
                    ?>
                    <a href="index.php?<?= http_build_query($link_params) ?>" class="<?= ($selected_telegram_bot_id == $telegram_bot_id_loop) ? 'active' : '' ?>">
                        <?= htmlspecialchars($bot['first_name'] ?? 'Bot Tanpa Nama') ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <main class="conv-main">
        <div class="search-form" style="margin-bottom: 20px;">
            <form action="index.php" method="get">
                <?php if ($selected_telegram_bot_id): ?>
                    <input type="hidden" name="bot_id" value="<?= htmlspecialchars($selected_telegram_bot_id) ?>">
                <?php endif; ?>
                <input type="text" name="search_user" placeholder="Cari percakapan pengguna..." value="<?= htmlspecialchars($search_user) ?>" style="width: 300px; display: inline-block;">
                <button type="submit" class="btn">Cari</button>
                <?php if(!empty($search_user)): ?>
                    <a href="index.php?bot_id=<?= $selected_telegram_bot_id ?>" class="btn btn-delete">Hapus Filter</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($selected_telegram_bot_id): ?>
            <?php if (!$internal_bot_id): ?>
                <div class="alert alert-danger">Bot dengan ID <?= htmlspecialchars($selected_telegram_bot_id) ?> tidak ditemukan.</div>
            <?php else: ?>

                <h3>Percakapan Pengguna</h3>
                <ul class="conv-list">
                    <?php if (empty($conversations)): ?>
                        <p>Tidak ada percakapan yang cocok dengan kriteria.</p>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): ?>
                            <li class="conv-card">
                                <a href="chat.php?user_id=<?= $conv['user_internal_id'] ?>&bot_id=<?= $internal_bot_id ?>">
                                    <div class="conv-avatar"><?= get_initials($conv['first_name'] ?? '?') ?></div>
                                    <div class="conv-details">
                                        <div class="conv-header">
                                            <span class="conv-name"><?= htmlspecialchars($conv['first_name'] ?? 'Tanpa Nama') ?></span>
                                            <span class="conv-time"><?= htmlspecialchars(date('H:i', strtotime($conv['last_message_time'] ?? 'now'))) ?></span>
                                        </div>
                                        <div class="conv-message"><?= htmlspecialchars($conv['last_message'] ?? '...') ?></div>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>

                <?php if(empty($search_user)): // Hanya tampilkan channel jika tidak sedang mencari user ?>
                <h3 style="margin-top: 40px;">Percakapan Channel & Grup</h3>
                <ul class="conv-list">
                     <?php if (empty($channel_chats)): ?>
                        <p>Belum ada pesan dari channel atau grup untuk bot ini.</p>
                    <?php else: ?>
                        <?php foreach ($channel_chats as $chat): ?>
                            <?php
                                $chat_title = 'Grup/Channel Tanpa Nama';
                                if (!empty($chat['last_message_raw'])) {
                                    $raw = json_decode($chat['last_message_raw'], true);
                                    $chat_title = $raw['channel_post']['chat']['title'] ?? $raw['message']['chat']['title'] ?? $chat_title;
                                }
                            ?>
                            <li class="conv-card">
                                <a href="channel_chat.php?chat_id=<?= $chat['chat_id'] ?>&bot_id=<?= $internal_bot_id ?>">
                                    <div class="conv-avatar" style="background-color: #6c757d;"><?= get_initials($chat_title) ?></div>
                                    <div class="conv-details">
                                        <div class="conv-header">
                                            <span class="conv-name"><?= htmlspecialchars($chat_title) ?></span>
                                            <span class="conv-time"><?= htmlspecialchars(date('H:i', strtotime($chat['last_message_time'] ?? 'now'))) ?></span>
                                        </div>
                                        <div class="conv-message"><?= htmlspecialchars($chat['last_message'] ?? '...') ?></div>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
                <?php endif; ?>

            <?php endif; ?>
        <?php else: ?>
            <div style="text-align: center; padding-top: 50px; color: #6c757d;">
                <h2>Selamat Datang</h2>
                <p>Silakan pilih bot dari sidebar kiri untuk melihat percakapannya.</p>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
