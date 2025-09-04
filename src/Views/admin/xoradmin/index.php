<?php
// This view assumes all necessary data is available in the $data array.
if (!function_exists('format_currency')) {
    function format_currency($number, $currency = 'Rp') {
        if (is_null($number)) {
            $number = 0;
        }
        return $currency . ' ' . number_format($number, 0, ',', '.');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XOR Admin Panel</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; color: #333; max-width: 1200px; margin: 20px auto; padding: 0 20px; background-color: #f4f4f4; }
        .container { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1, h2, h3 { color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .warning { background-color: #fcf8e3; border: 1px solid #faebcc; color: #8a6d3b; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .message, .alert-success { background-color: #dff0d8; border: 1px solid #d6e9c6; color: #3c763d; padding: 15px; border-radius: 4px; margin-top: 20px; white-space: pre-wrap; word-wrap: break-word; }
        .error { background-color: #f2dede; border: 1px solid #ebccd1; color: #a94442; padding: 15px; border-radius: 4px; margin-top: 20px; }
        form { margin-top: 20px; }
        input, select, textarea, button { width: 100%; padding: 10px; margin-bottom: 15px; border-radius: 4px; border: 1px solid #ccc; box-sizing: border-box; }
        button, input[type="submit"] { background-color: #337ab7; color: white; font-size: 16px; border: none; cursor: pointer; transition: background-color 0.3s; }
        button:hover, input[type="submit"]:hover { background-color: #286090; }
        .logout-form { text-align: right; margin: -20px 0 20px 0; border: none; }
        .tabs { border-bottom: 1px solid #ddd; display: flex; flex-wrap: wrap; }
        .tab-link { padding: 10px 15px; cursor: pointer; border: 1px solid transparent; border-bottom: none; margin-bottom: -1px; background: #f8f8f8; text-decoration: none; color: #337ab7; }
        .tab-link.active { background: #fff; border-color: #ddd; border-bottom-color: #fff; border-radius: 4px 4px 0 0; }
        .tab-content { display: none; padding: 20px; border: 1px solid #ddd; border-top: none; }
        .tab-content.active { display: block; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; }
        .modal { display: none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 700px; border-radius: 8px; }
        .close-button { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <h1>XOR Admin Panel</h1>
        <div class="logout-form">
            <a href="/logout" style="display: inline-block; padding: 8px 15px; background-color: #5bc0de; color: white; text-decoration: none; border-radius: 4px; font-size: 14px;">Logout</a>
        </div>
        <div class="tabs">
            <a href="?action=dashboard" class="tab-link <?= ($data['active_tab'] === 'dashboard' || $data['active_tab'] === '') ? 'active' : '' ?>">Dashboard</a>
            <a href="?action=users" class="tab-link <?= $data['active_tab'] === 'users' ? 'active' : '' ?>">Pengguna</a>
            <a href="?action=balance" class="tab-link <?= $data['active_tab'] === 'balance' ? 'active' : '' ?>">Saldo</a>
            <a href="?action=content" class="tab-link <?= $data['active_tab'] === 'content' ? 'active' : '' ?>">Konten</a>
            <a href="?action=storage_channels" class="tab-link <?= $data['active_tab'] === 'storage_channels' ? 'active' : '' ?>">Chan. Penyimpanan</a>
            <a href="?action=feature_channels" class="tab-link <?= $data['active_tab'] === 'feature_channels' ? 'active' : '' ?>">Chan. Fitur</a>
            <a href="?action=analytics" class="tab-link <?= $data['active_tab'] === 'analytics' ? 'active' : '' ?>">Analitik</a>
            <a href="?action=logs" class="tab-link <?= $data['active_tab'] === 'logs' ? 'active' : '' ?>">Logs</a>
            <a href="?action=bots" class="tab-link <?= $data['active_tab'] === 'bots' || $data['active_tab'] === 'edit_bot' ? 'active' : '' ?>">Bot</a>
            <a href="?action=roles" class="tab-link <?= $data['active_tab'] === 'roles' ? 'active' : '' ?>">Peran</a>
            <a href="?action=api_test" class="tab-link">API Test</a>
            <a href="?action=db_reset" class="tab-link <?= $data['active_tab'] === 'db_reset' ? 'active' : '' ?>">Database</a>
        </div>
        <div class="main-content">
            <?php if (($data['active_tab'] === 'dashboard' || $data['active_tab'] === '')): ?>
                <div class="tab-content active">
                    <h2>Dashboard Percakapan</h2>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="responseModal" class="modal"></div>
    <script>
        // JS will be added later
    </script>
</body>
</html>
