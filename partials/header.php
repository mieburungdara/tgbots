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
    </style>
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
