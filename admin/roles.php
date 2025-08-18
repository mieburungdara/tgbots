<?php
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("Koneksi database gagal.");
}

// TODO: Implementasikan mekanisme otentikasi admin yang sebenarnya.
// Saat ini, kita asumsikan siapa pun yang mengakses halaman ini adalah admin.

$status_message = '';
$status_type = '';

// Handle permintaan untuk memperbarui peran pengguna
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $user_id_to_update = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $new_role = $_POST['role'] ?? '';
    $allowed_roles = ['user', 'admin'];

    if ($user_id_to_update && in_array($new_role, $allowed_roles)) {
        try {
            // Jangan izinkan admin terakhir menghapus perannya sendiri
            if ($new_role === 'user') {
                $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
                $stmt_count->execute();
                $admin_count = $stmt_count->fetchColumn();

                $stmt_user = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                $stmt_user->execute([$user_id_to_update]);
                $current_role = $stmt_user->fetchColumn();

                if ($admin_count <= 1 && $current_role === 'admin') {
                    throw new Exception("Tidak dapat menghapus peran admin terakhir.");
                }
            }

            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$new_role, $user_id_to_update]);
            $status_message = "Peran berhasil diperbarui.";
            $status_type = 'success';
        } catch (Exception $e) {
            $status_message = "Gagal memperbarui peran: " . $e->getMessage();
            $status_type = 'error';
        }
    } else {
        $status_message = "Input tidak valid.";
        $status_type = 'error';
    }
}

// Ambil semua pengguna dari database
$users = $pdo->query("SELECT id, telegram_id, first_name, username, role FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Manajemen Peran';
require_once __DIR__ . '/../partials/header.php';
?>

<h1>Manajemen Peran Pengguna</h1>
        <p>Gunakan halaman ini untuk menetapkan peran 'admin' kepada pengguna. Pengguna dengan peran admin akan menerima media yang diteruskan.</p>

        <?php if ($status_message): ?>
            <div class="alert <?= $status_type ?>"><?= htmlspecialchars($status_message) ?></div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>ID Telegram</th>
                    <th>Nama</th>
                    <th>Username</th>
                    <th>Peran Saat Ini</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['telegram_id']) ?></td>
                        <td><?= htmlspecialchars($user['first_name']) ?></td>
                        <td><?= $user['username'] ? '@' . htmlspecialchars($user['username']) : 'N/A' ?></td>
                        <td><strong><?= htmlspecialchars(ucfirst($user['role'])) ?></strong></td>
                        <td>
                            <form action="roles.php" method="post">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <select name="role">
                                    <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                                <button type="submit" name="update_role">Simpan</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

<?php
require_once __DIR__ . '/../partials/footer.php';
?>
