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
    // Menggunakan placeholder unik untuk setiap kondisi untuk menghindari error di beberapa driver PDO
    $where_clause = "WHERE u.id = :search_id OR u.telegram_id = :search_telegram_id OR u.first_name LIKE :like_fn OR u.last_name LIKE :like_ln OR u.username LIKE :like_un";
    $params = [
        ':search_id' => $search_term,
        ':search_telegram_id' => $search_term,
        ':like_fn' => "%$search_term%",
        ':like_ln' => "%$search_term%",
        ':like_un' => "%$search_term%"
    ];
}

// --- Logika Pengurutan ---
$sort_columns = ['id', 'telegram_id', 'first_name', 'username', 'status', 'roles'];
$sort_by = in_array($_GET['sort'] ?? '', $sort_columns) ? $_GET['sort'] : 'id';
$order = strtolower($_GET['order'] ?? '') === 'asc' ? 'ASC' : 'DESC';
// Perlu penanganan khusus untuk sorting berdasarkan 'roles' karena ini adalah kolom agregat
$order_by_column = $sort_by === 'roles' ? 'roles' : "u.{$sort_by}";
$order_by_clause = "ORDER BY {$order_by_column} {$order}";

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

// --- Ambil data pengguna dari database dengan peran mereka ---
$sql = "
    SELECT u.*, GROUP_CONCAT(r.name SEPARATOR ', ') as roles
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    {$where_clause}
    GROUP BY u.id
    {$order_by_clause}
    LIMIT :limit OFFSET :offset
";
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

