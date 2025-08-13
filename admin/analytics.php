<?php
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/database/AnalyticsRepository.php';

$pdo = get_db_connection();
$analyticsRepo = new AnalyticsRepository($pdo);

// Ambil data analitik
$summary = $analyticsRepo->getGlobalSummary();
$sales_by_day = $analyticsRepo->getSalesByDay(null, 30); // 30 hari terakhir
$top_packages = $analyticsRepo->getTopSellingPackages(5);

// Siapkan data untuk chart
$chart_labels = [];
$chart_data = [];
foreach ($sales_by_day as $day) {
    $chart_labels[] = date("d M", strtotime($day['sales_date']));
    $chart_data[] = $day['daily_revenue'];
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analitik Penjualan - Admin Panel</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background-color: #f0f2f5; margin: 0; padding: 2rem; color: #1c1e21; }
        .container { max-width: 1200px; margin: auto; }
        nav { background: white; padding: 1rem 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        nav a { text-decoration: none; color: #007bff; margin-right: 1rem; font-weight: bold; }
        nav a.active { color: #1c1e21; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-card h3 { margin: 0 0 0.5rem 0; color: #606770; font-size: 1rem; }
        .stat-card p { margin: 0; font-size: 2rem; font-weight: bold; }
        .chart-container, .table-container { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        h2 { border-bottom: 1px solid #ddd; padding-bottom: 0.5rem; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <nav>
            <a href="index.php">Percakapan</a> |
            <a href="bots.php">Kelola Bot</a> |
            <a href="users.php">Pengguna</a> |
            <a href="roles.php">Manajemen Peran</a> |
            <a href="packages.php">Konten</a> |
            <a href="media_logs.php">Log Media</a> |
            <a href="channels.php">Channel</a> |
            <a href="database.php">Database</a> |
            <a href="analytics.php" class="active">Analitik</a> |
            <a href="logs.php">Logs</a>
        </nav>

        <div class="stat-grid">
            <div class="stat-card">
                <h3>Total Pendapatan</h3>
                <p>Rp <?= number_format($summary['total_revenue'], 0, ',', '.') ?></p>
            </div>
            <div class="stat-card">
                <h3>Total Penjualan</h3>
                <p><?= number_format($summary['total_sales']) ?> item</p>
            </div>
        </div>

        <div class="chart-container">
            <h2>Pendapatan 30 Hari Terakhir</h2>
            <canvas id="salesChart"></canvas>
        </div>

        <div class="table-container">
            <h2>Konten Terlaris</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID Paket</th>
                        <th>Deskripsi</th>
                        <th>Jumlah Terjual</th>
                        <th>Total Pendapatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($top_packages)): ?>
                        <tr><td colspan="4" style="text-align: center;">Belum ada penjualan.</td></tr>
                    <?php else: ?>
                        <?php foreach ($top_packages as $pkg): ?>
                            <tr>
                                <td>#<?= htmlspecialchars($pkg['id']) ?></td>
                                <td><?= htmlspecialchars($pkg['description']) ?></td>
                                <td><?= htmlspecialchars($pkg['sales_count']) ?></td>
                                <td>Rp <?= number_format($pkg['total_revenue'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [{
                    label: 'Pendapatan Harian',
                    data: <?= json_encode($chart_data) ?>,
                    backgroundColor: 'rgba(0, 123, 255, 0.2)',
                    borderColor: 'rgba(0, 123, 255, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value, index, values) {
                                return 'Rp ' + value.toLocaleString('id-ID');
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
                                    label += 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
