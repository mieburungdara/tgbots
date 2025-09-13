<?php
// This view assumes all data variables are available in the $data array.
?>

<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4"><?= htmlspecialchars($data['page_title']) ?></h1>

    <?php if ($data['log_files']): ?>
        <div class="bg-white shadow-md rounded-lg p-4">
            <ul class="list-disc pl-5">
                <?php foreach ($data['log_files'] as $logFile): ?>
                    <li>
                        <a href="/xoradmin/file_logs/<?= htmlspecialchars($logFile) ?>" class="text-blue-600 hover:underline">
                            <?= htmlspecialchars($logFile) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php else: ?>
        <div class="alert alert-info">Tidak ada file log yang ditemukan di direktori 'logs/'.</div>
    <?php endif; ?>
</div>