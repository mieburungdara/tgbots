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

// --- Logika untuk menangani form penyesuaian saldo (dari modal) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $telegram_id = (int)($_POST['user_id'] ?? 0); // Input field is still named user_id, but now holds telegram_id
    $amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
    $description = trim($_POST['description'] ?? '');
    $action = $_POST['action'];
    $message = '';
    $message_type = 'danger';
    if ($telegram_id && $amount > 0) {
        $transaction_amount = ($action === 'add_balance') ? $amount : -$amount;
        $pdo->beginTransaction();
        try {
            $stmt_update_user = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE telegram_id = ?");
            $stmt_update_user->execute([$transaction_amount, $telegram_id]);
            $stmt_insert_trans = $pdo->prepare("INSERT INTO balance_transactions (user_id, amount, type, description) VALUES (?, ?, ?, ?)");
            $stmt_insert_trans->execute([$telegram_id, $transaction_amount, 'admin_adjustment', $description]);
            $pdo->commit();
            $message = "Saldo pengguna berhasil diperbarui.";
            $message_type = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Terjadi kesalahan: " . $e->getMessage();
        }
    } else {
        $message = "Input tidak valid.";
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_message_type'] = $message_type;
    $redirect_url = "balance.php?" . http_build_query($_GET);
    header("Location: $redirect_url");
    exit;
}

// Ambil flash message dari session
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_message_type = $_SESSION['flash_message_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_message_type']);

// --- Logika
$search_term = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$sort_by = $_GET['sort'] ?? 'id';
$order = strtolower($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$limit = 50;
$offset = ($page - 1) * $limit;

$allowed_sort_columns = ['id', 'first_name', 'username', 'balance', 'total_income', 'total_spending'];
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'id';
}

$where_clause = '';
$params = [];
if (!empty($search_term)) {
    $where_clause = "WHERE u.first_name LIKE :search1 OR u.last_name LIKE :search2 OR u.username LIKE :search3";
    $params = [':search1' => "%$search_term%", ':search2' => "%$search_term%", ':search3' => "%$search_term%"];
}

$count_sql = "SELECT COUNT(*) FROM users u {$where_clause}";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_users = $count_stmt->fetchColumn();
$total_pages = ceil($total_users / $limit);

$sql = "
    SELECT
        u.telegram_id, u.first_name, u.last_name, u.username, u.balance,
        (SELECT SUM(price) FROM sales WHERE seller_user_id = u.telegram_id) as total_income,
        (SELECT SUM(price) FROM sales WHERE buyer_user_id = u.telegram_id) as total_spending
    FROM users u
    {$where_clause}
    ORDER BY {$sort_by} {$order}
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
                    <th><a href="<?= get_sort_link('telegram_id', $sort_by, $order, $_GET) ?>">Telegram ID</a></th>
                    <th><a href="<?= get_sort_link('first_name', $sort_by, $order, $_GET) ?>">Nama</a></th>
                    <th><a href="<?= get_sort_link('username', $sort_by, $order, $_GET) ?>">Username</a></th>
                    <th class="sortable"><a href="<?= get_sort_link('balance', $sort_by, $order, $_GET) ?>">Saldo Saat Ini</a></th>
                    <th class="sortable"><a href="<?= get_sort_link('total_income', $sort_by, $order, $_GET) ?>">Total Pemasukan</a></th>
                    <th class="sortable"><a href="<?= get_sort_link('total_spending', $sort_by, $order, $_GET) ?>">Total Pengeluaran</a></th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users_data)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;">Tidak ada pengguna ditemukan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users_data as $user_data): ?>
                        <tr>
                            <td><?= htmlspecialchars($user_data['telegram_id']) ?></td>
                            <td><?= htmlspecialchars(trim($user_data['first_name'] . ' ' . $user_data['last_name'])) ?></td>
                            <td>@<?= htmlspecialchars($user_data['username'] ?? 'N/A') ?></td>
                            <td class="clickable-log" data-log-type="balance" data-telegram-id="<?= $user_data['telegram_id'] ?>" data-user-name="<?= htmlspecialchars($user_data['first_name']) ?>"><?= format_currency($user_data['balance']) ?></td>
                            <td class="clickable-log" data-log-type="sales" data-telegram-id="<?= $user_data['telegram_id'] ?>" data-user-name="<?= htmlspecialchars($user_data['first_name']) ?>"><?= format_currency($user_data['total_income'] ?? 0) ?></td>
                            <td class="clickable-log" data-log-type="purchases" data-telegram-id="<?= $user_data['telegram_id'] ?>" data-user-name="<?= htmlspecialchars($user_data['first_name']) ?>"><?= format_currency($user_data['total_spending'] ?? 0) ?></td>
                            <td>
                                <button class="btn btn-sm btn-edit open-balance-modal"
                                        data-telegram-id="<?= $user_data['telegram_id'] ?>"
                                        data-user-name="<?= htmlspecialchars(trim($user_data['first_name'] . ' ' . $user_data['last_name'])) ?>">
                                    Ubah Saldo
                                </button>
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
</div>

