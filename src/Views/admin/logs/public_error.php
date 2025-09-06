<?php
// This view expects:
// - $page_title: string
// - $log_content: string (content of the log file)
// - $error_message: string|null (error message if file not found or unreadable)
?>

<h1 class="mb-4"><?= htmlspecialchars($data['page_title'] ?? 'Public Error Log') ?></h1>

<?php if (isset($data['error_message']) && $data['error_message']): ?>
    <div class="alert alert-danger">
        <?= htmlspecialchars($data['error_message']) ?>
    </div>
<?php else: ?>
    <?php if (isset($data['log_content']) && !empty($data['log_content'])): ?>
        <pre style="background-color: #f8f9fa; padding: 15px; border: 1px solid #e9ecef; border-radius: 5px; white-space: pre-wrap; word-break: break-all;"><?= htmlspecialchars($data['log_content']) ?></pre>
    <?php else: ?>
        <div class="alert alert-info">
            Tidak ada log error publik yang ditemukan atau file log kosong.
        </div>
    <?php endif; ?>
<?php endif; ?>