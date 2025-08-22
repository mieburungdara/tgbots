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
$updates = $raw_update_repo->findAll(100); // Get last 100 updates

$page_title = "Raw Telegram Update Feed";
include_once ROOT_PATH . '/partials/header.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4"><?php echo $page_title; ?></h1>
    <p class="mb-4">This page displays the last 100 raw JSON payloads received from Telegram in real-time. Newest updates appear first.</p>

    <div class="space-y-4">
        <?php if (empty($updates)): ?>
            <p>No updates received yet.</p>
        <?php else: ?>
            <?php foreach ($updates as $update): ?>
                <div class="bg-white shadow-md rounded-lg p-4">
                    <div class="flex justify-between items-center mb-2">
                        <h2 class="text-lg font-semibold">Update #<?php echo htmlspecialchars($update['id']); ?></h2>
                        <span class="text-sm text-gray-500"><?php echo htmlspecialchars($update['created_at']); ?></span>
                    </div>
                    <pre class="bg-gray-100 p-3 rounded-md overflow-auto text-sm"><code><?php
                        // Pretty-print the JSON
                        $json_data = json_decode($update['payload']);
                        echo htmlspecialchars(json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                    ?></code></pre>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
include_once ROOT_PATH . '/partials/footer.php';
?>
