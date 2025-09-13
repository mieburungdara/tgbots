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
        :root {
            --sidebar-bg: #111827; /* Gray 900 */
            --sidebar-text: #9ca3af; /* Gray 400 */
            --sidebar-hover-bg: #1f2937; /* Gray 800 */
            --sidebar-hover-text: #ffffff;
            --sidebar-active-bg: #007bff;
            --sidebar-active-text: #ffffff;
            --sidebar-heading-text: #6b7280; /* Gray 500 */
            --main-bg: #f4f6f8;
            --text-main: #1f2937;
            --card-bg: #ffffff;
            --border-color: #e5e7eb;
        }

        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; background-color: var(--main-bg); color: var(--text-main); font-size: 16px; }
        .header { background-color: var(--card-bg); box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 0 20px; }
        .container { max-width: 1600px; margin: 20px auto; padding: 20px; }
        .content { background-color: var(--card-bg); padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; word-wrap: break-word; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        h1, h2, h3 { color: var(--text-main); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid var(--border-color); text-align: left; vertical-align: top; }
        th { background-color: #f9fafb; }
        form { margin-top: 20px; padding: 20px; border: 1px solid var(--border-color); border-radius: 5px; background-color: #f9fafb; }
        input, textarea, select { width: calc(100% - 22px); padding: 10px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; }
        button, .btn { padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        button:hover, .btn:hover { background-color: #0056b3; }
        .btn-delete { background-color: #dc3545; }
        .btn-delete:hover { background-color: #c82333; }
        
        body.admin-body { display: flex; }
        .admin-main-content { flex-grow: 1; transition: margin-left 0.3s ease; }

        /* --- New Sidebar Styles --- */
        .sidebar {
            width: 260px;
            flex-shrink: 0;
            background-color: var(--sidebar-bg);
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: width 0.3s ease;
        }
        .sidebar-header {
            padding: 24px 20px;
            font-size: 1.5em;
            font-weight: bold;
            text-align: center;
            border-bottom: 1px solid var(--sidebar-hover-bg);
        }
        .sidebar-header a { text-decoration: none; color: var(--sidebar-active-text); }
        
        .sidebar-nav { flex-grow: 1; display: flex; flex-direction: column; padding: 15px; gap: 4px; }
        .sidebar-heading {
            font-size: 0.75rem; /* 12px */
            color: var(--sidebar-heading-text);
            margin-top: 16px;
            margin-bottom: 8px;
            padding: 0 15px;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.05em;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: color 0.2s ease;
        }
        .sidebar-heading:hover { color: var(--sidebar-hover-text); }
        .sidebar-heading::after { content: '▼'; font-size: 0.7em; transition: transform 0.2s ease; }
        .sidebar-heading.collapsed::after { transform: rotate(-90deg); }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--sidebar-text);
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 0.9rem; /* 14.4px */
            font-weight: 500;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        .sidebar-nav a:hover {
            background-color: var(--sidebar-hover-bg);
            color: var(--sidebar-hover-text);
        }
        .sidebar-nav a.active {
            background-color: var(--sidebar-active-bg);
            color: var(--sidebar-active-text);
            font-weight: 600;
        }
        .sidebar-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            stroke-width: 2;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid var(--sidebar-hover-bg);
            margin-top: 15px;
        }
        .sidebar-footer div { font-size: 14px; color: var(--sidebar-text); margin-bottom: 10px; }
        .sidebar-footer strong { color: var(--sidebar-hover-text); }
        .sidebar-footer a { display: block; text-align: center; background: var(--sidebar-hover-bg); color: #ef4444; padding: 10px; border-radius: 6px; text-decoration: none; font-weight: bold; }
        .sidebar-footer a:hover { background: #ef4444; color: white; }

        .collapsible-content { overflow: hidden; transition: max-height 0.3s ease-out; max-height: 500px; }
        .collapsible-content.collapsed { max-height: 0; }

        .sidebar-toggle { position: fixed; top: 15px; left: 15px; z-index: 1001; background: #007bff; color: white; border: none; border-radius: 50%; width: 40px; height: 40px; font-size: 20px; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2); display: none; align-items: center; justify-content: center; }
        .sidebar-toggle:hover { background: #0056b3; }

        @media (max-width: 768px) {
            .sidebar { position: fixed; z-index: 1000; left: -260px; transition: left 0.3s ease; height: 100%; overflow-y: auto; }
            .sidebar.toggled { left: 0; }
            .admin-main-content { margin-left: 0; }
            .sidebar-toggle { display: flex; }
            body.admin-body { display: block; }
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
            <a href="/xoradmin/dashboard" class="<?= is_active_nav('xoradmin/dashboard', $current_path) ? 'active' : '' ?>">
                <svg class="sidebar-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                <span>Dashboard</span>
            </a>

            <p class="sidebar-heading collapsible-toggle" data-target="content-sales-collapse">Content & Sales</p>
            <div id="content-sales-collapse" class="collapsible-content">
                <a href="/xoradmin/analytics" class="<?= is_active_nav('xoradmin/analytics', $current_path) ? 'active' : '' ?>">
                    <svg class="sidebar-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                    <span>Analytics</span>
                </a>
                <a href="/xoradmin/packages" class="<?= is_active_nav('xoradmin/packages', $current_path) ? 'active' : '' ?>">
                    <svg class="sidebar-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>
                    <span>Content</span>
                </a>
            </div>

            <p class="sidebar-heading collapsible-toggle" data-target="users-roles-collapse">Users & Roles</p>
            <div id="users-roles-collapse" class="collapsible-content">
                <a href="/xoradmin/users" class="<?= is_active_nav('xoradmin/users', $current_path) ? 'active' : '' ?>">
                    <svg class="sidebar-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M15 21a6 6 0 00-9-5.197M15 21a6 6 0 00-9-5.197" /></svg>
                    <span>Users</span>
                </a>
                <a href="/xoradmin/balance" class="<?= is_active_nav('xoradmin/balance', $current_path) ? 'active' : '' ?>">
                    <svg class="sidebar-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" /></svg>
                    <span>Balance</span>
                </a>
                <a href="/xoradmin/roles" class="<?= is_active_nav('xoradmin/roles', $current_path) ? 'active' : '' ?>">
                    <svg class="sidebar-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" /></svg>
                    <span>Roles</span>
                </a>
            </div>

            <p class="sidebar-heading collapsible-toggle" data-target="bot-channels-collapse">Bot & Channels</p>
            <div id="bot-channels-collapse" class="collapsible-content">
                <a href="/xoradmin/bots" class="<?= is_active_nav('xoradmin/bots', $current_path) ? 'active' : '' ?>">
                    <svg class="sidebar-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    <span>Bot Management</span>
                </a>
                <a href="/xoradmin/storage_channels" class="<?= is_active_nav('xoradmin/storage_channels', $current_path) ? 'active' : '' ?>">
                    <svg class="sidebar-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
                    <span>Storage Channels</span>
                </a>
                <a href="/xoradmin/feature-channels" class="<?= is_active_nav('xoradmin/feature-channels', $current_path) ? 'active' : '' ?>">
                    <svg class="sidebar-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg>
                    <span>Feature Channels</span>
                </a>
            </div>

            <p class="sidebar-heading collapsible-toggle" data-target="system-debug-collapse">System & Debug</p>
            <div id="system-debug-collapse" class="collapsible-content">
                <a href="/xoradmin/health_check" class="<?= is_active_nav('xoradmin/health_check', $current_path) ? 'active' : '' ?>">
                    <svg class="sidebar-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg>
                    <span>Health Check</span>
                </a>
                <a href="/xoradmin/logs" class="<?= is_active_nav('xoradmin/logs', $current_path) ? 'active' : '' ?>">
                    <svg class="sidebar-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    <span>App Logs</span>
                </a>
                <a href="/xoradmin/telegram_logs" class="<?= is_active_nav('xoradmin/telegram_logs', $current_path) ? 'active' : '' ?>">
                    <svg class="sidebar-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                    <span>Telegram Logs</span>
                </a>
                <a href="/xoradmin/file_logs" class="<?= is_active_nav('xoradmin/file_logs', $current_path) ? 'active' : '' ?>">
                    <svg class="sidebar-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                    <span>File Logs</span>
                </a>
                <a href="/xoradmin/database" class="<?= is_active_nav('xoradmin/database', $current_path) ? 'active' : '' ?>">
                    <svg class="sidebar-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" /></svg>
                    <span>Database</span>
                </a>
                <a href="/xoradmin/api_test" class="<?= is_active_nav('xoradmin/api_test', $current_path) ? 'active' : '' ?>">
                    <svg class="sidebar-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v4.512l-4 2.012-4-2.012V5l-1-1z" /></svg>
                    <span>API Tester</span>
                </a>
            </div>
        </nav>
        <div class="sidebar-footer">
            <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
                <div>
                    Masuk sebagai:<br>
                    <strong><?= htmlspecialchars($_SESSION['user_first_name'] ?? 'Admin') ?></strong>
                </div>
                <a href="/xoradmin/logout">Logout</a>
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

            document.addEventListener('click', function(event) {
                if (isMobile() && sidebar.classList.contains('toggled')) {
                    if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
                        sidebar.classList.remove('toggled');
                    }
                }
            });

            window.addEventListener('resize', applySidebarState);
            applySidebarState();

            const collapsibleToggles = document.querySelectorAll('.sidebar-heading');

            collapsibleToggles.forEach(toggle => {
                const targetId = toggle.dataset.target;
                if (!targetId) return;
                const targetContent = document.getElementById(targetId);
                if (!targetContent) return;

                const storedState = localStorage.getItem(`sidebar_collapse_${targetId}`);
                const isCollapsed = storedState === 'true';

                if (isCollapsed) {
                    targetContent.classList.add('collapsed');
                    toggle.classList.add('collapsed');
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
