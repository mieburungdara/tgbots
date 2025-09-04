<?php
// This view assumes all data variables are available in the $data array.
?>

<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4"><?= htmlspecialchars($data['page_title']) ?></h1>

    <?php if ($data['message']): ?>
        <div class="alert alert-success"><?= htmlspecialchars($data['message']) ?></div>
    <?php endif; ?>

    <div class="flex justify-between items-center mb-4">
        <form action="/admin/logs" method="get" class="flex items-center space-x-2">
            <label for="level_select">Filter by Level:</label>
            <select name="level" id="level_select" onchange="this.form.submit()" class="p-2 border rounded-md">
                <option value="all" <?= ($data['selected_level'] === 'all') ? 'selected' : '' ?>>All Levels</option>
                <?php foreach ($data['log_levels'] as $level): ?>
                    <option value="<?= htmlspecialchars($level) ?>" <?= ($data['selected_level'] === $level) ? 'selected' : '' ?>>
                        <?= ucfirst(htmlspecialchars($level)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <div>
            <a href="/admin/logs?level=<?= $data['selected_level'] ?>" class="btn">Refresh</a>
            <form action="/admin/logs/clear" method="post" style="display:inline;" onsubmit="return confirm('Anda yakin ingin MENGHAPUS SEMUA log? Aksi ini tidak dapat diurungkan.');">
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
                <?php if (empty($data['logs'])): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4">No logs found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($data['logs'] as $log): ?>
                        <?php
                            $level_color = match($log['level']) {
                                'error' => '#ef4444',
                                'system' => '#a855f7',
                                'debug' => '#3b82f6',
                                'info' => '#22c55e',
                                default => '#6b7280',
                            };
                        ?>
                        <tr class="border-b" style="border-left: 4px solid <?= $level_color ?>;">
                            <td class="px-4 py-2"><?= htmlspecialchars($log['id']) ?></td>
                            <td class="px-4 py-2 whitespace-nowrap"><?= htmlspecialchars($log['created_at']) ?></td>
                            <td class="px-4 py-2">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" style="background-color: <?= $level_color ?>1A; color: <?= $level_color ?>;">
                                    <?= htmlspecialchars($log['level']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-2"><?= htmlspecialchars($log['message']) ?></td>
                            <td class="px-4 py-2">
                                <?php if (!empty($log['context'])): ?>
                                    <button class="text-blue-500 text-xs context-toggle-button" data-target="context-<?= $log['id'] ?>">Show</button>
                                    <pre id="context-<?= $log['id'] ?>" class="bg-gray-100 p-2 rounded-md text-xs" style="display: none;"><code><?php
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

    <div class="mt-4 flex justify-center">
        <nav class="inline-flex rounded-md shadow">
            <?php
            $currentPage = $data['current_page'];
            $totalPages = $data['total_pages'];
            if ($totalPages > 1):
                $queryParams = [];
                if ($data['selected_level'] !== 'all') {
                    $queryParams['level'] = $data['selected_level'];
                }
            ?>
                <a href="?<?= htmlspecialchars(http_build_query(array_merge($queryParams, ['page' => $currentPage - 1]))) ?>"
                   class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-l-md hover:bg-gray-50 <?= ($currentPage <= 1) ? 'opacity-50 cursor-not-allowed' : '' ?>">
                    &laquo; Previous
                </a>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?<?= htmlspecialchars(http_build_query(array_merge($queryParams, ['page' => $i]))) ?>"
                       class="px-4 py-2 text-sm font-medium <?= ($i == $currentPage) ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-t border-b border-gray-300' ?> hover:bg-gray-50">
                        <?= htmlspecialchars($i) ?>
                    </a>
                <?php endfor; ?>
                <a href="?<?= htmlspecialchars(http_build_query(array_merge($queryParams, ['page' => $currentPage + 1]))) ?>"
                   class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-r-md hover:bg-gray-50 <?= ($currentPage >= $totalPages) ? 'opacity-50 cursor-not-allowed' : '' ?>">
                    Next &raquo;
                </a>
            <?php endif; ?>
        </nav>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleButtons = document.querySelectorAll('.context-toggle-button');
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const targetPre = document.getElementById(targetId);
            if (targetPre) {
                const isHidden = targetPre.style.display === 'none';
                targetPre.style.display = isHidden ? 'block' : 'none';
                this.textContent = isHidden ? 'Hide' : 'Show';
            }
        });
    });
});
</script>
