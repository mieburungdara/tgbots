<?php
// This view assumes all data variables are available in the $data array.
// It relies on the is_active_nav() helper function.
?>

<style>
    /* Custom styles for log viewer */
    .log-viewer-header {
        position: sticky;
        top: 0;
        z-index: 10;
        background-color: #f4f6f8; /* Match body background */
        padding: 1rem 0;
        border-bottom: 1px solid #e0e0e0;
        margin-bottom: 1rem;
    }
    .log-content-wrapper {
        background-color: #2d2d2d; /* Dark background for code */
        color: #f8f8f2; /* Light text for code */
        padding: 1rem;
        border-radius: 0.5rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        overflow-x: auto; /* Enable horizontal scrolling for long lines */
    }
    .log-content-wrapper pre {
        margin: 0;
        white-space: pre-wrap; /* Wrap long lines */
        word-break: break-all; /* Break words to prevent overflow */
    }
    .log-content-wrapper code {
        font-family: 'Fira Code', 'Consolas', 'Monaco', 'Andale Mono', 'Ubuntu Mono', monospace;
        font-size: 0.875rem; /* Smaller font size for readability */
        line-height: 1.4;
    }
    .alert-custom {
        padding: 1rem;
        border-radius: 0.25rem;
        margin-bottom: 1rem;
        font-weight: 600;
    }
    .alert-custom.alert-danger {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    .alert-custom.alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    .pagination-controls {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #e0e0e0;
    }
</style>

<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4"><?= htmlspecialchars($data['page_title']) ?></h1>

    <?php if ($data['error_message']): ?>
        <div class="alert-custom alert-danger"><?= htmlspecialchars($data['error_message']) ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert-custom alert-success"><?= htmlspecialchars($_SESSION['flash_message']) ?></div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <div class="log-viewer-header flex justify-between items-center">
        <a href="/xoradmin/file_logs" class="btn">Kembali ke Daftar Log</a>
        <?php if ($data['raw_log_content']): ?>
            <form action="/xoradmin/file_logs/clear/<?= htmlspecialchars($data['log_file_name']) ?>" method="post" style="display:inline;" onsubmit="return confirm('Anda yakin ingin MENGHAPUS SEMUA isi log file <?= htmlspecialchars($data['log_file_name']) ?>? Aksi ini tidak dapat diurungkan.');">
                <button type="submit" class="btn btn-delete">Clear Log File</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($data['raw_log_content']): ?>
        <div class="log-content-wrapper">
            <pre><code class="language-log"><?= $data['raw_log_content'] ?></code></pre>
        </div>

        <div class="pagination-controls flex justify-between items-center">
            <form action="" method="get" class="flex items-center space-x-2">
                <label for="lines_per_page" class="text-sm text-gray-700">Baris per halaman:</label>
                <select name="lines" id="lines_per_page" onchange="this.form.submit()" class="p-2 border rounded-md text-sm">
                    <?php
                    $linesOptions = [10, 25, 50, 100, 250, 500];
                    foreach ($linesOptions as $option) {
                        $selected = ($data['lines_per_page'] == $option) ? 'selected' : '';
                        echo "<option value=\"{$option}\" {$selected}>{$option}</option>";
                    }
                    ?>
                </select>
                <input type="hidden" name="page" value="<?= htmlspecialchars($data['current_page']) ?>">
            </form>

            <nav class="inline-flex rounded-md shadow">
                <?php
                $currentPage = $data['current_page'];
                $totalPages = $data['total_pages'];
                $logFileName = htmlspecialchars($data['log_file_name']);
                $linesPerPage = htmlspecialchars($data['lines_per_page']);

                if ($totalPages > 1):
                    // Previous button
                    $prevPageUrl = "/xoradmin/file_logs/{$logFileName}?page=" . ($currentPage - 1) . "&lines={$linesPerPage}";
                    $prevDisabled = ($currentPage <= 1) ? 'opacity-50 cursor-not-allowed' : '';
                    echo "<a href=\"{$prevPageUrl}\" class=\"px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-l-md hover:bg-gray-50 {$prevDisabled}\">&laquo; Previous</a>";

                    // Page numbers
                    for ($i = 1; $i <= $totalPages; $i++):
                        $pageUrl = "/xoradmin/file_logs/{$logFileName}?page={$i}&lines={$linesPerPage}";
                        $activeClass = ($i == $currentPage) ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-t border-b border-gray-300';
                        echo "<a href=\"{$pageUrl}\" class=\"px-4 py-2 text-sm font-medium {$activeClass} hover:bg-gray-50\">{$i}</a>";
                    endfor;

                    // Next button
                    $nextPageUrl = "/xoradmin/file_logs/{$logFileName}?page=" . ($currentPage + 1) . "&lines={$linesPerPage}";
                    $nextDisabled = ($currentPage >= $totalPages) ? 'opacity-50 cursor-not-allowed' : '';
                    echo "<a href=\"{$nextPageUrl}\" class=\"px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-r-md hover:bg-gray-50 {$nextDisabled}\">Next &raquo;</a>";
                endif;
                ?>
            </nav>
        </div>
    <?php else: ?>
        <div class="alert-custom alert-info">No log content available.</div>
    <?php endif; ?>
</div>
