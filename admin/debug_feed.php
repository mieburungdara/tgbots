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
    <p class="mb-4">This page displays raw JSON payloads received from Telegram. Newest updates appear first.</p>

    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <table class="min-w-full">
            <thead class="bg-gray-200">
                <tr>
                    <th class="px-4 py-2 text-left">ID</th>
                    <th class="px-4 py-2 text-left">Timestamp</th>
                    <th class="px-4 py-2 text-left">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($updates)): ?>
                    <tr>
                        <td colspan="3" class="text-center py-4 text-gray-500">No updates received yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($updates as $update): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-2 font-mono"><?= htmlspecialchars($update['id']) ?></td>
                            <td class="px-4 py-2 font-mono"><?= htmlspecialchars($update['created_at']) ?></td>
                            <td class="px-4 py-2">
                                <button class="btn btn-sm view-json-btn"
                                        data-update-id="<?= htmlspecialchars($update['id']) ?>"
                                        data-payload="<?= base64_encode($update['payload']) ?>">
                                    View JSON
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Kontrol Paginasi -->
    <div class="mt-6 flex justify-center">
        <nav class="inline-flex rounded-md shadow">
            <?php if ($total_pages > 1): ?>
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
                <a href="?page=<?= $current_page + 1 ?>"
                   class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-r-md hover:bg-gray-50 <?= ($current_page >= $total_pages) ? 'opacity-50 cursor-not-allowed' : '' ?>">
                    Next &raquo;
                </a>
            <?php endif; ?>
        </nav>
    </div>
</div>

<!-- Modal Structure -->
<div id="json-modal" class="modal-overlay">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h2 id="modal-title" class="text-xl font-bold">JSON Payload</h2>
            <button id="modal-close-btn" class="modal-close">&times;</button>
        </div>
        <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
            <pre><code id="modal-json-content" class="language-json"></code></pre>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('json-modal');
    const modalTitle = document.getElementById('modal-title');
    const modalJsonContent = document.getElementById('modal-json-content');
    const closeModalBtn = document.getElementById('modal-close-btn');
    const viewJsonButtons = document.querySelectorAll('.view-json-btn');

    function openModal() {
        modal.style.display = 'flex';
    }

    function closeModal() {
        modal.style.display = 'none';
        modalJsonContent.textContent = ''; // Clear content
    }

    viewJsonButtons.forEach(button => {
        button.addEventListener('click', function() {
            const updateId = this.getAttribute('data-update-id');
            const base64Payload = this.getAttribute('data-payload');

            modalTitle.textContent = `JSON Payload for Update #${updateId}`;

            try {
                // Decode from base64, then parse and re-stringify for pretty printing
                const payloadString = atob(base64Payload);
                const payloadJson = JSON.parse(payloadString);
                const prettyPayload = JSON.stringify(payloadJson, null, 2);
                modalJsonContent.textContent = prettyPayload;
            } catch (e) {
                // If parsing fails, show the raw decoded string
                try {
                    modalJsonContent.textContent = atob(base64Payload);
                } catch (e2) {
                    modalJsonContent.textContent = "Error decoding payload.";
                }
                console.error("Failed to parse JSON payload:", e);
            }

            // Apply syntax highlighting
            if (window.Prism) {
                Prism.highlightElement(modalJsonContent);
            }

            openModal();
        });
    });

    // Event listeners for closing the modal
    closeModalBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && modal.style.display === 'flex') {
            closeModal();
        }
    });
});
</script>

<?php
include_once ROOT_PATH . '/partials/footer.php';
?>
