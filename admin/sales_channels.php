<?php
/**
 * Halaman Manajemen Channel Jualan (Admin).
 *
 * Halaman ini menampilkan daftar semua channel jualan yang telah
 * didaftarkan oleh para penjual di sistem.
 */
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("Koneksi database gagal.");
}

// --- LOGIKA PENGAMBILAN DATA ---
$sql = "
    SELECT
        ssc.channel_id,
        ssc.discussion_group_id,
        ssc.is_active,
        ssc.created_at,
        u.first_name as seller_name,
        u.username as seller_username,
        b.username as bot_username
    FROM
        seller_sales_channels ssc
    LEFT JOIN
        users u ON ssc.seller_user_id = u.id
    LEFT JOIN
        bots b ON ssc.bot_id = b.id
    ORDER BY
        ssc.created_at DESC
";
$stmt = $pdo->query($sql);
$sales_channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Ambil Nama Channel/Grup dari Telegram API ---
// Ini bisa lambat jika ada banyak channel. Caching akan menjadi ide bagus di masa depan.
require_once __DIR__ . '/../core/TelegramAPI.php';

foreach ($sales_channels as $key => $channel) {
    $bot_token = get_bot_token($pdo, $channel['bot_id']);
    if ($bot_token) {
        try {
            $telegram_api = new TelegramAPI($bot_token);

            // Ambil nama channel
            $chat_info = $telegram_api->getChat($channel['channel_id']);
            $sales_channels[$key]['channel_title'] = ($chat_info && $chat_info['ok']) ? $chat_info['result']['title'] : 'Tidak Ditemukan';

            // Ambil nama grup diskusi
            $group_info = $telegram_api->getChat($channel['discussion_group_id']);
            $sales_channels[$key]['group_title'] = ($group_info && $group_info['ok']) ? $group_info['result']['title'] : 'Tidak Ditemukan';

        } catch (Exception $e) {
            $sales_channels[$key]['channel_title'] = 'Error API';
            $sales_channels[$key]['group_title'] = 'Error API';
        }
    } else {
        $sales_channels[$key]['channel_title'] = 'Bot Tidak Valid';
        $sales_channels[$key]['group_title'] = 'Bot Tidak Valid';
    }
}


$page_title = 'Manajemen Channel Jualan';
require_once __DIR__ . '/../partials/header.php';
?>

<h1>Manajemen Channel Jualan</h1>
<p>Halaman ini menampilkan semua channel yang telah didaftarkan oleh penjual untuk berjualan.</p>

<div class="table-responsive">
    <table class="chat-log-table">
        <thead>
            <tr>
                <th>Nama Channel</th>
                <th>Nama Grup Diskusi</th>
                <th>Pemilik Channel</th>
                <th>Bot Admin</th>
                <th>Status</th>
                <th>Tanggal Didaftarkan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($sales_channels)): ?>
                <tr>
                    <td colspan="6" style="text-align:center;">Tidak ada channel jualan yang terdaftar.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($sales_channels as $channel): ?>
                    <tr>
                        <td><?= htmlspecialchars($channel['channel_title']) ?><br><code><?= htmlspecialchars($channel['channel_id']) ?></code></td>
                        <td><?= htmlspecialchars($channel['group_title']) ?><br><code><?= htmlspecialchars($channel['discussion_group_id']) ?></code></td>
                        <td><?= htmlspecialchars($channel['seller_name'] . ' (@' . $channel['seller_username'] . ')') ?></td>
                        <td>@<?= htmlspecialchars($channel['bot_username']) ?></td>
                        <td><?= $channel['is_active'] ? '<span class="status-active">Aktif</span>' : '<span class="status-blocked">Tidak Aktif</span>' ?></td>
                        <td><?= htmlspecialchars($channel['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
require_once __DIR__ . '/../partials/footer.php';
?>
