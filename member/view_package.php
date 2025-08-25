<?php
/**
 * Halaman Detail dan Analitik Paket Konten.
 *
 * Halaman ini menampilkan informasi mendetail dan statistik penjualan
 * untuk satu paket konten spesifik milik pengguna.
 */
session_start();

// Jika belum login, redirect ke halaman login
if (!isset($_SESSION['member_user_id'])) {
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/database/PackageRepository.php';
require_once __DIR__ . '/../core/database/AnalyticsRepository.php';

$pdo = get_db_connection();
$packageRepo = new PackageRepository($pdo);
$analyticsRepo = new AnalyticsRepository($pdo);
$user_id = $_SESSION['member_user_id'];

// Ambil ID publik paket dari URL
$public_id = $_GET['id'] ?? null;
if (!$public_id) {
    header("Location: my_content.php");
    exit;
}

// Ambil data paket dan verifikasi kepemilikan
try {
    $package = $packageRepo->findByPublicId($public_id);
    if (!$package || $package['seller_user_id'] != $user_id) {
        $_SESSION['flash_message'] = "Error: Paket tidak ditemukan atau Anda tidak memiliki izin.";
        header("Location: my_content.php");
        exit;
    }
} catch (Exception $e) {
    $_SESSION['flash_message'] = "Error: " . $e->getMessage();
    header("Location: my_content.php");
    exit;
}

// Ambil data analitik untuk paket ini
$package_id = $package['id'];
$package_summary = $analyticsRepo->getSummaryForPackage($package_id);
$recent_sales = $analyticsRepo->getRecentSalesForPackage($package_id, 10);

$page_title = 'Detail Konten: ' . htmlspecialchars($package['public_id']);
require_once __DIR__ . '/../partials/header.php';
?>

<h2>Detail Konten: <?= htmlspecialchars($package['public_id']) ?></h2>
<p><?= htmlspecialchars($package['description']) ?></p>

<div class="dashboard-grid" style="margin-top: 20px;">
    <!-- Analytics Summary -->
    <div class="dashboard-card">
        <h3>Total Pendapatan</h3>
        <p class="stat-number">Rp <?= number_format($package_summary['total_revenue'], 0, ',', '.') ?></p>
    </div>
    <div class="dashboard-card">
        <h3>Total Terjual</h3>
        <p class="stat-number"><?= number_format($package_summary['total_sales']) ?></p>
    </div>

    <!-- Package Info -->
    <div class="dashboard-card">
        <h3>Informasi</h3>
        <table class="list-table">
            <tr><th>Harga</th><td>Rp <?= number_format($package['price'] ?? 0, 0, ',', '.') ?></td></tr>
            <tr><th>Status</th><td><span class="status-badge status-<?= htmlspecialchars($package['status']) ?>"><?= ucfirst(htmlspecialchars($package['status'])) ?></span></td></tr>
            <tr><th>Dibuat</th><td><?= htmlspecialchars(date('d M Y H:i', strtotime($package['created_at']))) ?></td></tr>
        </table>
    </div>
</div>

<div class="dashboard-card" style="margin-top: 20px;">
    <h3>10 Penjualan Terakhir</h3>
    <?php if (empty($recent_sales)): ?>
        <p>Belum ada riwayat penjualan untuk konten ini.</p>
    <?php else: ?>
        <table class="list-table">
            <thead>
                <tr>
                    <th>Tanggal Pembelian</th>
                    <th>Harga</th>
                    <th>Dibeli oleh</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_sales as $sale): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('d M Y H:i', strtotime($sale['purchased_at']))) ?></td>
                        <td>Rp <?= number_format($sale['price'], 0, ',', '.') ?></td>
                        <td>@<?= htmlspecialchars($sale['buyer_username']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div style="margin-top: 20px;">
    <a href="my_content.php" class="btn">Kembali ke Konten Saya</a>
</div>

<!-- CSS untuk status badge, jika belum ada di header -->
<style>
.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: bold;
    color: #fff;
    text-transform: uppercase;
}
.status-available { background-color: #28a745; }
.status-pending { background-color: #ffc107; color: #333; }
.status-sold { background-color: #dc3545; }
.status-deleted { background-color: #6c757d; }
</style>

<?php
require_once __DIR__ . '/../partials/footer.php';
?>
