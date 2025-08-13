<?php
session_start();

// Jika belum login, redirect ke halaman login
if (!isset($_SESSION['member_user_id'])) {
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/database/AnalyticsRepository.php';

$pdo = get_db_connection();
$analyticsRepo = new AnalyticsRepository($pdo);

$user_id = $_SESSION['member_user_id'];

// Ambil informasi pengguna dari tabel users
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch();

if (!$user_info) {
    // Jika data pengguna tidak ditemukan, hancurkan session dan redirect
    session_destroy();
    header("Location: index.php");
    exit;
}

// Ambil data analitik penjual
$seller_summary = $analyticsRepo->getSellerSummary($user_id);

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
        h1, h2, h3 { color: #1c1e21; }
        h1 { font-size: 1.5rem; margin: 0; }
        h2 { font-size: 1.25rem; }
        h3 { font-size: 1.1rem; border-bottom: 1px solid #eee; padding-bottom: 0.5rem; margin-top: 1.5rem; }
        nav a { text-decoration: none; color: #007bff; margin-left: 1rem; font-weight: bold; }
        nav a.active { color: #1c1e21; }
        .content-box { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem;}
        .info-grid { display: grid; grid-template-columns: 150px 1fr; gap: 1rem; }
        .info-grid strong { font-weight: bold; color: #606770; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1.5rem; }
        .stat-card { background-color: #f8f9fa; padding: 1.5rem; border-radius: 8px; }
        .stat-card h4 { margin: 0 0 0.5rem 0; color: #606770; font-size: 1rem; }
        .stat-card p { margin: 0; font-size: 2rem; font-weight: bold; }
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

            <h3>Analitik Penjualan Anda</h3>
            <div class="stat-grid">
                <div class="stat-card">
                    <h4>Total Pendapatan</h4>
                    <p>Rp <?= number_format($seller_summary['total_revenue'], 0, ',', '.') ?></p>
                </div>
                <div class="stat-card">
                    <h4>Konten Terjual</h4>
                    <p><?= number_format($seller_summary['total_sales']) ?> item</p>
                </div>
            </div>

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
