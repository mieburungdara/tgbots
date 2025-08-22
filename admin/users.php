<?php
/**
 * Halaman Manajemen Pengguna (Admin).
 *
 * Halaman ini menyediakan antarmuka lengkap untuk mengelola semua pengguna
 * dalam sistem.
 *
 * Fitur:
 * - Menampilkan daftar semua pengguna dengan detail penting.
 * - Pencarian pengguna berdasarkan ID, nama, atau username.
 * - Pengurutan (sorting) tabel berdasarkan berbagai kolom.
 * - Memperbarui saldo pengguna secara langsung dari tabel.
 * - Melihat bot mana yang pernah berinteraksi dengan pengguna.
 * - Memblokir atau membuka blokir pengguna untuk bot tertentu.
 * - Menggunakan pola PRG (Post/Redirect/Get) untuk menangani aksi form.
 */
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("Koneksi database gagal.");
}

$message = '';

// --- Logika untuk menangani aksi POST (update saldo, blokir/buka blokir) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Aksi untuk mengubah status blokir pengguna pada bot tertentu
    if ($_POST['action'] === 'toggle_block') {
        $user_id_to_toggle = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $bot_id_to_toggle = isset($_POST['bot_id']) ? (int)$_POST['bot_id'] : 0;

        if ($user_id_to_toggle && $bot_id_to_toggle) {
            // Ambil status saat ini, lalu balikkan nilainya (0 -> 1, 1 -> 0)
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
    // Aksi untuk memperbarui saldo pengguna
    } elseif ($_POST['action'] === 'update_balance') {
        $user_id_to_update = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $new_balance = isset($_POST['balance']) ? filter_var($_POST['balance'], FILTER_VALIDATE_FLOAT) : false;

        if ($user_id_to_update && $new_balance !== false) {
            $update_stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
            if ($update_stmt->execute([$new_balance, $user_id_to_update])) {
                $message = "Saldo pengguna berhasil diperbarui.";
            } else {
                $message = "Gagal memperbarui saldo.";
            }
        } else {
            $message = "Input tidak valid untuk memperbarui saldo.";
        }
    }

    // Redirect kembali ke halaman yang sama untuk menghindari pengiriman ulang formulir.
    // Parameter GET (seperti sort, order, search) dipertahankan.
    $redirect_url = "users.php?" . http_build_query($_GET);
    if (!empty($message)) {
        $_SESSION['flash_message'] = $message;
    }
    header("Location: " . $redirect_url);
    exit;
}

// Periksa pesan status (flash message) dari session setelah redirect
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}


// --- Logika untuk Pencarian ---
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
$params = [];
if (!empty($search_term)) {
    // Bangun klausa WHERE untuk mencari di beberapa kolom
    $where_clause = "WHERE u.id = ? OR u.telegram_id = ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ?";
    $params = [$search_term, $search_term, "%$search_term%", "%$search_term%", "%$search_term%"];
}


// --- Logika untuk Pengurutan ---
$sort_columns = ['id', 'telegram_id', 'first_name', 'last_name', 'username', 'balance', 'created_at'];
$sort_by = isset($_GET['sort']) && in_array($_GET['sort'], $sort_columns) ? $_GET['sort'] : 'id';
$order = isset($_GET['order']) && strtolower($_GET['order']) === 'asc' ? 'ASC' : 'DESC';
$order_by_clause = "ORDER BY u.{$sort_by} {$order}";

// --- Ambil data pengguna dari database dengan filter dan urutan yang diterapkan ---
$stmt = $pdo->prepare("SELECT u.* FROM users u {$where_clause} {$order_by_clause}");
$stmt->execute($params);
$users = $stmt->fetchAll();


// Fungsi helper untuk membuat link pengurutan pada header tabel
function get_sort_link($column, $current_sort, $current_order) {
    // Balikkan urutan jika kolom yang sama diklik lagi, jika tidak, default ke 'asc'
    $new_order = ($column === $current_sort && $current_order === 'asc') ? 'desc' : 'asc';
    $arrow = '';
    if ($column === $current_sort) {
        $arrow = $current_order === 'asc' ? ' &#9650;' : ' &#9660;';
    }
    return "users.php?sort={$column}&order={$new_order}" . ($GLOBALS['search_term'] ? '&search=' . urlencode($GLOBALS['search_term']) : '') . $arrow;
}

$page_title = 'Manajemen Pengguna';
require_once __DIR__ . '/../partials/header.php';
?>

<h1>Manajemen Pengguna</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="search-form">
    <form action="users.php" method="get" style="padding:0; border:none; background:none;">
        <input type="text" name="search" placeholder="Cari ID, Nama, Username..." value="<?= htmlspecialchars($search_term) ?>">
        <button type="submit">Cari</button>
    </form>
</div>

<table>
    <thead>
        <tr>
            <th><a href="<?= get_sort_link('id', $sort_by, $order) ?>">ID</a></th>
            <th><a href="<?= get_sort_link('telegram_id', $sort_by, $order) ?>">Telegram ID</a></th>
            <th><a href="<?= get_sort_link('first_name', $sort_by, $order) ?>">Nama</a></th>
            <th><a href="<?= get_sort_link('username', $sort_by, $order) ?>">Username</a></th>
            <th><a href="<?= get_sort_link('balance', $sort_by, $order) ?>">Saldo</a></th>
            <th>Bot Terkait & Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($users)): ?>
            <tr>
                <td colspan="6">Tidak ada pengguna yang cocok dengan kriteria.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?= htmlspecialchars($user['id']) ?></td>
                <td><?= htmlspecialchars($user['telegram_id']) ?></td>
                <td><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></td>
                <td>@<?= htmlspecialchars($user['username'] ?? '') ?></td>
                <td>
                    <form action="users.php?<?= http_build_query($_GET) ?>" method="post" class="balance-form" style="padding:0; border:none; background:none;">
                        <input type="hidden" name="action" value="update_balance">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <input type="number" name="balance" value="<?= htmlspecialchars($user['balance']) ?>" step="any" required>
                        <button type="submit" class="btn" style="padding: 4px 8px;">Simpan</button>
                    </form>
                </td>
                <td>
                    <?php
                        $related_bots_stmt = $pdo->prepare(
                            "SELECT b.id, b.first_name, r.is_blocked
                             FROM bots b
                             JOIN rel_user_bot r ON b.id = r.bot_id
                             WHERE r.user_id = ?"
                        );
                        $related_bots_stmt->execute([$user['id']]);
                        $related_bots = $related_bots_stmt->fetchAll();
                    ?>
                    <ul class="bot-list" style="list-style: none; padding: 0; margin: 0;">
                        <?php foreach ($related_bots as $related_bot): ?>
                            <li style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                <span>
                                    <a href="chat.php?user_id=<?= $user['id'] ?>&bot_id=<?= $related_bot['id'] ?>">
                                        <?= htmlspecialchars($related_bot['first_name']) ?>
                                    </a>
                                    (<?= $related_bot['is_blocked'] ? 'Diblokir' : 'Aktif' ?>)
                                </span>
                                <form action="users.php?<?= http_build_query($_GET) ?>" method="post" style="display: inline; padding:0; border:none; background:none;">
                                    <input type="hidden" name="action" value="toggle_block">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <input type="hidden" name="bot_id" value="<?= $related_bot['id'] ?>">
                                    <button type="submit" class="btn <?= $related_bot['is_blocked'] ? 'btn-edit' : 'btn-delete' ?>" style="padding: 2px 6px; font-size: 0.8em;">
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
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
