<?php
/**
 * Halaman Feed Debug (Admin).
 *
 * Halaman ini berfungsi sebagai alat debugging untuk administrator.
 * Ini menampilkan 100 payload JSON mentah terakhir yang diterima dari Telegram,
 * yang disimpan dalam tabel `raw_updates`.
 * Sangat berguna untuk memeriksa data yang masuk saat terjadi masalah atau
 * saat mengembangkan fitur baru.
 */

// Define ROOT_PATH for reliable file access
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/core/database.php';
require_once ROOT_PATH . '/core/database/RawUpdateRepository.php';

// Check for admin role (implement proper session/role check later)
// For now, this is a placeholder. In a real app, you'd have a robust auth check.
$is_admin = true; // Assuming admin for now
if (!$is_admin) {
    die('Unauthorized');
}

$pdo = get_db_connection();
$raw_update_repo = new RawUpdateRepository($pdo);

// --- Logika Paginasi ---
$items_per_page = 25; // Tampilkan 25 update per halaman
$total_items = $raw_update_repo->countAll();
$total_pages = ceil($total_items / $items_per_page);
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

// Ambil data untuk halaman saat ini
$updates = $raw_update_repo->findAll($items_per_page, $offset);

$page_title = "Raw Telegram Update Feed";
include_once ROOT_PATH . '/partials/header.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4"><?php echo $page_title; ?></h1>
    <p class="mb-4">This page displays the last 100 raw JSON payloads received from Telegram in real-time. Newest updates appear first.</p>

    <div class="space-y-2">
        <?php if (empty($updates)): ?>
            <div class="bg-white shadow-md rounded-lg p-4 text-center text-gray-500">
                No updates received yet.
            </div>
        <?php else: ?>
            <?php foreach ($updates as $update): ?>
                <div class="bg-white shadow-md rounded-lg">
                    <div class="p-4 cursor-pointer debug-header hover:bg-gray-50 transition-colors">
                        <div class="flex justify-between items-center">
                            <h2 class="text-lg font-semibold">Update #<?php echo htmlspecialchars($update['id']); ?></h2>
                            <span class="text-sm text-gray-500"><?php echo htmlspecialchars($update['created_at']); ?></span>
                        </div>
                    </div>
                    <div class="debug-content border-t border-gray-200" style="display: none;">
                        <pre class="!p-0 !m-0"><code class="language-json" style="font-size: 0.875rem;"><?php
                            $json_data = json_decode($update['payload']);
                            echo htmlspecialchars(json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                        ?></code></pre>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Kontrol Paginasi -->
    <div class="mt-6 flex justify-center">
        <nav class="inline-flex rounded-md shadow">
            <?php if ($total_pages > 1): ?>
                <!-- Tombol Previous -->
                <a href="?page=<?= $current_page - 1 ?>"
                   class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-l-md hover:bg-gray-50 <?= ($current_page <= 1) ? 'opacity-50 cursor-not-allowed' : '' ?>">
                    &laquo; Previous
                </a>

                <?php
                $window = 2;
                for ($i = 1; $i <= $total_pages; $i++):
                    if ($i == 1 || $i == $total_pages || ($i >= $current_page - $window && $i <= $current_page + $window)):
                ?>
                    <a href="?page=<?= $i ?>"
                       class="px-4 py-2 text-sm font-medium <?= ($i == $current_page) ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-t border-b border-gray-300' ?> hover:bg-gray-50">
                        <?= $i ?>
                    </a>
                <?php
                    elseif ($i == $current_page - $window - 1 || $i == $current_page + $window + 1):
                ?>
                    <span class="px-4 py-2 text-sm font-medium bg-white text-gray-500 border-t border-b border-gray-300">...</span>
                <?php
                    endif;
                endfor;
                ?>

                <!-- Tombol Next -->
                <a href="?page=<?= $current_page + 1 ?>"
                   class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-r-md hover:bg-gray-50 <?= ($current_page >= $total_pages) ? 'opacity-50 cursor-not-allowed' : '' ?>">
                    Next &raquo;
                </a>
            <?php endif; ?>
        </nav>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const headers = document.querySelectorAll('.debug-header');
    headers.forEach(header => {
        header.addEventListener('click', function() {
            const content = this.nextElementSibling;
            if (content) {
                const isHidden = content.style.display === 'none';
                content.style.display = isHidden ? 'block' : 'none';
                if (isHidden) {
                    Prism.highlightAllUnder(content);
                }
            }
        });
    });
});
</script>

<?php
include_once ROOT_PATH . '/partials/footer.php';
?>
