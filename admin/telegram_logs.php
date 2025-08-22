<?php
/**
 * Halaman Log Kesalahan API Telegram (Admin).
 *
 * Halaman ini menampilkan log kesalahan yang spesifik terjadi saat berinteraksi
 * dengan API Telegram, yang diambil dari tabel `telegram_error_logs`.
 * Ini membantu dalam mendiagnosis masalah konektivitas atau permintaan API yang salah.
 *
 * Fitur:
 * - Menampilkan log dalam format tabel yang terstruktur.
 * - Menyertakan detail penting seperti metode API, kode error, deskripsi, dan data request.
 * - Implementasi paginasi untuk menavigasi log dalam jumlah besar.
 */
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

$page_title = 'Log Kesalahan Telegram';
require_once __DIR__ . '/../partials/header.php';
?>

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

<?php
require_once __DIR__ . '/../partials/footer.php';
?>