<!-- Modal untuk Ubah Saldo -->
<div id="balance-modal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modal-title-adjust">Ubah Saldo untuk Pengguna</h2>
            <button class="modal-close-adjust">&times;</button>
        </div>
        <form action="balance.php?<?= http_build_query($_GET) ?>" method="post" class="balance-adjustment-form" style="padding: 0; border: none; background: none; margin-top: 0;">
            <input type="hidden" name="user_id" id="modal-user-id">
            <div class="form-group">
                <label for="modal-amount">Jumlah:</label>
                <input type="number" name="amount" id="modal-amount" step="0.01" min="0.01" required placeholder="Contoh: 50000">
            </div>
            <div class="form-group">
                <label for="modal-description">Deskripsi (Opsional):</label>
                <textarea name="description" id="modal-description" rows="2" placeholder="Contoh: Hadiah topup, koreksi, dll."></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" name="action" value="add_balance" class="btn btn-edit">Tambah Saldo</button>
                <button type="submit" name="action" value="subtract_balance" class="btn btn-delete">Kurangi Saldo</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal untuk Log Transaksi -->
<div id="log-modal" class="modal-overlay">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h2 id="log-modal-title">Riwayat Transaksi</h2>
            <button class="modal-close-log">&times;</button>
        </div>
        <div id="log-modal-body" style="max-height: 60vh; overflow-y: auto;">
            <p>Memuat data...</p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Modal untuk Ubah Saldo ---
    const balanceModal = document.getElementById('balance-modal');
    const balanceModalTitle = document.getElementById('modal-title-adjust');
    const balanceModalUserIdInput = document.getElementById('modal-user-id');
    document.querySelectorAll('.open-balance-modal').forEach(button => {
        button.addEventListener('click', function() {
            balanceModalTitle.textContent = 'Ubah Saldo untuk ' + this.dataset.userName;
            balanceModalUserIdInput.value = this.dataset.telegramId; // Changed from userId
            balanceModal.style.display = 'flex';
        });
    });
    document.querySelectorAll('.modal-close-adjust').forEach(button => button.addEventListener('click', () => balanceModal.style.display = 'none'));
    balanceModal.addEventListener('click', e => { if (e.target === balanceModal) balanceModal.style.display = 'none'; });

    // --- Modal untuk Log Transaksi ---
    const logModal = document.getElementById('log-modal');
    const logModalTitle = document.getElementById('log-modal-title');
    const logModalBody = document.getElementById('log-modal-body');
    document.querySelectorAll('.clickable-log').forEach(cell => {
        cell.addEventListener('click', async function() {
            const telegramId = this.dataset.telegramId;
            const userName = this.dataset.userName;
            const logType = this.dataset.logType;

            logModalBody.innerHTML = '<p>Memuat data...</p>';
            logModal.style.display = 'flex';

            let apiUrl = '';
            let title = '';
            let headers = [];
            let dataBuilder;

            switch (logType) {
                case 'balance':
                    apiUrl = `api/get_balance_log.php?telegram_id=${telegramId}`;
                    title = `Riwayat Penyesuaian Saldo untuk ${userName}`;
                    headers = ['Waktu', 'Tipe', 'Jumlah', 'Deskripsi'];
                    dataBuilder = (item) => `
                        <td>${item.created_at}</td>
                        <td>${item.type}</td>
                        <td>${formatCurrency(item.amount)}</td>
                        <td>${item.description || ''}</td>`;
                    break;
                case 'sales':
                    apiUrl = `api/get_sales_log.php?telegram_id=${telegramId}`;
                    title = `Riwayat Pemasukan (Penjualan) untuk ${userName}`;
                    headers = ['Waktu', 'Konten', 'Pembeli', 'Harga'];
                    dataBuilder = (item) => `
                        <td>${item.purchased_at}</td>
                        <td>${item.package_title || 'N/A'}</td>
                        <td>${item.buyer_name || 'N/A'}</td>
                        <td>${formatCurrency(item.price)}</td>`;
                    break;
                case 'purchases':
                    apiUrl = `api/get_purchases_log.php?telegram_id=${telegramId}`;
                    title = `Riwayat Pengeluaran (Pembelian) untuk ${userName}`;
                    headers = ['Waktu', 'Konten', 'Harga'];
                    dataBuilder = (item) => `
                        <td>${item.purchased_at}</td>
                        <td>${item.package_title || 'N/A'}</td>
                        <td>${formatCurrency(item.price)}</td>`;
                    break;
            }

            logModalTitle.textContent = title;

            try {
                const response = await fetch(apiUrl);
                const data = await response.json();

                if (data.error) {
                    logModalBody.innerHTML = `<p class="alert alert-danger">${data.error}</p>`;
                    return;
                }

                if (data.length === 0) {
                    logModalBody.innerHTML = '<p>Tidak ada riwayat ditemukan.</p>';
                    return;
                }

                let tableHTML = '<table class="chat-log-table"><thead><tr>';
                headers.forEach(h => tableHTML += `<th>${h}</th>`);
                tableHTML += '</tr></thead><tbody>';
                data.forEach(item => {
                    tableHTML += `<tr>${dataBuilder(item)}</tr>`;
                });
                tableHTML += '</tbody></table>';
                logModalBody.innerHTML = tableHTML;

            } catch (error) {
                logModalBody.innerHTML = `<p class="alert alert-danger">Gagal memuat data. Silakan coba lagi.</p>`;
            }
        });
    });
    document.querySelectorAll('.modal-close-log').forEach(button => button.addEventListener('click', () => logModal.style.display = 'none'));
    logModal.addEventListener('click', e => { if (e.target === logModal) logModal.style.display = 'none'; });

    // Helper function to format currency inside JS
    function formatCurrency(number, currency = 'Rp') {
        if (isNaN(parseFloat(number))) return number;
        return currency + ' ' + parseFloat(number).toLocaleString('id-ID');
    }
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
