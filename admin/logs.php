<?php
/**
 * Halaman Penampil Log Database (Admin).
 *
 * Halaman ini menampilkan log aplikasi yang disimpan di dalam tabel `app_logs`.
 * Ini memungkinkan administrator untuk memantau aktivitas sistem, debug, dan error.
 *
 * Fitur:
 * - Menampilkan log dalam format tabel yang mudah dibaca.
 * - Memfilter log berdasarkan level (misal: 'info', 'error', 'debug').
 * - Tombol untuk menyegarkan tampilan log.
 * - Aksi untuk membersihkan (TRUNCATE) semua data log dari tabel.
 */

// Define ROOT_PATH for reliable file access
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/core/database.php';
require_once ROOT_PATH . '/core/helpers.php';

// --- Konfigurasi ---
$pdo = get_db_connection();
$items_per_page = 50; // Jumlah log per halaman

// --- Dapatkan Level Log yang Tersedia dari Database untuk Filter ---
$stmt_levels = $pdo->query("SELECT DISTINCT level FROM app_logs ORDER BY level ASC");
$log_levels = $stmt_levels->fetchAll(PDO::FETCH_COLUMN);

// --- Tentukan Filter Level Log yang Aktif ---
$selected_level = isset($_GET['level']) && in_array($_GET['level'], $log_levels) ? $_GET['level'] : 'all';

// --- Menangani Aksi POST (misalnya, membersihkan log) ---
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'clear_logs') {
        try {
            $pdo->query("TRUNCATE TABLE app_logs");
            app_log("Tabel app_logs dibersihkan oleh admin.", 'system'); // Mencatat aksi pembersihan itu sendiri
            $message = "Semua log berhasil dibersihkan.";
            header("Location: logs.php?message=" . urlencode($message));
            exit;
        } catch (PDOException $e) {
            $message = "Gagal membersihkan log: " . $e->getMessage();
        }
    }
}

if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
}

// --- Logika Paginasi ---
// 1. Hitung total log sesuai filter
$count_sql = "SELECT COUNT(*) FROM app_logs";
if ($selected_level !== 'all') {
    $count_sql .= " WHERE level = :level";
}
$count_stmt = $pdo->prepare($count_sql);
if ($selected_level !== 'all') {
    $count_stmt->bindParam(':level', $selected_level, PDO::PARAM_STR);
}
$count_stmt->execute();
$total_items = $count_stmt->fetchColumn();

// 2. Tentukan halaman saat ini
$total_pages = ceil($total_items / $items_per_page);
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, min($current_page, $total_pages)); // Pastikan halaman valid

// 3. Hitung offset
$offset = ($current_page - 1) * $items_per_page;


// --- Baca isi log dari DB dengan paginasi ---
$sql = "SELECT * FROM app_logs";
if ($selected_level !== 'all') {
    $sql .= " WHERE level = :level";
}
$sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);

if ($selected_level !== 'all') {
    $stmt->bindParam(':level', $selected_level, PDO::PARAM_STR);
}
$stmt->bindParam(':limit', $items_per_page, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Database Log Viewer';
include_once ROOT_PATH . '/partials/header.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4"><?php echo $page_title; ?></h1>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>

    <div class="flex justify-between items-center mb-4">
        <form action="logs.php" method="get" class="flex items-center space-x-2">
            <label for="level_select">Filter by Level:</label>
            <select name="level" id="level_select" onchange="this.form.submit()" class="p-2 border rounded-md">
                <option value="all" <?= ($selected_level === 'all') ? 'selected' : '' ?>>All Levels</option>
                <?php foreach ($log_levels as $level): ?>
                    <option value="<?= htmlspecialchars($level) ?>" <?= ($selected_level === $level) ? 'selected' : '' ?>>
                        <?= ucfirst(htmlspecialchars($level)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <div>
            <a href="logs.php?level=<?= $selected_level ?>" class="btn">Refresh</a>
            <form action="logs.php" method="post" style="display:inline;" onsubmit="return confirm('Anda yakin ingin MENGHAPUS SEMUA log? Aksi ini tidak dapat diurungkan.');">
                <input type="hidden" name="action" value="clear_logs">
                <button type="submit" class="btn btn-danger">Clear All Logs</button>
            </form>
        </div>
    </div>

    <div class="overflow-x-auto bg-white shadow-md rounded-lg">
        <table class="min-w-full">
            <thead class="bg-gray-200">
                <tr>
                    <th class="px-4 py-2">ID</th>
                    <th class="px-4 py-2">Timestamp</th>
                    <th class="px-4 py-2">Level</th>
                    <th class="px-4 py-2">Message</th>
                    <th class="px-4 py-2">Context</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4">No logs found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr class="border-b">
                            <td class="px-4 py-2"><?= htmlspecialchars($log['id']) ?></td>
                            <td class="px-4 py-2 whitespace-nowrap"><?= htmlspecialchars($log['created_at']) ?></td>
                            <td class="px-4 py-2">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                    <?= ($log['level'] == 'error' ? 'bg-red-100 text-red-800' : ($log['level'] == 'debug' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800')) ?>">
                                    <?= htmlspecialchars($log['level']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-2"><?= htmlspecialchars($log['message']) ?></td>
                            <td class="px-4 py-2">
                                <?php if (!empty($log['context'])): ?>
                                    <pre class="bg-gray-100 p-2 rounded-md text-xs"><code><?php
                                        $context_data = json_decode($log['context']);
                                        echo htmlspecialchars(json_encode($context_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                                    ?></code></pre>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Kontrol Paginasi -->
    <div class="mt-4 flex justify-center">
        <nav class="inline-flex rounded-md shadow">
            <?php if ($total_pages > 1): ?>
                <?php
                // Pertahankan parameter query yang ada (misal: filter level)
                $query_params = [];
                if ($selected_level !== 'all') {
                    $query_params['level'] = $selected_level;
                }
                ?>

                <!-- Tombol Previous -->
                <a href="?<?= http_build_query(array_merge($query_params, ['page' => $current_page - 1])) ?>"
                   class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-l-md hover:bg-gray-50 <?= ($current_page <= 1) ? 'opacity-50 cursor-not-allowed' : '' ?>">
                    &laquo; Previous
                </a>

                <?php
                // Logika untuk menampilkan rentang halaman
                $window = 2; // Jumlah halaman di sekitar halaman saat ini
                for ($i = 1; $i <= $total_pages; $i++):
                    if ($i == 1 || $i == $total_pages || ($i >= $current_page - $window && $i <= $current_page + $window)):
                ?>
                    <a href="?<?= http_build_query(array_merge($query_params, ['page' => $i])) ?>"
                       class="px-4 py-2 text-sm font-medium <?= ($i == $current_page) ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-t border-b border-gray-300' ?> hover:bg-gray-50">
                        <?= $i ?>
                    </a>
                <?php
                    // Tampilkan elipsis jika ada jeda halaman
                    elseif ($i == $current_page - $window - 1 || $i == $current_page + $window + 1):
                ?>
                    <span class="px-4 py-2 text-sm font-medium bg-white text-gray-500 border-t border-b border-gray-300">...</span>
                <?php
                    endif;
                endfor;
                ?>

                <!-- Tombol Next -->
                <a href="?<?= http_build_query(array_merge($query_params, ['page' => $current_page + 1])) ?>"
                   class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-r-md hover:bg-gray-50 <?= ($current_page >= $total_pages) ? 'opacity-50 cursor-not-allowed' : '' ?>">
                    Next &raquo;
                </a>
            <?php endif; ?>
        </nav>
    </div>
</div>

<?php
include_once ROOT_PATH . '/partials/footer.php';
?>
