<?php
session_start();

// Untuk development, kita asumsikan admin sudah login.
// Di produksi, harus ada pengecekan sesi yang lebih ketat.
// if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
//     header('Location: login.php');
//     exit;
// }

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/database/TelegramErrorLogRepository.php';
require_once __DIR__ . '/../core/helpers.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("Tidak dapat terhubung ke database.");
}

$logRepo = new TelegramErrorLogRepository($pdo);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 25;
$offset = ($page - 1) * $limit;
$total_records = $logRepo->countAll();
$total_pages = ceil($total_records / $limit);

$logs = $logRepo->findAll($limit, $offset);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Kesalahan Telegram - Admin Panel</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; background-color: #f4f6f8; color: #333; }
        .main-container { max-width: 1400px; margin: 20px auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #f4f4f4; padding-bottom: 10px; }
        nav { background-color: #333; padding: 10px 20px; }
        nav a { text-decoration: none; color: white; padding: 10px 15px; display: inline-block; }
        nav a:hover, nav a.active { background-color: #555; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; table-layout: fixed; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; font-size: 0.9em; word-wrap: break-word; }
        th { background-color: #f7f7f7; }
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a { text-decoration: none; color: #007bff; padding: 8px 12px; border: 1px solid #ddd; margin: 0 2px; border-radius: 4px; }
        .pagination a.current { background-color: #007bff; color: white; border-color: #007bff; }
        .pagination a.disabled { color: #ccc; pointer-events: none; }
        .pagination a:hover:not(.current):not(.disabled) { background-color: #eee; }
        .details { max-height: 150px; overflow-y: auto; display: block; background: #efefef; padding: 8px; border-radius: 4px; white-space: pre-wrap; }
        .status-failed { color: #dc3545; font-weight: bold; }
        .status-pending_retry { color: #ffc107; font-weight: bold; }
    </style>
</head>
<body>

    <nav>
        <a href="index.php">Percakapan</a> |
        <a href="bots.php">Kelola Bot</a> |
        <a href="users.php">Pengguna</a> |
        <a href="roles.php">Manajemen Peran</a> |
        <a href="packages.php">Konten</a> |
        <a href="media_logs.php">Log Media</a> |
        <a href="channels.php">Channel</a> |
        <a href="database.php">Database</a> |
        <a href="logs.php">Logs</a> |
        <a href="telegram_logs.php" class="active">Log Error Telegram</a>
    </nav>

    <div class="main-container">
        <h1>Log Kesalahan API Telegram</h1>

        <table>
            <colgroup>
                <col style="width: 12%;">
                <col style="width: 10%;">
                <col style="width: 8%;">
                <col style="width: 5%;">
                <col style="width: 5%;">
                <col style="width: 8%;">
                <col style="width: 22%;">
                <col style="width: 30%;">
            </colgroup>
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Metode</th>
                    <th>Chat ID</th>
                    <th>HTTP</th>
                    <th>Kode</th>
                    <th>Status</th>
                    <th>Deskripsi</th>
                    <th>Data Request</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 20px;">Tidak ada log kesalahan yang tercatat.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['created_at']) ?></td>
                            <td><?= htmlspecialchars($log['method']) ?></td>
                            <td><?= htmlspecialchars($log['chat_id'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($log['http_code'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($log['error_code'] ?? 'N/A') ?></td>
                            <td><span class="status-<?= strtolower($log['status']) ?>"><?= htmlspecialchars($log['status']) ?></span></td>
                            <td><?= htmlspecialchars($log['description']) ?></td>
                            <td><pre class="details"><?= htmlspecialchars(json_encode(json_decode($log['request_data']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>">&laquo; Sebelumnya</a>
            <?php else: ?>
                <a class="disabled">&laquo; Sebelumnya</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?= $i ?>" class="<?= ($page == $i) ? 'current' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>">Berikutnya &raquo;</a>
            <?php else: ?>
                <a class="disabled">Berikutnya &raquo;</a>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
