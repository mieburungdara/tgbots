<?php
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/database/PackageRepository.php';
require_once __DIR__ . '/../core/TelegramAPI.php';

$pdo = get_db_connection();
$packageRepo = new PackageRepository($pdo);
$message = null;

// Handle Hard Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'hard_delete_package') {
    $package_id_to_delete = filter_input(INPUT_POST, 'package_id', FILTER_VALIDATE_INT);

    if ($package_id_to_delete) {
        try {
            // Dapatkan info bot dari paket untuk menghapus pesan
            $package_info = $packageRepo->find($package_id_to_delete);
            if ($package_info && $package_info['bot_id']) {
                $stmt_bot = $pdo->prepare("SELECT token FROM bots WHERE id = ?");
                $stmt_bot->execute([$package_info['bot_id']]);
                $bot_token = $stmt_bot->fetchColumn();

                if ($bot_token) {
                    $telegram_api = new TelegramAPI($bot_token);
                    $files_to_delete = $packageRepo->hardDeletePackage($package_id_to_delete);

                    // Hapus pesan dari channel penyimpanan
                    foreach ($files_to_delete as $file) {
                        if ($file['storage_channel_id'] && $file['storage_message_id']) {
                            $telegram_api->deleteMessage($file['storage_channel_id'], $file['storage_message_id']);
                            // Kita tidak memeriksa hasilnya, coba yang terbaik
                        }
                    }
                    $_SESSION['message'] = "Paket #{$package_id_to_delete} berhasil dihapus permanen.";
                } else {
                    throw new Exception("Token bot tidak ditemukan, tidak dapat menghapus pesan dari Telegram.");
                }
            } else {
                 // Jika tidak ada info bot, coba hapus dari DB saja
                 $packageRepo->hardDeletePackage($package_id_to_delete);
                 $_SESSION['message'] = "Paket #{$package_id_to_delete} berhasil dihapus dari database (pesan di Telegram tidak dapat dihapus).";
            }
        } catch (Exception $e) {
            $_SESSION['message'] = "Error: " . $e->getMessage();
        }
    }
    header("Location: packages.php");
    exit;
}


if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Ambil semua paket
$packages = $packageRepo->findAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Konten - Admin Panel</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background-color: #f0f2f5; margin: 0; padding: 2rem; color: #1c1e21; }
        .container { max-width: 1200px; margin: auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { font-size: 1.5rem; margin: 0; }
        nav { margin-bottom: 2rem; }
        nav a { text-decoration: none; color: #007bff; margin-right: 1rem; font-weight: bold; }
        nav a.active { color: #1c1e21; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
        .btn-delete { padding: 0.3rem 0.6rem; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9em; }
        .btn-delete:hover { background-color: #c82333; }
        .btn-delete:disabled { background-color: #6c757d; cursor: not-allowed; }
        .alert { padding: 1rem; margin-bottom: 1rem; border-radius: 8px; background-color: #d1ecf1; color: #0c5460; }
        .status { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem; font-weight: bold; display: inline-block; color: white; }
        .status-available { background-color: #28a745; }
        .status-pending { background-color: #ffc107; color: #212529; }
        .status-sold { background-color: #6c757d; }
        .status-deleted { background-color: #dc3545; }
        .status-rejected { background-color: #343a40; }
    </style>
</head>
<body>
    <div class="container">
        <nav>
             <a href="index.php">Percakapan</a> |
            <a href="bots.php">Kelola Bot</a> |
            <a href="users.php">Pengguna</a> |
            <a href="roles.php">Manajemen Peran</a> |
            <a href="packages.php" class="active">Konten</a> |
            <a href="media_logs.php">Log Media</a> |
            <a href="channels.php">Channel</a> |
            <a href="database.php">Database</a> |
            <a href="analytics.php">Analitik</a> |
            <a href="logs.php">Logs</a>
        </nav>
        <h1>Manajemen Konten</h1>
        <p>Halaman ini menampilkan semua paket konten dalam sistem. Admin dapat menghapus konten secara permanen jika diperlukan.</p>

        <?php if ($message): ?>
            <div class="alert"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>ID Publik</th>
                    <th>Deskripsi</th>
                    <th>Penjual</th>
                    <th>Harga</th>
                    <th>Status</th>
                    <th>Dibuat</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($packages)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">Tidak ada konten.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($packages as $package): ?>
                        <tr>
                            <td><?= htmlspecialchars($package['public_id'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($package['description'] ?: 'N/A') ?></td>
                            <td>@<?= htmlspecialchars($package['seller_username'] ?? 'N/A') ?></td>
                            <td>Rp <?= number_format($package['price'] ?? 0, 0, ',', '.') ?></td>
                            <td>
                                <span class="status status-<?= strtolower($package['status']) ?>">
                                    <?= htmlspecialchars(ucfirst($package['status'])) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($package['created_at']) ?></td>
                            <td>
                                <form action="packages.php" method="post" onsubmit="return confirm('PERINGATAN: Aksi ini akan menghapus data dari database dan channel secara permanen. Anda yakin?');">
                                    <input type="hidden" name="action" value="hard_delete_package">
                                    <input type="hidden" name="package_id" value="<?= $package['id'] ?>">
                                    <button type="submit" class="btn-delete" <?= $package['status'] === 'sold' ? 'disabled' : '' ?>>
                                        Hard Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
