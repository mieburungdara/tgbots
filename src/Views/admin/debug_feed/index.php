<?php
// This view assumes 'updates' and 'pagination' are available in the $data array.
$current_page = $data['pagination']['current_page'];
$total_pages = $data['pagination']['total_pages'];
?>

<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4"><?= htmlspecialchars($data['page_title']) ?></h1>
    <p class="mb-4">This page displays raw JSON payloads received from Telegram. Newest updates appear first.</p>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-gray-200">
                <tr>
                    <th class="px-4 py-2 text-left w-1/12">ID</th>
                    <th class="px-4 py-2 text-left w-2/12">Timestamp</th>
                    <th class="px-4 py-2 text-left w-9/12">Payload</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['updates'])): ?>
                    <tr>
                        <td colspan="3" class="text-center py-4 text-gray-500">No updates received yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($data['updates'] as $update): ?>
                        <tr class="border-b">
                            <td class="px-4 py-2 align-top font-mono"><?= htmlspecialchars($update['id']) ?></td>
                            <td class="px-4 py-2 align-top font-mono"><?= htmlspecialchars($update['created_at']) ?></td>
                            <td class="px-4 py-2">
                                <pre style="max-height: 400px; overflow-y: auto;"><code class="language-json"><?php
                                    $json_data = json_decode($update['payload']);
                                    echo htmlspecialchars(json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                                ?></code></pre>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <div class="mt-6 flex justify-center">
        <nav class="inline-flex rounded-md shadow">
            <?php if ($total_pages > 1): ?>
                <a href="?page=<?= $current_page - 1 ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-l-md hover:bg-gray-50 <?= ($current_page <= 1) ? 'opacity-50 cursor-not-allowed' : '' ?>">&laquo; Previous</a>
                <?php
                $window = 2;
                for ($i = 1; $i <= $total_pages; $i++):
                    if ($i == 1 || $i == $total_pages || ($i >= $current_page - $window && $i <= $current_page + $window)):
                ?>
                    <a href="?page=<?= $i ?>" class="px-4 py-2 text-sm font-medium <?= ($i == $current_page) ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-t border-b border-gray-300' ?> hover:bg-gray-50"><?= $i ?></a>
                <?php
                    elseif ($i == $current_page - $window - 1 || $i == $current_page + $window + 1):
                ?>
                    <span class="px-4 py-2 text-sm font-medium bg-white text-gray-500 border-t border-b border-gray-300">...</span>
                <?php
                    endif;
                endfor;
                ?>
                <a href="?page=<?= $current_page + 1 ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-r-md hover:bg-gray-50 <?= ($current_page >= $total_pages) ? 'opacity-50 cursor-not-allowed' : '' ?>">Next &raquo;</a>
            <?php endif; ?>
        </nav>
    </div>
</div>
