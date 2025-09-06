<?php
// This view expects:
// - $page_title: string
// - $parsed_logs: array (array of structured log entries)
// - $error_message: string|null (error message if file not found or unreadable)
?>

<h1 class="mb-4"><?= htmlspecialchars($data['page_title'] ?? 'Public Error Log') ?></h1>

<?php if (isset($data['error_message']) && $data['error_message']): ?>
    <div class="alert alert-danger">
        <?= htmlspecialchars($data['error_message']) ?>
    </div>
<?php else: ?>
    <?php if (!empty($data['parsed_logs'])): ?>
        <div class="mb-3">
            <form action="/xoradmin/public_error_log/clear" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin membersihkan semua log error publik? Aksi ini tidak dapat dibatalkan.');">
                <button type="submit" class="btn btn-danger">Bersihkan Log Error</button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th style="width: 180px;">Timestamp</th>
                        <th style="width: 120px;">Level</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['parsed_logs'] as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['timestamp']) ?></td>
                            <td><?= htmlspecialchars($log['level']) ?></td>
                            <td><?= htmlspecialchars($log['message']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            Tidak ada log error publik yang ditemukan atau file log kosong.
        </div>
    <?php endif; ?>
<?php endif; ?>