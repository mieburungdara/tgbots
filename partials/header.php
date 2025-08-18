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
    </style>
</head>
<body>

<header class="header">
    <div class="nav-container">
        <?php if ($is_admin_page): ?>
            <h1><a href="index.php" style="text-decoration: none; color: inherit;">Admin Panel</a></h1>
            <nav>
                <a href="index.php" class="<?= $current_page == 'index.php' ? 'active' : '' ?>">Percakapan</a>
                <a href="bots.php" class="<?= $current_page == 'bots.php' || $current_page == 'edit_bot.php' ? 'active' : '' ?>">Kelola Bot</a>
                <a href="users.php" class="<?= $current_page == 'users.php' ? 'active' : '' ?>">Pengguna</a>
                <a href="roles.php" class="<?= $current_page == 'roles.php' ? 'active' : '' ?>">Manajemen Peran</a>
                <a href="packages.php" class="<?= $current_page == 'packages.php' ? 'active' : '' ?>">Konten</a>
                <a href="media_logs.php" class="<?= $current_page == 'media_logs.php' ? 'active' : '' ?>">Log Media</a>
                <a href="channels.php" class="<?= $current_page == 'channels.php' ? 'active' : '' ?>">Channel</a>
                <a href="database.php" class="<?= $current_page == 'database.php' ? 'active' : '' ?>">Database</a>
                <a href="logs.php" class="<?= $current_page == 'logs.php' ? 'active' : '' ?>">Logs</a>
                <a href="telegram_logs.php" class="<?= $current_page == 'telegram_logs.php' ? 'active' : '' ?>">Log Error Telegram</a>
                <a href="api_test.php" class="<?= $current_page == 'api_test.php' ? 'active' : '' ?>">Tes API</a>
                <a href="../index.php">Logout</a>
            </nav>
        <?php else: // Member page ?>
            <h1><a href="index.php" style="text-decoration: none; color: inherit;">Member Area</a></h1>
            <nav>
                <a href="dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
                <a href="package_manager.php" class="<?= $current_page == 'package_manager.php' ? 'active' : '' ?>">My Content</a>
                <a href="purchased.php" class="<?= $current_page == 'purchased.php' ? 'active' : '' ?>">Purchased</a>
                <a href="sold.php" class="<?= $current_page == 'sold.php' ? 'active' : '' ?>">Sold</a>
                <a href="../index.php">Logout</a>
            </nav>
        <?php endif; ?>
    </div>
</header>

<main class="container">
    <div class="content">
        <!-- Konten utama halaman akan dimulai di sini -->
