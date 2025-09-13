<?php
// This view is for the file log viewer.
// It expects $data to contain log_files, log_file_name, raw_log_content, etc.
$current_log_file = $data['log_file_name'] ?? null;
?>

<style>
    .log-viewer-layout {
        display: flex;
        gap: 1.5rem;
        height: calc(100vh - 150px); /* Adjust height based on your header/footer */
    }
    .log-file-list {
        flex: 0 0 280px; /* Fixed width for the file list sidebar */
        background-color: #f8f9fa;
        border-radius: 0.5rem;
        padding: 1rem;
        overflow-y: auto;
        border: 1px solid #e9ecef;
    }
    .log-file-list h3 {
        margin-top: 0;
        margin-bottom: 1rem;
        font-size: 1.1rem;
        font-weight: 600;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 0.75rem;
    }
    .log-file-list ul {
        list-style-type: none;
        padding: 0;
        margin: 0;
    }
    .log-file-list li a {
        display: block;
        padding: 0.6rem 1rem;
        text-decoration: none;
        color: #212529;
        border-radius: 0.375rem;
        margin-bottom: 0.25rem;
        font-size: 0.9rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: background-color 0.2s ease;
    }
    .log-file-list li a:hover {
        background-color: #e9ecef;
    }
    .log-file-list li a.active {
        background-color: #007bff;
        color: #fff;
        font-weight: 600;
    }

    .log-content-area {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }
    .log-content-wrapper {
        flex-grow: 1;
        background-color: #1e293b; /* Dark background for code */
        color: #f8f8f2; /* Light text for code */
        padding: 1rem;
        border-radius: 0.5rem;
        overflow: auto; /* Important for scrolling log content */
        font-family: 'Fira Code', 'Consolas', 'Monaco', monospace;
        font-size: 0.875rem;
        line-height: 1.5;
    }
    .log-content-wrapper pre { margin: 0; }
    .log-viewer-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    .pagination-controls {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #e0e0e0;
    }
    .alert-info {
        background-color: #cce5ff;
        color: #004085;
        border: 1px solid #b8daff;
        padding: 1rem;
        border-radius: 0.25rem;
        margin-bottom: 1rem;
    }
    .alert-warning {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeeba;
        padding: 0.5rem 1rem;
        border-radius: 0.25rem;
        margin-bottom: 1rem;
        font-size: 0.9rem;
    }
    .empty-state {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        height: 100%;
        background-color: #f8f9fa;
        border-radius: 0.5rem;
        border: 2px dashed #dee2e6;
    }
    .empty-state p { font-size: 1.2rem; color: #6c757d; }
</style>

<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4"><?= htmlspecialchars($data['page_title']) ?></h1>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert-info"><?= htmlspecialchars($_SESSION['flash_message']) ?></div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <div class="log-viewer-layout">
        <aside class="log-file-list">
            <h3>File Log</h3>
            <ul>
                <?php foreach ($data['log_files'] as $logFile): ?>
                    <li>
                        <a href="/xoradmin/file_logs/<?= htmlspecialchars($logFile) ?>" 
                           class="<?= ($logFile === $current_log_file) ? 'active' : '' ?>"
                           title="<?= htmlspecialchars($logFile) ?>">
                            <?= htmlspecialchars($logFile) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <main class="log-content-area">
            <?php if ($current_log_file): ?>
                <div class="log-viewer-header">
                    <h2 class="text-xl font-semibold">Isi: <?= htmlspecialchars($current_log_file) ?></h2>
                    <?php if ($data['raw_log_content'] !== ''): ?>
                        <form action="/xoradmin/file_logs/clear/<?= base64_encode($current_log_file) ?>" method="post" onsubmit="return confirm('Anda yakin ingin MENGHAPUS SEMUA isi log file ini? Aksi ini tidak dapat diurungkan.');">
                            <button type="submit" class="btn btn-delete">Clear Log File</button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if ($data['error_message']): ?>
                    <div class="alert-warning"><?= htmlspecialchars($data['error_message']) ?></div>
                <?php endif; ?>

                <div class="log-content-wrapper">
                    <?php if (empty($data['raw_log_content'])): ?>
                        <p style="text-align: center; padding: 2rem;">File log ini kosong.</p>
                    <?php else: ?>
                        <pre><?= htmlspecialchars($data['raw_log_content']) ?></pre>
                    <?php endif; ?>
                </div>

                <?php if ($data['total_pages'] > 1): ?>
                    <div class="pagination-controls flex justify-between items-center">
                        <form action="" method="get" class="flex items-center space-x-2">
                            <label for="lines_per_page" class="text-sm text-gray-700">Baris/halaman:</label>
                            <select name="lines" id="lines_per_page" onchange="this.form.submit()" class="p-2 border rounded-md text-sm">
                                <?php foreach ([50, 100, 250, 500] as $option): ?>
                                    <option value="<?= $option ?>" <?= ($data['lines_per_page'] == $option) ? 'selected' : '' ?>><?= $option ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>

                        <nav class="inline-flex rounded-md shadow">
                            <?php for ($i = 1; $i <= $data['total_pages']; $i++): ?>
                                <a href="/xoradmin/file_logs/view/<?= base64_encode($current_log_file) ?>?page=<?= $i ?>&lines=<?= $data['lines_per_page'] ?>"
                                   class="px-4 py-2 text-sm font-medium <?= ($i == $data['current_page']) ? 'bg-blue-600 text-white' : 'bg-white text-gray-700' ?> border border-gray-300 hover:bg-gray-50">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                        </nav>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <p>← Pilih file log dari samping untuk melihat isinya.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>/div>