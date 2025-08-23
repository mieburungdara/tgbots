<?php
// Dapatkan nama file skrip saat ini untuk menyorot tautan aktif
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <a href="index.php">Admin Panel</a>
    </div>
    <nav class="sidebar-nav">
        <a href="index.php" class="<?= $current_page == 'index.php' ? 'active' : '' ?>">Percakapan</a>
        <a href="bots.php" class="<?= $current_page == 'bots.php' || $current_page == 'edit_bot.php' ? 'active' : '' ?>">Kelola Bot</a>
        <a href="users.php" class="<?= $current_page == 'users.php' ? 'active' : '' ?>">Pengguna</a>
        <a href="balance.php" class="<?= $current_page == 'balance.php' ? 'active' : '' ?>">Manajemen Saldo</a>
        <a href="roles.php" class="<?= $current_page == 'roles.php' ? 'active' : '' ?>">Manajemen Peran</a>
        <a href="packages.php" class="<?= $current_page == 'packages.php' ? 'active' : '' ?>">Konten</a>
        <a href="media_logs.php" class="<?= $current_page == 'media_logs.php' ? 'active' : '' ?>">Log Media</a>
        <a href="channels.php" class="<?= $current_page == 'channels.php' ? 'active' : '' ?>">Channel</a>
        <a href="database.php" class="<?= $current_page == 'database.php' ? 'active' : '' ?>">Database</a>
        <a href="logs.php" class="<?= $current_page == 'logs.php' ? 'active' : '' ?>">Logs</a>
        <a href="telegram_logs.php" class="<?= $current_page == 'telegram_logs.php' ? 'active' : '' ?>">Log Error Telegram</a>
        <a href="debug_feed.php" class="<?= $current_page == 'debug_feed.php' ? 'active' : '' ?>">Debug Feed</a>
        <a href="api_test.php" class="<?= $current_page == 'api_test.php' ? 'active' : '' ?>">Tes API</a>
        <a href="../index.php">Logout</a>
    </nav>
</aside>
