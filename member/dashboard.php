<?php
session_start();

// Jika belum login, redirect ke halaman login
if (!isset($_SESSION['member_user_id'])) {
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/../core/database.php';

$pdo = get_db_connection();

// Ambil informasi pengguna dari tabel users
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['member_user_id']]);
$user_info = $stmt->fetch();

if (!$user_info) {
    // Jika data pengguna tidak ditemukan, hancurkan session dan redirect
    session_destroy();
    header("Location: index.php");
    exit;
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Member Area</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background-color: #f0f2f5; margin: 0; padding: 2rem; color: #1c1e21; }
        .container { max-width: 960px; margin: auto; }
        .header { background: white; padding: 1rem 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; }
        h1 { font-size: 1.5rem; margin: 0; }
        nav a { text-decoration: none; color: #007bff; margin-left: 1rem; font-weight: bold; }
        nav a.active { color: #1c1e21; }
        .content-box { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .info-grid { display: grid; grid-template-columns: 150px 1fr; gap: 1rem; }
        .info-grid strong { font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Dashboard</h1>
            <nav>
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="sold.php">Dijual</a>
                <a href="purchased.php">Dibeli</a>
                <a href="dashboard.php?action=logout">Logout</a>
            </nav>
        </div>

        <div class="content-box">
            <h2>Selamat Datang, <?= htmlspecialchars($user_info['first_name'] ?? '') ?>!</h2>
            <p>Ini adalah halaman dasbor Anda. Gunakan navigasi di atas untuk melihat konten yang telah Anda jual atau beli.</p>

            <h3>Informasi Akun</h3>
            <div class="info-grid">
                <strong>Nama Depan:</strong>
                <span><?= htmlspecialchars($user_info['first_name'] ?? '') ?></span>

                <strong>Username:</strong>
                <span>@<?= htmlspecialchars($user_info['username'] ?? 'Tidak ada') ?></span>

                <strong>Telegram ID:</strong>
                <span><?= htmlspecialchars($user_info['telegram_id']) ?></span>

                <strong>Terdaftar pada:</strong>
                <span><?= htmlspecialchars(date('d F Y H:i', strtotime($user_info['created_at']))) ?></span>
            </div>
        </div>
    </div>
</body>
</html>
