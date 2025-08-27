<?php
/**
 * Halaman Utama Panel Admin (Dasbor Percakapan).
 *
 * Halaman ini menampilkan antarmuka dua kolom untuk menjelajahi percakapan:
 * - Sidebar Kiri: Daftar semua bot yang terdaftar.
 * - Area Utama: Daftar percakapan untuk bot yang dipilih, dengan opsi filter pengguna.
 */
require_once __DIR__ . '/auth.php'; // Handle otentikasi
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
$bots = $pdo->query("SELECT id, first_name FROM bots ORDER BY first_name ASC")->fetchAll();

// Dapatkan parameter dari URL
$selected_bot_id = isset($_GET['bot_id']) ? (int)$_GET['bot_id'] : null;
$search_user = trim($_GET['search_user'] ?? '');

$conversations = [];
$channel_chats = [];
$bot_exists = false;

if ($selected_bot_id) {
    // Verifikasi bot ada
    $stmt = $pdo->prepare("SELECT 1 FROM bots WHERE id = ?");
    $stmt->execute([$selected_bot_id]);
    $bot_exists = $stmt->fetchColumn();

    if ($bot_exists) {
        // --- Ambil percakapan PRIBADI ---
        $params = [$selected_bot_id];
        $user_where_clause = '';
        if (!empty($search_user)) {
            $user_where_clause = "AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.id = ?)";
            $params = array_merge($params, ["%$search_user%", "%$search_user%", "%$search_user%", $search_user]);
        }

        $sql_users = "
            SELECT u.id as telegram_id, u.first_name, u.username,
                   (SELECT text FROM messages m WHERE m.user_id = u.id AND m.bot_id = r.bot_id ORDER BY m.id DESC LIMIT 1) as last_message,
                   (SELECT telegram_timestamp FROM messages m WHERE m.user_id = u.id AND m.bot_id = r.bot_id ORDER BY m.id DESC LIMIT 1) as last_message_time
            FROM users u
            JOIN rel_user_bot r ON u.id = r.user_id
            WHERE r.bot_id = ? {$user_where_clause}
            ORDER BY last_message_time DESC";

        $stmt_users = $pdo->prepare($sql_users);
        $stmt_users->execute($params);
        $conversations = $stmt_users->fetchAll();

        // --- UNTUK DEBUGGING ---
        $debug_queries['User Conversations Query'] = [
            'sql' => $stmt_users->queryString,
            'params' => $params
        ];
        // --- AKHIR UNTUK DEBUGGING ---

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
            $stmt_channels->execute([$selected_bot_id]);
            $channel_chats = $stmt_channels->fetchAll();

            // --- UNTUK DEBUGGING ---
            $debug_queries['Channel Conversations Query'] = [
                'sql' => $stmt_channels->queryString,
                'params' => [$selected_bot_id]
            ];
            // --- AKHIR UNTUK DEBUGGING ---
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
                        $link_params = ['bot_id' => $bot['id']];
                        if (!empty($search_user)) {
                            $link_params['search_user'] = $search_user;
                        }
                    ?>
                    <a href="index.php?<?= http_build_query($link_params) ?>" class="<?= ($selected_bot_id == $bot['id']) ? 'active' : '' ?>">
                        <?= htmlspecialchars($bot['first_name'] ?? 'Bot Tanpa Nama') ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <main class="conv-main">
        <div class="search-form" style="margin-bottom: 20px;">
            <form action="index.php" method="get">
                <?php if ($selected_bot_id): ?>
                    <input type="hidden" name="bot_id" value="<?= htmlspecialchars($selected_bot_id) ?>">
                <?php endif; ?>
                <input type="text" name="search_user" placeholder="Cari percakapan pengguna..." value="<?= htmlspecialchars($search_user) ?>" style="width: 300px; display: inline-block;">
                <button type="submit" class="btn">Cari</button>
                <?php if(!empty($search_user)): ?>
                    <a href="index.php?bot_id=<?= $selected_bot_id ?>" class="btn btn-delete">Hapus Filter</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($selected_bot_id): ?>
            <?php if (!$bot_exists): ?>
                <div class="alert alert-danger">Bot dengan ID <?= htmlspecialchars($selected_bot_id) ?> tidak ditemukan.</div>
            <?php else: ?>

                <h3>Percakapan Pengguna</h3>
                <ul class="conv-list">
                    <?php if (empty($conversations)): ?>
                        <p>Tidak ada percakapan yang cocok dengan kriteria.</p>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): ?>
                            <li class="conv-card">
                                <a href="chat.php?telegram_id=<?= $conv['telegram_id'] ?>&bot_id=<?= $selected_bot_id ?>">
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
                                <a href="channel_chat.php?chat_id=<?= $chat['chat_id'] ?>&bot_id=<?= $selected_bot_id ?>">
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

<?php if (!empty($debug_queries)): ?>
<div class="debug-section" style="margin-top: 40px;">
    <button onclick="document.getElementById('debug-content').style.display = document.getElementById('debug-content').style.display === 'none' ? 'block' : 'none';" class="btn btn-secondary">
        Tampilkan/Sembunyikan Info Debug Kueri
    </button>
    <div id="debug-content" style="display:none; margin-top: 10px; padding: 15px; border: 1px solid #ccc; background-color: #f8f9fa;">
        <h4>Kueri yang Dieksekusi</h4>
        <?php foreach ($debug_queries as $title => $query_info): ?>
            <h5><?= htmlspecialchars($title) ?></h5>
            <pre><code class="language-sql"><?= htmlspecialchars($query_info['sql']) ?></code></pre>
            <h6>Parameter:</h6>
            <pre><code><?= htmlspecialchars(print_r($query_info['params'], true)) ?></code></pre>
            <hr>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
