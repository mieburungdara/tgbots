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
        .sidebar-heading { font-size: 0.8em; color: #6c757d; margin-top: 10px; margin-bottom: 5px; padding: 0 15px; text-transform: uppercase; font-weight: bold; }
        .sidebar-nav a { text-decoration: none; color: #333; padding: 0; border-radius: 5px; margin-bottom: 12px; transition: all 0.2s ease; font-size: 0.9em; }
        .sidebar-nav a:hover { background-color: #f0f0f0; }
        .sidebar-nav a.active { font-weight: bold; background-color: #007bff; color: #fff; }
        .sidebar-footer { padding: 15px; border-top: 1px solid #f0f0f0; margin-top: 15px; transition: all 0.2s ease; }
        .admin-main-content { flex-grow: 1; transition: margin-left 0.3s ease; }
        .sidebar-toggle { position: fixed; top: 15px; left: 15px; z-index: 1001; background: #007bff; color: white; border: none; border-radius: 50%; width: 40px; height: 40px; font-size: 20px; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2); display: none; align-items: center; justify-content: center; }
        .sidebar-toggle:hover { background: #0056b3; }

        /* Collapsed state */
        .sidebar-collapsed .sidebar { width: 0; }
        .sidebar-collapsed .sidebar-header, .sidebar-collapsed .sidebar-nav, .sidebar-collapsed .sidebar-footer { display: none; }
        .sidebar-collapsed .admin-main-content { margin-left: 0; }

        @media (max-width: 768px) {
            .sidebar { position: fixed; z-index: 1000; left: -250px; transition: left 0.3s ease; height: 100%; overflow-y: auto; }
            .sidebar.toggled { left: 0; }
            .admin-main-content { margin-left: 0; }
            .sidebar-toggle { display: flex; }
            body.admin-body { display: block; }
        }

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

        /* Collapsible styles */
        .collapsible-toggle {
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px; /* Match sidebar-nav a padding */
            margin-bottom: 5px;
            border-radius: 5px;
            transition: background-color 0.2s ease;
            user-select: none; /* Prevent text selection on double click */
        }
        .collapsible-toggle:hover {
            background-color: #f0f0f0;
        }
        .collapsible-toggle::after {
            content: '▼'; /* Down arrow */
            font-size: 0.7em;
            transition: transform 0.2s ease;
        }
        .collapsible-toggle.collapsed::after {
            content: '►'; /* Right arrow */
            transform: rotate(0deg);
        }
        .collapsible-content {
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            max-height: 500px; /* Max height when expanded, adjust as needed */
        }
        .collapsible-content.collapsed {
            max-height: 0;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-okaidia.min.css" />
</head>
<body class="admin-body">

    <button class="sidebar-toggle" id="sidebar-toggle-btn">☰</button>

    <aside class="sidebar" id="admin-sidebar">
        <div class="sidebar-header">
            <a href="/xoradmin/dashboard">Admin Panel</a>
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-section">
                <a href="/xoradmin/dashboard" class="<?= is_active_nav('xoradmin/dashboard', $current_path) ? 'active' : '' ?>">Dashboard</a>
            </div>

            <div class="sidebar-section">
                <p class="sidebar-heading collapsible-toggle" data-target="content-sales-collapse">Content & Sales</p>
                <div id="content-sales-collapse" class="collapsible-content">
                    <a href="/xoradmin/analytics" class="<?= is_active_nav('xoradmin/analytics', $current_path) ? 'active' : '' ?>">Analytics</a>
                    <a href="/xoradmin/packages" class="<?= is_active_nav('xoradmin/packages', $current_path) ? 'active' : '' ?>">Content Management</a>
                </div>
            </div>

            <div class="sidebar-section">
                <p class="sidebar-heading collapsible-toggle" data-target="users-roles-collapse">Users & Roles</p>
                <div id="users-roles-collapse" class="collapsible-content">
                    <a href="/xoradmin/users" class="<?= is_active_nav('xoradmin/users', $current_path) ? 'active' : '' ?>">Users</a>
                    <a href="/xoradmin/balance" class="<?= is_active_nav('xoradmin/balance', $current_path) ? 'active' : '' ?>">Balance</a>
                    <a href="/xoradmin/roles" class="<?= is_active_nav('xoradmin/roles', $current_path) ? 'active' : '' ?>">Roles</a>
                </div>
            </div>

            <div class="sidebar-section">
                <p class="sidebar-heading collapsible-toggle" data-target="bot-channels-collapse">Bot & Channels</p>
                <div id="bot-channels-collapse" class="collapsible-content">
                    <a href="/xoradmin/bots" class="<?= is_active_nav('xoradmin/bots', $current_path) ? 'active' : '' ?>">Bot Management</a>
                    <a href="/xoradmin/storage_channels" class="<?= is_active_nav('xoradmin/storage_channels', $current_path) ? 'active' : '' ?>">Storage Channels</a>
                    <a href="/xoradmin/feature-channels" class="<?= is_active_nav('xoradmin/feature-channels', $current_path) ? 'active' : '' ?>">Feature Channels</a>
                </div>
            </div>

            <div class="sidebar-section">
                <p class="sidebar-heading collapsible-toggle" data-target="system-debug-collapse">System & Debug</p>
                <div id="system-debug-collapse" class="collapsible-content">
                    <a href="/xoradmin/health_check" class="<?= is_active_nav('xoradmin/health_check', $current_path) ? 'active' : '' ?>">Health Check</a>
                    <a href="/xoradmin/logs" class="<?= is_active_nav('xoradmin/logs', $current_path) ? 'active' : '' ?>">App Logs</a>
                    <a href="/xoradmin/telegram_logs" class="<?= is_active_nav('xoradmin/telegram_logs', $current_path) ? 'active' : '' ?>">Telegram Error Logs</a>
                    <a href="/xoradmin/media_logs" class="<?= is_active_nav('xoradmin/media_logs', $current_path) ? 'active' : '' ?>">Media Logs</a>
                    <a href="/xoradmin/public_error_log" class="<?= is_active_nav('xoradmin/public_error_log', $current_path) ? 'active' : '' ?>">Public Error Log</a>
                    <a href="/xoradmin/file_logs" class="<?= is_active_nav('xoradmin/file_logs', $current_path) ? 'active' : '' ?>">File Logs</a>
                    <a href="/xoradmin/debug_feed" class="<?= is_active_nav('xoradmin/debug_feed', $current_path) ? 'active' : '' ?>">Debug Feed</a>
                    <a href="/xoradmin/database" class="<?= is_active_nav('xoradmin/database', $current_path) ? 'active' : '' ?>">Database</a>
                    <a href="/xoradmin/api_test" class="<?= is_active_nav('xoradmin/api_test', $current_path) ? 'active' : '' ?>">API Tester</a>
                </div>
            </div>
        </nav>
        <div class="sidebar-footer">
            <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
                <div style="font-size: 14px; color: #6c757d; margin-bottom: 10px;">
                    Masuk sebagai:<br>
                    <strong style="color: #333;"><?= htmlspecialchars($_SESSION['user_first_name'] ?? 'Admin') ?></strong>
                </div>
                <a href="/xoradmin/logout" style="display: block; text-align: center; background: #f8d7da; color: #721c24; padding: 8px; border-radius: 4px; text-decoration: none; font-weight: bold;">Logout</a>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('admin-sidebar');
            const toggleBtn = document.getElementById('sidebar-toggle-btn');
            const body = document.body;

            const isMobile = () => window.innerWidth <= 768;

            const applySidebarState = () => {
                const isCollapsed = localStorage.getItem('sidebar_collapsed') === 'true';
                if (isMobile()) {
                    body.classList.remove('sidebar-collapsed');
                    sidebar.classList.remove('toggled');
                } else {
                    if (isCollapsed) {
                        body.classList.add('sidebar-collapsed');
                    } else {
                        body.classList.remove('sidebar-collapsed');
                    }
                }
            };

            toggleBtn.addEventListener('click', () => {
                if (isMobile()) {
                    sidebar.classList.toggle('toggled');
                } else {
                    const isCollapsed = body.classList.toggle('sidebar-collapsed');
                    localStorage.setItem('sidebar_collapsed', isCollapsed);
                }
            });

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (isMobile() && sidebar.classList.contains('toggled')) {
                    if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
                        sidebar.classList.remove('toggled');
                    }
                }
            });

            window.addEventListener('resize', applySidebarState);
            applySidebarState(); // Initial state

            // Collapsible sidebar-heading logic
            const collapsibleToggles = document.querySelectorAll('.collapsible-toggle');

            collapsibleToggles.forEach(toggle => {
                const targetId = toggle.dataset.target;
                const targetContent = document.getElementById(targetId);
                if (!targetContent) return;

                // Get state from localStorage, default to 'true' (collapsed) if not found
                const storedState = localStorage.getItem(`sidebar_collapse_${targetId}`);
                const isCollapsed = storedState === null ? true : storedState === 'true'; // Default to true (collapsed)

                if (isCollapsed) {
                    targetContent.classList.add('collapsed');
                    toggle.classList.add('collapsed');
                } else {
                    // Ensure it's expanded if not collapsed (e.g., if it was explicitly expanded before)
                    targetContent.classList.remove('collapsed');
                    toggle.classList.remove('collapsed');
                }

                toggle.addEventListener('click', () => {
                    const isCurrentlyCollapsed = targetContent.classList.toggle('collapsed');
                    toggle.classList.toggle('collapsed');
                    localStorage.setItem(`sidebar_collapse_${targetId}`, isCurrentlyCollapsed);
                });
            });
        });
    </script>
</body>
</html>
