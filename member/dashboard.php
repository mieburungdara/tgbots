<?php
/**
 * Halaman Dasbor Panel Anggota.
 *
 * Halaman ini adalah halaman utama yang dilihat pengguna setelah berhasil login.
 * Ini menampilkan ringkasan statistik penjualan (jika pengguna adalah penjual)
 * dan informasi dasar akun pengguna, dengan opsi rentang waktu untuk grafik.
 */
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
    session_destroy();
    header("Location: index.php");
    exit;
}

// Tentukan periode waktu untuk analitik
$periods = [
    '1' => 'Hari Ini',
    '7' => '7 Hari',
    '30' => '30 Hari',
    '365' => '1 Tahun',
];
$current_period = isset($_GET['period']) && isset($periods[$_GET['period']]) ? $_GET['period'] : '30';

// Ambil semua data analitik yang diperlukan
$seller_summary = $analyticsRepo->getSellerSummary($user_id);
$purchase_stats = $analyticsRepo->getUserPurchaseStats($user_id);
$sales_by_day = $analyticsRepo->getSalesByDay($user_id, $current_period);
$top_selling_items = $analyticsRepo->getTopSellingPackagesForSeller($user_id, 5);

// Siapkan data untuk grafik
$chart_labels = [];
$chart_data = [];
$label_format = ($current_period > 90) ? 'M Y' : 'd M'; // Format bulanan untuk periode panjang
foreach ($sales_by_day as $day) {
    $chart_labels[] = date($label_format, strtotime($day['sales_date']));
    $chart_data[] = $day['daily_revenue'];
}

$chart_title = 'Tren Pendapatan ' . $periods[$current_period];

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

$page_title = 'Dashboard';
require_once __DIR__ . '/../partials/header.php';
?>

<!-- Style untuk pemilih periode -->
<style>
.period-selector {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}
.period-selector a {
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 5px;
    background-color: #f0f0f0;
    color: #333;
    font-size: 0.9em;
    border: 1px solid #ddd;
}
.period-selector a.active {
    background-color: #007bff;
    color: #fff;
    font-weight: bold;
    border-color: #007bff;
}
</style>

<h2>Selamat Datang, <?= htmlspecialchars($user_info['first_name'] ?? '') ?>!</h2>
<p>Ini adalah ringkasan aktivitas dan statistik Anda di platform kami.</p>

<div class="dashboard-grid">

    <!-- Seller Analytics -->
    <div class="dashboard-card">
        <h3>Analitik Penjual</h3>
        <p class="stat-number">Rp <?= number_format($seller_summary['total_revenue'], 0, ',', '.') ?></p>
        <small>Total Pendapatan</small>
        <p class="stat-number" style="margin-top: 15px;"><?= number_format($seller_summary['total_sales']) ?></p>
        <small>Total Konten Terjual</small>
    </div>

    <!-- Buyer Analytics -->
    <div class="dashboard-card">
        <h3>Analitik Pembeli</h3>
        <p class="stat-number">Rp <?= number_format($purchase_stats['total_spent'], 0, ',', '.') ?></p>
        <small>Total Uang Dibelanjakan</small>
        <p class="stat-number" style="margin-top: 15px;"><?= number_format($purchase_stats['total_purchases']) ?></p>
        <small>Total Konten Dibeli</small>
    </div>

    <!-- Account Information -->
    <div class="dashboard-card">
        <h3>Informasi Akun</h3>
        <table class="list-table">
            <tr>
                <th>Username</th>
                <td>@<?= htmlspecialchars($user_info['username'] ?? 'Tidak ada') ?></td>
            </tr>
            <tr>
                <th>Telegram ID</th>
                <td><?= htmlspecialchars($user_info['id']) ?></td>
            </tr>
            <tr>
                <th>Terdaftar</th>
                <td><?= htmlspecialchars(date('d F Y', strtotime($user_info['created_at']))) ?></td>
            </tr>
        </table>
    </div>

    <!-- Sales Chart -->
    <div class="chart-container dashboard-card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h3><?= htmlspecialchars($chart_title) ?></h3>
            <div class="period-selector">
                <?php foreach ($periods as $days => $label): ?>
                    <a href="?period=<?= $days ?>" class="<?= $current_period == $days ? 'active' : '' ?>"><?= $label ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <canvas id="salesChart"></canvas>
    </div>

    <!-- Top Selling Items -->
    <div class="dashboard-card" style="grid-column: 1 / -1;">
        <h3>Top 5 Konten Terlaris Anda</h3>
        <?php if (empty($top_selling_items)): ?>
            <p>Anda belum memiliki penjualan.</p>
        <?php else: ?>
            <table class="list-table">
                <thead>
                    <tr>
                        <th>ID Konten</th>
                        <th>Deskripsi Konten</th>
                        <th>Jumlah Terjual</th>
                        <th>Total Pendapatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_selling_items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['public_id']) ?></td>
                            <td><?= htmlspecialchars($item['description']) ?></td>
                            <td><?= number_format($item['sales_count']) ?></td>
                            <td>Rp <?= number_format($item['total_revenue'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Include Chart.js from CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('salesChart').getContext('2d');
    const salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                label: 'Pendapatan (Rp)',
                data: <?= json_encode($chart_data) ?>,
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                borderColor: 'rgba(0, 123, 255, 1)',
                borderWidth: 2,
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value, index, values) {
                            return 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += 'Rp ' + new Intl.NumberFormat('id-ID').format(context.parsed.y);
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php
require_once __DIR__ . '/../partials/footer.php';
?>
