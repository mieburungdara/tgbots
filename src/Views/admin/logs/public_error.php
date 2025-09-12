<?php
// This view expects:
// - $page_title: string
// - $parsed_logs: array (array of structured log entries)
// - $raw_log_content: string|null (raw log content)
// - $error_message: string|null (error message if file not found or unreadable)
?>

<h1 class="mb-4"><?= htmlspecialchars($data['page_title'] ?? 'Public Error Log') ?></h1>

<?php if (isset($data['error_message']) && $data['error_message']): ?>
    <div class="alert alert-danger">
        <?= htmlspecialchars($data['error_message']) ?>
    </div>
<?php elseif (isset($data['raw_log_content'])):
    // Raw view
    ?>
    <div class="mb-3">
        <a href="/xoradmin/public_error_log" class="btn btn-primary">View Parsed</a>
        <form action="/xoradmin/public_error_log/clear" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin membersihkan semua log error publik? Aksi ini tidak dapat dibatalkan.');" style="display: inline-block;">
            <button type="submit" class="btn btn-danger">Bersihkan Log</button>
        </form>
    </div>
    <pre style="white-space: pre-wrap; word-wrap: break-word; background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 1rem; color: #212529;"><?= $data['raw_log_content'] ?></pre>
<?php elseif (!empty($data['parsed_logs'])):
    // Parsed view
    ?>
    <div class="mb-3">
        <a href="/xoradmin/public_error_log?raw=true" class="btn btn-secondary">View Raw</a>
        <form action="/xoradmin/public_error_log/clear" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin membersihkan semua log error publik? Aksi ini tidak dapat dibatalkan.');" style="display: inline-block;">
            <button type="submit" class="btn btn-danger">Bersihkan Log</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover">
            <thead class="thead-light">
                <tr>
                    <th style="width: 200px;">Timestamp</th>
                    <th style="width: 150px;">Level</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['parsed_logs'] as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['timestamp']) ?></td>
                        <td>
                            <span class="badge bg-<?= strpos(strtolower($log['level']), 'fatal') !== false || strpos(strtolower($log['level']), 'parse') !== false ? 'danger' : (strpos(strtolower($log['level']), 'warning') !== false ? 'warning' : 'secondary') ?>">
                                <?= htmlspecialchars($log['level']) ?>
                            </span>
                        </td>
                        <td style="white-space: pre-wrap; word-wrap: break-word; font-family: monospace;"><?= htmlspecialchars($log['message']) ?></td>
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
