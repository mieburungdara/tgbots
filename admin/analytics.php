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

$page_title = 'Analitik Penjualan';
require_once __DIR__ . '/../partials/header.php';
?>
<!-- Load Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

<?php
require_once __DIR__ . '/../partials/footer.php';
?>
