<?php
/**
 * Halaman Manajemen Saldo Pengguna (Admin).
 */
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("Koneksi database gagal.");
}

// --- Logika untuk menangani form penyesuaian saldo ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // (Logic from previous step remains the same)
    $user_id = (int)($_POST['user_id'] ?? 0);
    $amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
    $description = trim($_POST['description'] ?? '');
    $action = $_POST['action'];

    $message = '';
    $message_type = 'danger';

    if ($user_id && $amount > 0) {
        $transaction_amount = ($action === 'add_balance') ? $amount : -$amount;

        $pdo->beginTransaction();
        try {
            $stmt_update_user = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt_update_user->execute([$transaction_amount, $user_id]);

            $stmt_insert_trans = $pdo->prepare(
                "INSERT INTO balance_transactions (user_id, amount, type, description) VALUES (?, ?, ?, ?)"
            );
            $stmt_insert_trans->execute([$user_id, $transaction_amount, 'admin_adjustment', $description]);

            $pdo->commit();
            $message = "Saldo pengguna berhasil diperbarui.";
            $message_type = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Terjadi kesalahan: " . $e->getMessage();
        }
    } else {
        $message = "Input tidak valid. Pastikan pengguna dipilih dan jumlah adalah angka positif.";
    }

    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_message_type'] = $message_type;
    header("Location: balance.php");
    exit;
}

// Ambil flash message dari session
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_message_type = $_SESSION['flash_message_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_message_type']);

// --- Logika Pagination & Pencarian ---
$search_term = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 50;
$offset = ($page - 1) * $limit;

// Bangun klausa WHERE untuk pencarian
$where_clause = '';
$params = [];
if (!empty($search_term)) {
    $where_clause = "WHERE u.first_name LIKE :search OR u.last_name LIKE :search OR u.username LIKE :search";
    $params[':search'] = "%$search_term%";
}

// Query untuk menghitung total pengguna (dengan filter)
$count_sql = "SELECT COUNT(*) FROM users u {$where_clause}";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_users = $count_stmt->fetchColumn();
$total_pages = ceil($total_users / $limit);

// Ambil semua pengguna untuk dropdown form (tidak terpengaruh pagination/pencarian)
$all_users_for_form = $pdo->query("SELECT id, first_name, last_name, username FROM users ORDER BY first_name ASC, last_name ASC")->fetchAll();

// Query utama untuk mendapatkan data pengguna dengan agregat dan pagination
$sql = "
    SELECT
        u.id,
        u.first_name,
        u.last_name,
        u.username,
        u.balance,
        (SELECT SUM(price) FROM sales WHERE seller_id = u.id) as total_income,
        (SELECT SUM(price) FROM sales WHERE buyer_id = u.id) as total_spending
    FROM users u
    {$where_clause}
    ORDER BY u.id DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$users_data = $stmt->fetchAll(PDO::FETCH_ASSOC);


$page_title = 'Manajemen Saldo';
require_once __DIR__ . '/../partials/header.php';
?>

<h1>Manajemen Saldo</h1>

<?php if (!empty($flash_message)): ?>
    <div class="alert alert-<?= htmlspecialchars($flash_message_type) ?>"><?= htmlspecialchars($flash_message) ?></div>
<?php endif; ?>

<div class="content-box">
    <h2>Ubah Saldo Pengguna</h2>
    <form action="balance.php" method="post" class="balance-adjustment-form">
        <div class="form-group">
            <label for="user_id">Pilih Pengguna:</label>
            <select name="user_id" id="user_id" required>
                <option value="">-- Pilih Pengguna --</option>
                <?php foreach ($all_users_for_form as $user): ?>
                    <option value="<?= $user['id'] ?>">
                        <?= htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])) ?> (@<?= htmlspecialchars($user['username'] ?? 'N/A') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="amount">Jumlah:</label>
            <input type="number" name="amount" id="amount" step="0.01" min="0.01" required placeholder="Contoh: 50000">
        </div>
        <div class="form-group">
            <label for="description">Deskripsi (Opsional):</label>
            <textarea name="description" id="description" rows="2" placeholder="Contoh: Hadiah topup, koreksi, dll."></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" name="action" value="add_balance" class="btn btn-edit">Tambah Saldo</button>
            <button type="submit" name="action" value="subtract_balance" class="btn btn-delete">Kurangi Saldo</button>
        </div>
    </form>
</div>

<div class="content-box" style="margin-top: 20px;">
    <h2>Daftar Saldo Pengguna</h2>

    <div class="search-form" style="margin-bottom: 20px;">
        <form action="balance.php" method="get">
            <input type="text" name="search" placeholder="Cari Nama/Username..." value="<?= htmlspecialchars($search_term) ?>" style="width: 300px; display: inline-block;">
            <button type="submit" class="btn">Cari</button>
             <?php if(!empty($search_term)): ?>
                <a href="balance.php" class="btn btn-delete">Hapus Filter</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive">
        <table class="chat-log-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama</th>
                    <th>Username</th>
                    <th>Saldo Saat Ini</th>
                    <th>Total Pemasukan</th>
                    <th>Total Pengeluaran</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users_data)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;">Tidak ada pengguna ditemukan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users_data as $user_data): ?>
                        <tr>
                            <td><?= htmlspecialchars($user_data['id']) ?></td>
                            <td><?= htmlspecialchars(trim($user_data['first_name'] . ' ' . $user_data['last_name'])) ?></td>
                            <td>@<?= htmlspecialchars($user_data['username'] ?? 'N/A') ?></td>
                            <td><?= format_currency($user_data['balance']) ?></td>
                            <td><?= format_currency($user_data['total_income'] ?? 0) ?></td>
                            <td><?= format_currency($user_data['total_spending'] ?? 0) ?></td>
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
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
