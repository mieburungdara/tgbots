<?php
/**
 * Halaman Manajemen Pengguna (Admin).
 */
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';

session_start();

$pdo = get_db_connection();
if (!$pdo) {
    die("Koneksi database gagal.");
}

// --- Logika Aksi POST (Update Saldo, Blokir/Buka Blokir) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $message = '';
    // Aksi untuk mengubah status blokir
    if ($_POST['action'] === 'toggle_block') {
        $user_id_to_toggle = (int)($_POST['user_id'] ?? 0);
        $bot_id_to_toggle = (int)($_POST['bot_id'] ?? 0);
        if ($user_id_to_toggle && $bot_id_to_toggle) {
            $stmt = $pdo->prepare("SELECT is_blocked FROM rel_user_bot WHERE user_id = ? AND bot_id = ?");
            $stmt->execute([$user_id_to_toggle, $bot_id_to_toggle]);
            $current_status = $stmt->fetchColumn();
            if ($current_status !== false) {
                $new_status = $current_status ? 0 : 1;
                $update_stmt = $pdo->prepare("UPDATE rel_user_bot SET is_blocked = ? WHERE user_id = ? AND bot_id = ?");
                $update_stmt->execute([$new_status, $user_id_to_toggle, $bot_id_to_toggle]);
                $message = "Status blokir pengguna berhasil diubah.";
            }
        }
    // Aksi untuk memperbarui saldo
    } elseif ($_POST['action'] === 'update_balance') {
        $user_id_to_update = (int)($_POST['user_id'] ?? 0);
        $new_balance = filter_var($_POST['balance'] ?? false, FILTER_VALIDATE_FLOAT);
        if ($user_id_to_update && $new_balance !== false) {
            $update_stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
            $message = $update_stmt->execute([$new_balance, $user_id_to_update]) ? "Saldo berhasil diperbarui." : "Gagal memperbarui saldo.";
        } else {
            $message = "Input tidak valid.";
        }
    }
    // Redirect untuk mencegah resubmission (Pola PRG)
    if (!empty($message)) {
        $_SESSION['flash_message'] = $message;
    }
    header("Location: users.php?" . http_build_query($_GET));
    exit;
}

// Ambil flash message dari session
$message = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

// --- Logika Pencarian ---
$search_term = $_GET['search'] ?? '';
$where_clause = '';
$params = [];
if (!empty($search_term)) {
    $where_clause = "WHERE u.id = :search_term OR u.telegram_id = :search_term OR u.first_name LIKE :like_search OR u.last_name LIKE :like_search OR u.username LIKE :like_search";
    $params = [':search_term' => $search_term, ':like_search' => "%$search_term%"];
}

// --- Logika Pengurutan ---
$sort_columns = ['id', 'telegram_id', 'first_name', 'last_name', 'username', 'balance', 'created_at', 'status'];
$sort_by = in_array($_GET['sort'] ?? '', $sort_columns) ? $_GET['sort'] : 'id';
$order = strtolower($_GET['order'] ?? '') === 'asc' ? 'ASC' : 'DESC';
$order_by_clause = "ORDER BY u.{$sort_by} {$order}";

// --- Logika Pagination ---
$page = (int)($_GET['page'] ?? 1);
$limit = 50;
$offset = ($page - 1) * $limit;

// Query untuk menghitung total pengguna (dengan filter pencarian)
$count_sql = "SELECT COUNT(*) FROM users u {$where_clause}";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_users = $count_stmt->fetchColumn();
$total_pages = ceil($total_users / $limit);

// --- Ambil data pengguna dari database ---
$sql = "SELECT u.* FROM users u {$where_clause} {$order_by_clause} LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
// Bind parameter pencarian
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
// Bind parameter pagination
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

// Fungsi helper untuk membuat link pengurutan
function get_sort_link($column, $current_sort, $current_order) {
    $new_order = ($column === $current_sort && $current_order === 'asc') ? 'desc' : 'asc';
    $arrow = ($column === $current_sort) ? ($current_order === 'asc' ? ' &#9650;' : ' &#9660;') : '';
    // Pertahankan parameter GET yang ada
    $query_params = $_GET;
    $query_params['sort'] = $column;
    $query_params['order'] = $new_order;
    return "users.php?" . http_build_query($query_params) . $arrow;
}

$page_title = 'Manajemen Pengguna';
require_once __DIR__ . '/../partials/header.php';
?>

<h1>Manajemen Pengguna</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="search-form" style="margin-bottom: 20px;">
    <form action="users.php" method="get">
        <input type="text" name="search" placeholder="Cari ID, Nama, Username..." value="<?= htmlspecialchars($search_term) ?>" style="width: 300px; display: inline-block;">
        <button type="submit" class="btn">Cari</button>
        <?php if(!empty($search_term)): ?>
            <a href="users.php" class="btn btn-delete">Hapus Filter</a>
        <?php endif; ?>
    </form>
</div>

<p>Menampilkan <?= count($users) ?> dari <?= $total_users ?> total pengguna.</p>

<div class="table-responsive">
    <table class="chat-log-table">
        <thead>
            <tr>
                <th><a href="<?= get_sort_link('id', $sort_by, $order) ?>">ID</a></th>
                <th><a href="<?= get_sort_link('telegram_id', $sort_by, $order) ?>">Telegram ID</a></th>
                <th><a href="<?= get_sort_link('first_name', $sort_by, $order) ?>">Nama</a></th>
                <th><a href="<?= get_sort_link('username', $sort_by, $order) ?>">Username</a></th>
                <th><a href="<?= get_sort_link('status', $sort_by, $order) ?>">Status</a></th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="6" style="text-align:center;">Tidak ada pengguna ditemukan.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['id']) ?></td>
                    <td><?= htmlspecialchars($user['telegram_id']) ?></td>
                    <td><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></td>
                    <td>@<?= htmlspecialchars($user['username'] ?? 'N/A') ?></td>
                    <td><span class="status-<?= htmlspecialchars($user['status']) ?>"><?= htmlspecialchars(ucfirst($user['status'])) ?></span></td>
                    <td>
                        <a href="index.php?search_user=<?= htmlspecialchars($user['username'] ?? $user['first_name']) ?>" class="btn btn-sm">Lihat Percakapan</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="pagination">
    <?php
    $query_params = $_GET;
    if ($page > 1) {
        $query_params['page'] = $page - 1;
        echo '<a href="?' . http_build_query($query_params) . '">&laquo; Sebelumnya</a>';
    } else {
        echo '<span class="disabled">&laquo; Sebelumnya</span>';
    }

    echo '<span class="current-page">Halaman ' . $page . ' dari ' . $total_pages . '</span>';

    if ($page < $total_pages) {
        $query_params['page'] = $page + 1;
        echo '<a href="?' . http_build_query($query_params) . '">Berikutnya &raquo;</a>';
    } else {
        echo '<span class="disabled">Berikutnya &raquo;</span>';
    }
    ?>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
