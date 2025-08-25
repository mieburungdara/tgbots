<?php
/**
 * Halaman Log Kesalahan API Telegram (Admin).
 */
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/database/TelegramErrorLogRepository.php';
require_once __DIR__ . '/../core/helpers.php';

// Auth check placeholder
// if (!isset($_SESSION['admin_logged_in'])) { header('Location: login.php'); exit; }

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
$page = max(1, min($page, $total_pages)); // Re-validate page

$logs = $logRepo->findAll($limit, $offset);

$page_title = 'Log Kesalahan Telegram';
require_once __DIR__ . '/../partials/header.php';
?>

<h1>Log Kesalahan API Telegram</h1>

<div style="overflow-x:auto;">
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
                        <td>
                            <?php if (!empty($log['request_data']) && $log['request_data'] !== '[]'): ?>
                                <button class="btn btn-sm context-toggle-button" data-target="context-<?= $log['id'] ?>">Show</button>
                                <div id="context-<?= $log['id'] ?>" style="display: none; margin-top: 5px;">
                                    <pre><code class="language-json"><?= htmlspecialchars(json_encode(json_decode($log['request_data']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></code></pre>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="pagination">
    <?php if ($total_pages > 1): ?>
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
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleButtons = document.querySelectorAll('.context-toggle-button');
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const targetDiv = document.getElementById(targetId);
            if (targetDiv) {
                const isHidden = targetDiv.style.display === 'none';
                targetDiv.style.display = isHidden ? 'block' : 'none';
                this.textContent = isHidden ? 'Hide' : 'Show';

                // Highlight only when showing for the first time
                if (isHidden && !this.dataset.highlighted) {
                    if (window.Prism) {
                        Prism.highlightAllUnder(targetDiv);
                    }
                    this.dataset.highlighted = 'true';
                }
            }
        });
    });
});
</script>

<?php
require_once __DIR__ . '/../partials/footer.php';
?>
