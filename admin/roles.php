<?php

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';

session_start();

// Simple admin check. In a real app, this would be more robust.
// We assume the new role system is in place, so we might need a new is_admin() helper.
// For now, let's assume a session variable `is_admin` is set upon login.
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    // A fallback check on the old session might be needed temporarily if login isn't updated yet.
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}


$pdo = get_db_connection();

// Handle POST request to add/delete role
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_role']) && !empty($_POST['role_name'])) {
        $role_name = trim($_POST['role_name']);
        $stmt = $pdo->prepare("INSERT INTO roles (name) VALUES (?) ON DUPLICATE KEY UPDATE name=name");
        $stmt->execute([$role_name]);
        $_SESSION['flash_message'] = "Peran '{$role_name}' berhasil ditambahkan.";
    } elseif (isset($_POST['delete_role']) && !empty($_POST['role_id'])) {
        $role_id = $_POST['role_id'];
        // The ON DELETE CASCADE constraint on the user_roles table will handle associations.
        $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
        $stmt->execute([$role_id]);
        $_SESSION['flash_message'] = "Peran berhasil dihapus.";
    }
    header("Location: roles.php");
    exit;
}

// Fetch all roles
$roles = $pdo->query("SELECT * FROM roles ORDER BY name ASC")->fetchAll();

// This is a temporary way to check for admin. This should be replaced with a proper role check.
function is_admin_temp_check(PDO $pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM user_roles ur
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = ? AND r.name = 'Admin'
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() > 0;
}

if (isset($_SESSION['user_id']) && !is_admin_temp_check($pdo, $_SESSION['user_id'])) {
     header('Location: /login.php');
     exit;
}


$page_title = 'Manajemen Peran';
require_once __DIR__ . '/../partials/header.php';
?>

<h1>Manajemen Daftar Peran</h1>
<p>Gunakan halaman ini untuk menambah atau menghapus peran yang tersedia secara global di sistem.</p>

<?php if (isset($_SESSION['flash_message'])): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($_SESSION['flash_message']) ?>
        <?php unset($_SESSION['flash_message']); ?>
    </div>
<?php endif; ?>

<div class="card" style="margin-bottom: 20px;">
    <div class="card-header">
        <h2>Tambah Peran Baru</h2>
    </div>
    <div class="card-body">
        <form action="roles.php" method="post">
            <input type="text" name="role_name" placeholder="Nama peran (e.g., Reseller)" required>
            <button type="submit" name="add_role" class="btn">Tambah</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Daftar Peran Saat Ini</h2>
    </div>
    <div class="card-body">
        <table class="chat-log-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Peran</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($roles)): ?>
                    <tr>
                        <td colspan="3" style="text-align: center;">Belum ada peran yang ditambahkan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($roles as $role): ?>
                        <tr>
                            <td><?= htmlspecialchars($role['id']) ?></td>
                            <td><?= htmlspecialchars($role['name']) ?></td>
                            <td>
                                <form action="roles.php" method="post" onsubmit="return confirm('Apakah Anda yakin ingin menghapus peran ini? Ini akan menghapus peran ini dari SEMUA pengguna yang memilikinya.');">
                                    <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
                                    <button type="submit" name="delete_role" class="btn btn-delete btn-sm">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<?php require_once __DIR__ . '/../partials/footer.php'; ?>
