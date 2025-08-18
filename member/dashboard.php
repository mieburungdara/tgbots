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

$page_title = 'Dashboard';
require_once __DIR__ . '/../partials/header.php';
?>

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

<?php
require_once __DIR__ . '/../partials/footer.php';
?>
