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
    </nav>
    <div class="sidebar-footer" style="padding: 15px; border-top: 1px solid #f0f0f0; margin-top: 15px;">
        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true): ?>
            <div style="font-size: 14px; color: #6c757d; margin-bottom: 10px;">
                Masuk sebagai:<br>
                <strong style="color: #333;"><?= htmlspecialchars($_SESSION['user_first_name'] ?? 'Admin') ?></strong>
            </div>
            <a href="logout.php" style="display: block; text-align: center; background: #f8d7da; color: #721c24; padding: 8px; border-radius: 4px; text-decoration: none; font-weight: bold;">Logout</a>
        <?php endif; ?>
    </div>
</aside>
