<?php
session_start();
require_once __DIR__ . '/../core/helpers.php'; // For app_log

// --- Konfigurasi ---
$log_dir = __DIR__ . '/../logs';
$lines_to_show = 20;

// --- Dapatkan daftar file log ---
$log_files = glob($log_dir . '/*.log');
$log_names = array_map(function($file) {
    return basename($file, '.log');
}, $log_files);

// --- Tentukan log yang akan ditampilkan ---
$selected_log = isset($_GET['log']) && in_array($_GET['log'], $log_names) ? $_GET['log'] : ($log_names[0] ?? null);

// --- Handle Aksi ---
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'clear_log' && $selected_log) {
        $file_to_clear = $log_dir . '/' . $selected_log . '.log';
        if (file_exists($file_to_clear)) {
            file_put_contents($file_to_clear, '');
            app_log("Log file '{$selected_log}.log' dibersihkan oleh admin.", 'app');
            $message = "Log '{$selected_log}' berhasil dibersihkan.";
        }
    }
}

// --- Baca isi log ---
$log_content = [];
if ($selected_log) {
    $log_file_path = $log_dir . '/' . $selected_log . '.log';
    if (file_exists($log_file_path) && filesize($log_file_path) > 0) {
        // Baca semua baris
        $lines = file($log_file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        // Ambil N baris terakhir
        $log_content = array_slice($lines, -$lines_to_show);
        // Urutkan dari yang terbaru
        $log_content = array_reverse($log_content);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Viewer - Admin Panel</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 40px; background-color: #f4f6f8; color: #333; }
        .container { max-width: 1000px; margin: 0 auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        nav { margin-bottom: 20px; }
        nav a { text-decoration: none; color: #007bff; padding: 10px; }
        nav a.active { font-weight: bold; }
        .log-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .log-viewer { background-color: #2d2d2d; color: #f1f1f1; padding: 20px; border-radius: 5px; font-family: 'SF Mono', 'Consolas', 'Menlo', monospace; font-size: 0.9em; white-space: pre-wrap; word-wrap: break-word; max-height: 60vh; overflow-y: auto; }
        .log-viewer p { margin: 0 0 5px 0; }
        .log-viewer p.empty { color: #888; }
        .btn { padding: 8px 12px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; }
        .btn-danger { background-color: #dc3545; }
        .btn-danger:hover { background-color: #c82333; }
        select { padding: 8px; border-radius: 4px; border: 1px solid #ccc; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; background-color: #d4edda; color: #155724; }
    </style>
</head>
<body>
    <div class="container">
        <nav>
            <a href="index.php">Percakapan</a> |
            <a href="bots.php">Kelola Bot</a> |
            <a href="users.php">Pengguna</a> |
            <a href="roles.php">Manajemen Peran</a> |
            <a href="media_logs.php">Log Media</a> |
            <a href="channels.php">Channel</a> |
            <a href="database.php">Database</a> |
            <a href="logs.php" class="active">Logs</a>
        </nav>

        <h1>Log Viewer</h1>

        <?php if ($message): ?>
            <div class="alert"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="log-controls">
            <form action="logs.php" method="get">
                <label for="log_select">Pilih Log:</label>
                <select name="log" id="log_select" onchange="this.form.submit()">
                    <?php foreach ($log_names as $name): ?>
                        <option value="<?= $name ?>" <?= ($selected_log === $name) ? 'selected' : '' ?>>
                            <?= ucfirst($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <div>
                <a href="logs.php?log=<?= $selected_log ?>" class="btn">Refresh</a>
                <?php if ($selected_log): ?>
                <form action="logs.php?log=<?= $selected_log ?>" method="post" style="display:inline;" onsubmit="return confirm('Anda yakin ingin membersihkan log ini? Aksi ini tidak dapat diurungkan.');">
                    <input type="hidden" name="action" value="clear_log">
                    <button type="submit" class="btn btn-danger">Bersihkan Log</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="log-viewer">
            <?php if (empty($log_content)): ?>
                <p class="empty">Log ini kosong.</p>
            <?php else: ?>
                <?php foreach ($log_content as $line): ?>
                    <p><?= htmlspecialchars($line) ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
