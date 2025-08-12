<?php
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("Koneksi database gagal.");
}

// --- Logic untuk Block/Unblock ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_block') {
    $user_id_to_toggle = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $bot_id_to_toggle = isset($_POST['bot_id']) ? (int)$_POST['bot_id'] : 0;

    if ($user_id_to_toggle && $bot_id_to_toggle) {
        // Get current status
        $stmt = $pdo->prepare("SELECT is_blocked FROM rel_user_bot WHERE user_id = ? AND bot_id = ?");
        $stmt->execute([$user_id_to_toggle, $bot_id_to_toggle]);
        $current_status = $stmt->fetchColumn();

        if ($current_status !== false) {
            // Toggle status
            $new_status = $current_status ? 0 : 1;
            $update_stmt = $pdo->prepare("UPDATE rel_user_bot SET is_blocked = ? WHERE user_id = ? AND bot_id = ?");
            $update_stmt->execute([$new_status, $user_id_to_toggle, $bot_id_to_toggle]);
        }
    }

    // Redirect to the same page to avoid form resubmission
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}


// --- Logic untuk Search ---
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
$params = [];
if (!empty($search_term)) {
    $where_clause = "WHERE id = ? OR telegram_id = ? OR first_name LIKE ? OR last_name LIKE ? OR username LIKE ?";
    $params = [$search_term, $search_term, "%$search_term%", "%$search_term%", "%$search_term%"];
}

// --- Logic untuk Sort ---
$sort_columns = ['id', 'telegram_id', 'first_name', 'last_name', 'username', 'created_at'];
$sort_by = isset($_GET['sort']) && in_array($_GET['sort'], $sort_columns) ? $_GET['sort'] : 'id';
$order = isset($_GET['order']) && strtolower($_GET['order']) === 'asc' ? 'ASC' : 'DESC';
$order_by_clause = "ORDER BY {$sort_by} {$order}";

// --- Ambil data pengguna dari database ---
$stmt = $pdo->prepare("SELECT * FROM users {$where_clause} {$order_by_clause}");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Helper function untuk membuat link sort
function get_sort_link($column, $current_sort, $current_order) {
    $new_order = ($column === $current_sort && $current_order === 'asc') ? 'desc' : 'asc';
    $arrow = '';
    if ($column === $current_sort) {
        $arrow = $current_order === 'asc' ? ' &#9650;' : ' &#9660;';
    }
    return "users.php?sort={$column}&order={$new_order}" . ($GLOBALS['search_term'] ? '&search=' . urlencode($GLOBALS['search_term']) : '') . $arrow;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna - Admin Panel</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 40px; background-color: #f4f6f8; color: #333; }
        .container { max-width: 1200px; margin: 0 auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
        th { background-color: #f2f2f2; }
        tr:hover { background-color: #f1f1f1; }
        nav { margin-bottom: 20px; }
        nav a { text-decoration: none; color: #007bff; padding: 10px; }
        nav a.active { font-weight: bold; }
        a { color: #007bff; text-decoration: none; }
        .search-form { margin-bottom: 20px; }
        .search-form input[type="text"] { padding: 8px; width: 300px; border-radius: 4px; border: 1px solid #ccc; }
        .search-form button { padding: 8px 12px; border-radius: 4px; border: none; background-color: #007bff; color: white; cursor: pointer; }
        .bot-list { list-style-type: none; padding: 0; margin: 0; }
        .bot-list li { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
        .action-btn { font-size: 0.8em; padding: 2px 6px; border-radius: 3px; color: white; border: none; cursor: pointer; }
        .btn-block { background-color: #dc3545; }
        .btn-unblock { background-color: #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <nav>
            <a href="index.php">Percakapan</a> |
            <a href="bots.php">Kelola Bot</a> |
            <a href="users.php" class="active">Pengguna</a> |
            <a href="roles.php">Manajemen Peran</a> |
            <a href="media_logs.php">Log Media</a> |
            <a href="channels.php">Channel</a> |
            <a href="database.php">Database</a> |
            <a href="logs.php">Logs</a>
        </nav>

        <h1>Manajemen Pengguna</h1>

        <div class="search-form">
            <form action="users.php" method="get">
                <input type="text" name="search" placeholder="Cari ID, Nama, Username..." value="<?= htmlspecialchars($search_term) ?>">
                <button type="submit">Cari</button>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th><a href="<?= get_sort_link('id', $sort_by, $order) ?>">ID</a></th>
                    <th><a href="<?= get_sort_link('telegram_id', $sort_by, $order) ?>">Telegram ID</a></th>
                    <th><a href="<?= get_sort_link('first_name', $sort_by, $order) ?>">Nama Depan</a></th>
                    <th><a href="<?= get_sort_link('last_name', $sort_by, $order) ?>">Nama Belakang</a></th>
                    <th><a href="<?= get_sort_link('username', $sort_by, $order) ?>">Username</a></th>
                    <th>Kode Bahasa</th>
                    <th><a href="<?= get_sort_link('created_at', $sort_by, $order) ?>">Tanggal Dibuat</a></th>
                    <th colspan="2">Bot Terkait & Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="8">Tidak ada pengguna yang cocok dengan kriteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['id']) ?></td>
                        <td><?= htmlspecialchars($user['telegram_id']) ?></td>
                        <td><?= htmlspecialchars($user['first_name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($user['last_name'] ?? '') ?></td>
                        <td>@<?= htmlspecialchars($user['username'] ?? '') ?></td>
                        <td><?= htmlspecialchars($user['language_code'] ?? '') ?></td>
                        <td><?= htmlspecialchars($user['created_at']) ?></td>
                        <td colspan="2">
                            <?php
                                // NOTE: This creates an N+1 query problem. For a larger scale app,
                                // this data should be fetched in a more optimized way.
                                $related_bots_stmt = $pdo->prepare(
                                    "SELECT b.id, b.name, r.is_blocked
                                     FROM bots b
                                     JOIN rel_user_bot r ON b.id = r.bot_id
                                     WHERE r.user_id = ?"
                                );
                                $related_bots_stmt->execute([$user['id']]);
                                $related_bots = $related_bots_stmt->fetchAll();
                            ?>
                            <ul class="bot-list">
                                <?php foreach ($related_bots as $related_bot): ?>
                                    <li>
                                        <span>
                                            <a href="chat.php?user_id=<?= $user['id'] ?>&bot_id=<?= $related_bot['id'] ?>">
                                                <?= htmlspecialchars($related_bot['name']) ?>
                                            </a>
                                            (<?= $related_bot['is_blocked'] ? 'Diblokir' : 'Aktif' ?>)
                                        </span>
                                        <form action="users.php?<?= http_build_query($_GET) ?>" method="post" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_block">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <input type="hidden" name="bot_id" value="<?= $related_bot['id'] ?>">
                                            <button type="submit" class="action-btn <?= $related_bot['is_blocked'] ? 'btn-unblock' : 'btn-block' ?>">
                                                <?= $related_bot['is_blocked'] ? 'Buka Blokir' : 'Blokir' ?>
                                            </button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
