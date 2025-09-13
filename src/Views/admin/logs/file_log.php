<?php
// This view assumes all data variables are available in the $data array.
?>

<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4"><?= htmlspecialchars($data['page_title']) ?></h1>

    <?php if ($data['error_message']): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($data['error_message']) ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_message']) ?></div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <div class="flex justify-between items-center mb-4">
        <a href="/xoradmin/file_logs" class="btn">Kembali ke Daftar Log</a>
        <?php if ($data['raw_log_content']): ?>
            <form action="/xoradmin/file_logs/clear/<?= htmlspecialchars($data['log_file_name']) ?>" method="post" style="display:inline;" onsubmit="return confirm('Anda yakin ingin MENGHAPUS SEMUA isi log file <?= htmlspecialchars($data['log_file_name']) ?>? Aksi ini tidak dapat diurungkan.');">
                <button type="submit" class="btn btn-danger">Clear Log File</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($data['raw_log_content']): ?>
        <div class="bg-white shadow-md rounded-lg p-4">
            <pre class="whitespace-pre-wrap text-sm"><?= $data['raw_log_content'] ?></pre>
        </div>

        <div class="mt-4 flex justify-between items-center">
            <form action="" method="get" class="flex items-center space-x-2">
                <label for="lines_per_page">Baris per halaman:</label>
                <select name="lines" id="lines_per_page" onchange="this.form.submit()" class="p-2 border rounded-md">
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
        <div class="alert alert-info">No log content available.</div>
    <?php endif; ?>
</div>