<?php
// This view assumes all data variables are available in the $data array.
?>

<style>
    /* Custom styles for log file list */
    .log-file-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1rem;
    }
    .log-file-card {
        background-color: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 0.5rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        padding: 1rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: all 0.2s ease-in-out;
    }
    .log-file-card:hover {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }
    .log-file-card h3 {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: #333;
    }
    .log-file-card .btn-view {
        display: inline-block;
        margin-top: 1rem;
        padding: 0.5rem 1rem;
        background-color: #007bff;
        color: white;
        border-radius: 0.25rem;
        text-decoration: none;
        text-align: center;
        transition: background-color 0.2s ease;
    }
    .log-file-card .btn-view:hover {
        background-color: #0056b3;
    }
    .alert-custom {
        padding: 1rem;
        border-radius: 0.25rem;
        margin-bottom: 1rem;
        font-weight: 600;
    }
    .alert-custom.alert-info {
        background-color: #d1ecf1;
        color: #0c5460;
        border: 1px solid #bee5eb;
    }
</style>

<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4"><?= htmlspecialchars($data['page_title']) ?></h1>

    <?php if ($data['log_files']): ?>
        <div class="log-file-grid">
            <?php foreach ($data['log_files'] as $logFile): ?>
                <div class="log-file-card">
                    <h3><?= htmlspecialchars($logFile) ?></h3>
                    <a href="/xoradmin/file_logs/<?= htmlspecialchars($logFile) ?>" class="btn-view">Lihat Log</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert-custom alert-info">Tidak ada file log yang ditemukan di direktori 'logs/'.</div>
    <?php endif; ?>
</div>