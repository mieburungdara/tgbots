<?php
// This layout file expects:
// - $page_title: string
// - $content: string (the rendered view content)
// It relies on the is_active_nav() helper function.

$current_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($data['page_title'] ?? 'Admin Panel') ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; background-color: #f4f6f8; color: #333; }
        .header { background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 0 20px; }
        .nav-container { max-width: 1600px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .nav-container h1 { font-size: 1.5em; color: #333; }
        nav { display: flex; gap: 5px; padding: 10px 0; flex-wrap: wrap; }
        nav a { text-decoration: none; color: #007bff; padding: 10px 15px; border-radius: 5px; white-space: nowrap; }
        nav a:hover { background-color: #f0f0f0; }
        nav a.active { font-weight: bold; background-color: #e9ecef; }
        .container { max-width: 1600px; margin: 20px auto; padding: 20px; }
        .content { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; word-wrap: break-word; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        h1, h2, h3 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
        th { background-color: #f2f2f2; }
        form { margin-top: 20px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9; }
        input[type="text"], input[type="number"], input[type="password"], textarea, select { width: calc(100% - 22px); padding: 10px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; }
        button, .btn { padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        button:hover, .btn:hover { background-color: #0056b3; }
        .btn-edit { background-color: #28a745; }
        .btn-edit:hover { background-color: #218838; }
        .btn-delete { background-color: #dc3545; }
        .btn-delete:hover { background-color: #c82333; }
        body.admin-body { display: flex; }
        .sidebar { width: 240px; flex-shrink: 0; background-color: #fff; box-shadow: 2px 0 5px rgba(0,0,0,0.1); min-height: 100vh; display: flex; flex-direction: column; }
        .sidebar-header { padding: 20px; font-size: 1.5em; font-weight: bold; text-align: center; border-bottom: 1px solid #f0f0f0; }
        .sidebar-header a { text-decoration: none; color: inherit; }
        .sidebar-nav { flex-grow: 1; display: flex; flex-direction: column; padding: 15px; }
        .sidebar-nav a { text-decoration: none; color: #333; padding: 12px 15px; border-radius: 5px; margin-bottom: 5px; }
        .sidebar-nav a:hover { background-color: #f0f0f0; }
        .sidebar-nav a.active { font-weight: bold; background-color: #007bff; color: #fff; }
        .sidebar-footer { padding: 15px; border-top: 1px solid #f0f0f0; margin-top: 15px; }
        .admin-main-content { flex-grow: 1; }
        .conv-layout { display: flex; margin: -20px; height: calc(100vh - 85px); }
        .conv-sidebar { width: 280px; flex-shrink: 0; background-color: #fff; border-right: 1px solid #dee2e6; overflow-y: auto; }
        .conv-sidebar-header { padding: 15px; border-bottom: 1px solid #dee2e6; }
        .conv-sidebar-header h2 { margin: 0; font-size: 1.2rem; }
        .conv-bot-list a { display: block; padding: 12px 15px; text-decoration: none; color: #212529; border-bottom: 1px solid #e9ecef; }
        .conv-bot-list a:hover { background-color: #f8f9fa; }
        .conv-bot-list a.active { background-color: #007bff; color: #fff; font-weight: 600; }
        .conv-main { flex-grow: 1; padding: 20px; overflow-y: auto; background-color: #fff; }
        .conv-list { list-style-type: none; padding: 0; margin: 0; }
        .conv-card a { text-decoration: none; color: inherit; display: flex; align-items: flex-start; width: 100%; }
        .conv-avatar { width: 48px; height: 48px; border-radius: 50%; background-color: #007bff; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; font-weight: bold; flex-shrink: 0; margin-right: 15px; }
        .conv-details { flex-grow: 1; }
        .conv-header { display: flex; justify-content: space-between; align-items: baseline; }
        .conv-name { font-weight: 600; }
        .conv-time { font-size: 0.75rem; color: #6c757d; }
        .conv-message { font-size: 0.9rem; color: #495057; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 95%; }

        /* Modal Styles */
        .modal-overlay { display: none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background-color: #fff; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); position: relative; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e5e5; padding-bottom: 10px; margin-bottom: 20px; }
        .modal-header h2 { margin: 0; }
        .modal-close-adjust, .modal-close-log { background: none; border: none; font-size: 1.5rem; font-weight: bold; line-height: 1; color: #000; text-shadow: 0 1px 0 #fff; opacity: 0.5; cursor: pointer; }
        .modal-close-adjust:hover, .modal-close-log:hover { opacity: 0.8; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-actions { margin-top: 20px; text-align: right; }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-okaidia.min.css" />
</head>
<body class="admin-body">

    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="/admin">Admin Panel</a>
        </div>
        <nav class="sidebar-nav">
            <a href="/admin/dashboard" class="<?= is_active_nav('admin/dashboard', $current_path) ? 'active' : '' ?>">Percakapan</a>
            <a href="/admin/bots" class="<?= is_active_nav('admin/bots', $current_path) ? 'active' : '' ?>">Kelola Bot</a>
            <a href="/admin/users" class="<?= is_active_nav('admin/users', $current_path) ? 'active' : '' ?>">Pengguna</a>
            <a href="/admin/balance" class="<?= is_active_nav('admin/balance', $current_path) ? 'active' : '' ?>">Manajemen Saldo</a>
            <a href="/admin/roles" class="<?= is_active_nav('admin/roles', $current_path) ? 'active' : '' ?>">Manajemen Peran</a>
            <a href="/admin/packages" class="<?= is_active_nav('admin/packages', $current_path) ? 'active' : '' ?>">Manajemen Konten</a>
            <a href="/admin/media_logs" class="<?= is_active_nav('admin/media_logs', $current_path) ? 'active' : '' ?>">Log Media</a>
            <a href="/admin/sales_channels" class="<?= is_active_nav('admin/sales_channels', $current_path) ? 'active' : '' ?>">Channel Jualan</a>
            <a href="/admin/storage_channels" class="<?= is_active_nav('admin/storage_channels', $current_path) ? 'active' : '' ?>">Channel Penyimpanan</a>
            <a href="/admin/database" class="<?= is_active_nav('admin/database', $current_path) ? 'active' : '' ?>">Database</a>
            <a href="/admin/logs" class="<?= is_active_nav('admin/logs', $current_path) ? 'active' : '' ?>">Log Aplikasi</a>
            <a href="/admin/telegram_logs" class="<?= is_active_nav('admin/telegram_logs', $current_path) ? 'active' : '' ?>">Log Error Telegram</a>
            <a href="/admin/debug_feed" class="<?= is_active_nav('admin/debug_feed', $current_path) ? 'active' : '' ?>">Debug Feed</a>
            <a href="/admin/api_test" class="<?= is_active_nav('admin/api_test', $current_path) ? 'active' : '' ?>">Tes API</a>
        </nav>
        <div class="sidebar-footer">
            <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true): ?>
                <div style="font-size: 14px; color: #6c757d; margin-bottom: 10px;">
                    Masuk sebagai:<br>
                    <strong style="color: #333;"><?= htmlspecialchars($_SESSION['user_first_name'] ?? 'Admin') ?></strong>
                </div>
                <a href="/logout" style="display: block; text-align: center; background: #f8d7da; color: #721c24; padding: 8px; border-radius: 4px; text-decoration: none; font-weight: bold;">Logout</a>
            <?php endif; ?>
        </div>
    </aside>

    <div class="admin-main-content">
        <main class="container">
            <div class="content">
                <?= $content ?? '' ?>
            </div>
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
</body>
</html>
