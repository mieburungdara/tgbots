<?php
// Pastikan sesi dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Dapatkan nama file skrip saat ini untuk menyorot tautan aktif
$current_page = basename($_SERVER['PHP_SELF']);
$is_admin_page = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? ($is_admin_page ? 'Admin Panel' : 'Member Area') ?></title>
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

        /* Dashboard Specific Styles */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .dashboard-card {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #eee;
        }
        .dashboard-card h3 {
            margin-top: 0;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 10px;
            font-size: 1.1em;
            color: #555;
        }
        .dashboard-card .stat-number {
            font-size: 2em;
            font-weight: bold;
            margin: 10px 0 0;
            color: #007bff;
        }
        .chart-container {
            grid-column: 1 / -1; /* Span full width */
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            position: relative;
            height: 400px;
        }
        .list-table {
            width: 100%;
            border-collapse: collapse;
        }
        .list-table th, .list-table td {
            text-align: left;
            padding: 12px 8px;
            border-bottom: 1px solid #f0f0f0;
        }
        .list-table th {
            font-size: 0.9em;
            color: #666;
            font-weight: 600;
        }
        .list-table tr:last-child td {
            border-bottom: none;
        }

        /* Admin Layout */
        body.admin-body { display: flex; }
        .sidebar { width: 240px; flex-shrink: 0; background-color: #fff; box-shadow: 2px 0 5px rgba(0,0,0,0.1); min-height: 100vh; }
        .sidebar-header { padding: 20px; font-size: 1.5em; font-weight: bold; text-align: center; border-bottom: 1px solid #f0f0f0; }
        .sidebar-header a { text-decoration: none; color: inherit; }
        .sidebar-nav { display: flex; flex-direction: column; padding: 15px; }
        .sidebar-nav a { text-decoration: none; color: #333; padding: 12px 15px; border-radius: 5px; margin-bottom: 5px; }
        .sidebar-nav a:hover { background-color: #f0f0f0; }
        .sidebar-nav a.active { font-weight: bold; background-color: #007bff; color: #fff; }
        .admin-main-content { flex-grow: 1; }

        /* Conversation Page Layout */
        .conv-layout {
            display: flex;
            margin: -20px; /* Counteract padding from .content */
            height: calc(100vh - 85px); /* Adjust based on your header/footer height */
        }
        .conv-sidebar {
            width: 280px;
            flex-shrink: 0;
            background-color: #fff;
            border-right: 1px solid #dee2e6;
            overflow-y: auto;
        }
        .conv-sidebar-header {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .conv-sidebar-header h2 {
            margin: 0;
            font-size: 1.2rem;
        }
        .conv-bot-list a {
            display: block;
            padding: 12px 15px;
            text-decoration: none;
            color: #212529;
            border-bottom: 1px solid #e9ecef;
        }
        .conv-bot-list a:hover {
            background-color: #f8f9fa;
        }
        .conv-bot-list a.active {
            background-color: #007bff;
            color: #fff;
            font-weight: 600;
        }
        .conv-main {
            flex-grow: 1;
            padding: 20px;
            overflow-y: auto;
            background-color: #fff;
        }

        /* Conversation Card List */
        .conv-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        .conv-card {
            display: flex;
            align-items: flex-start;
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .conv-card:hover {
            background-color: #f8f9fa;
        }
        .conv-card a {
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: flex-start;
            width: 100%;
        }
        .conv-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background-color: #007bff;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
            flex-shrink: 0;
            margin-right: 15px;
        }
        .conv-details {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .conv-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            width: 100%;
        }
        .conv-name {
            font-weight: 600;
            font-size: 1rem;
        }
        .conv-time {
            font-size: 0.75rem;
            color: #6c757d;
        }
        .conv-message {
            font-size: 0.9rem;
            color: #495057;
            margin-top: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 95%;
        }

        /* Chat Log Table */
        .chat-log-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .chat-log-table th, .chat-log-table td {
            border: 1px solid #dee2e6;
            padding: 8px 12px;
            text-align: left;
            vertical-align: top;
        }
        .chat-log-table th {
            background-color: #f8f9fa;
        }
        .chat-log-table .col-id { width: 5%; }
        .chat-log-table .col-time { width: 15%; }
        .chat-log-table .col-direction { width: 10%; }
        .chat-log-table .col-type { width: 10%; }
        .chat-log-table .col-content { width: 50%; word-break: break-word; }
        .chat-log-table .direction-incoming { color: #007bff; }
        .chat-log-table .direction-outgoing { color: #28a745; }
        .chat-log-table .json-toggle {
             font-size: 0.8em;
             cursor: pointer;
             color: #007bff;
        }
        .chat-log-table .raw-json {
            display: none;
            margin-top: 10px;
            padding: 10px;
            background-color: #2d2d2d;
            color: #f1f1f1;
            border-radius: 5px;
            white-space: pre-wrap;
            max-height: 200px;
            overflow-y: auto;
            font-size: 0.85em;
        }

        /* Pagination Controls */
        .pagination {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
        }
        .pagination a, .pagination span {
            text-decoration: none;
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            color: #007bff;
            border-radius: 4px;
        }
        .pagination a:hover {
            background-color: #e9ecef;
        }
        .pagination .current-page {
            background-color: #007bff;
            color: #fff;
            border-color: #007bff;
            font-weight: bold;
        }
        .pagination .disabled {
            color: #6c757d;
            pointer-events: none;
            background-color: #e9ecef;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1050;
        }
        .modal-content {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 500px;
            position: relative;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .modal-header h2 {
            margin: 0;
        }
        .modal-close {
            border: none;
            background: none;
            font-size: 1.5rem;
            cursor: pointer;
        }
    </style>
    <!-- Prism.js for Syntax Highlighting -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-okaidia.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
</head>
<body class="<?= $is_admin_page ? 'admin-body' : '' ?>">

<?php if ($is_admin_page): ?>
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <div class="admin-main-content">
        <main class="container">
            <div class="content">
                <!-- Konten utama halaman akan dimulai di sini -->
<?php else: // Member page ?>
    <header class="header">
        <div class="nav-container">
            <h1><a href="index.php" style="text-decoration: none; color: inherit;">Member Area</a></h1>
            <nav>
                <a href="dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
                <a href="package_manager.php" class="<?= $current_page == 'package_manager.php' ? 'active' : '' ?>">My Content</a>
                <a href="purchased.php" class="<?= $current_page == 'purchased.php' ? 'active' : '' ?>">Purchased</a>
                <a href="sold.php" class="<?= $current_page == 'sold.php' ? 'active' : '' ?>">Sold</a>
                <a href="../index.php">Logout</a>
            </nav>
        </div>
    </header>
    <main class="container">
        <div class="content">
            <!-- Konten utama halaman akan dimulai di sini -->
<?php endif; ?>