// --- Ambil semua peran yang tersedia untuk modal ---
$all_roles = $pdo->query("SELECT * FROM roles ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Manajemen Pengguna';
require_once __DIR__ . '/../partials/header.php';
?>

<h1>Manajemen Pengguna</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-success" id="flash-message"><?= htmlspecialchars($message) ?></div>
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
                <th><a href="<?= get_sort_link('roles', $sort_by, $order) ?>">Peran</a></th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="7" style="text-align:center;">Tidak ada pengguna ditemukan.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                <tr id="user-row-<?= $user['id'] ?>">
                    <td><?= htmlspecialchars($user['id']) ?></td>
                    <td><?= htmlspecialchars($user['telegram_id']) ?></td>
                    <td><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></td>
                    <td>@<?= htmlspecialchars($user['username'] ?? 'N/A') ?></td>
                    <td><span class="status-<?= htmlspecialchars($user['status']) ?>"><?= htmlspecialchars(ucfirst($user['status'])) ?></span></td>
                    <td class="roles-cell">
                        <?php if (!empty($user['roles'])): ?>
                            <?php foreach (explode(', ', $user['roles']) as $role): ?>
                                <span class="role-badge"><?= htmlspecialchars($role) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-muted">Tidak ada</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="index.php?search_user=<?= htmlspecialchars($user['username'] ?? $user['first_name']) ?>" class="btn btn-sm">Lihat Chat</a>
                        <button class="btn btn-sm btn-manage-roles" data-user-id="<?= $user['id'] ?>" data-user-name="<?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>">Kelola Peran</button>
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

<!-- Modal for Role Management -->
<div id="roles-modal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modal-title">Kelola Peran untuk Pengguna</h2>
            <span class="close-button">&times;</span>
        </div>
        <div class="modal-body">
            <form id="roles-form">
                <input type="hidden" id="modal-user-id" name="user_id">
                <div id="roles-checkbox-container">
                    <!-- Checkboxes will be populated by JavaScript -->
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button id="save-roles-button" class="btn">Simpan</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('roles-modal');
    const closeButton = modal.querySelector('.close-button');
    const manageButtons = document.querySelectorAll('.btn-manage-roles');
    const saveButton = document.getElementById('save-roles-button');
    const checkboxContainer = document.getElementById('roles-checkbox-container');
    const modalUserIdInput = document.getElementById('modal-user-id');
    const modalTitle = document.getElementById('modal-title');

    const allRoles = <?= json_encode($all_roles) ?>;

    function openModal(userId, userName) {
        modalUserIdInput.value = userId;
        modalTitle.textContent = 'Kelola Peran untuk ' + userName;
        checkboxContainer.innerHTML = 'Memuat peran...';
        modal.style.display = 'block';

        // Fetch user's current roles
        fetch(`api/get_user_roles.php?user_id=${userId}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                populateCheckboxes(data.role_ids);
            })
            .catch(error => {
                checkboxContainer.innerHTML = `<p style="color:red;">Gagal memuat peran: ${error.message}</p>`;
            });
    }

    function populateCheckboxes(assignedRoleIds) {
        checkboxContainer.innerHTML = '';
        allRoles.forEach(role => {
            const isChecked = assignedRoleIds.includes(role.id);
            const checkboxWrapper = document.createElement('div');
            checkboxWrapper.className = 'checkbox-wrapper';
            checkboxWrapper.innerHTML = `
                <label>
                    <input type="checkbox" name="role_ids[]" value="${role.id}" ${isChecked ? 'checked' : ''}>
                    ${role.name}
                </label>
            `;
            checkboxContainer.appendChild(checkboxWrapper);
        });
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    manageButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const userName = this.getAttribute('data-user-name');
            openModal(userId, userName);
        });
    });

    closeButton.addEventListener('click', closeModal);
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    saveButton.addEventListener('click', function() {
        const userId = modalUserIdInput.value;
        const checkedRoles = Array.from(checkboxContainer.querySelectorAll('input[type="checkbox"]:checked')).map(cb => cb.value);

        saveButton.textContent = 'Menyimpan...';
        saveButton.disabled = true;

        fetch('api/update_user_roles.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: userId,
                role_ids: checkedRoles
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeModal();
                // Optionally, refresh the roles in the table row without a full page reload
                updateTableRowRoles(userId, checkedRoles);
                // Show a temporary success message
                const flashMessage = document.getElementById('flash-message') || document.createElement('div');
                flashMessage.className = 'alert alert-success';
                flashMessage.textContent = 'Peran berhasil diperbarui!';
                if (!document.getElementById('flash-message')) {
                    document.querySelector('h1').insertAdjacentElement('afterend', flashMessage);
                }
                setTimeout(() => flashMessage.style.display = 'none', 3000);
            } else {
                throw new Error(data.error || 'Terjadi kesalahan yang tidak diketahui.');
            }
        })
        .catch(error => {
            alert('Gagal menyimpan peran: ' + error.message);
        })
        .finally(() => {
            saveButton.textContent = 'Simpan';
            saveButton.disabled = false;
        });
    });

    function updateTableRowRoles(userId, newRoleIds) {
        const row = document.getElementById(`user-row-${userId}`);
        if (!row) return;

        const rolesCell = row.querySelector('.roles-cell');
        if (!rolesCell) return;

        rolesCell.innerHTML = '';
        const newRoleNames = newRoleIds.map(id => {
            const role = allRoles.find(r => r.id == id);
            return role ? role.name : '';
        }).filter(name => name);

        if (newRoleNames.length > 0) {
            newRoleNames.forEach(name => {
                const badge = document.createElement('span');
                badge.className = 'role-badge';
                badge.textContent = name;
                rolesCell.appendChild(badge);
            });
        } else {
            const noRoleSpan = document.createElement('span');
            noRoleSpan.className = 'text-muted';
            noRoleSpan.textContent = 'Tidak ada';
            rolesCell.appendChild(noRoleSpan);
        }
    }
});
</script>

<style>
.role-badge {
    display: inline-block;
    padding: 0.25em 0.6em;
    font-size: 75%;
    font-weight: 700;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 0.375rem;
    color: #fff;
    background-color: #6c757d;
    margin-right: 5px;
    margin-bottom: 5px;
}
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5);
}
.modal-content {
    background-color: #fefefe;
    margin: 10% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 500px;
    border-radius: 5px;
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
    margin-bottom: 20px;
}
.modal-header h2 {
    margin: 0;
}
.close-button {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}
.close-button:hover,
.close-button:focus {
    color: black;
}
.checkbox-wrapper {
    margin-bottom: 10px;
}
</style>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
