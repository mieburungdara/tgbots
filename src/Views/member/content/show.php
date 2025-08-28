<?php
// This view assumes all data variables ($package, $package_summary, etc.) are passed from the controller.
?>

<style>
.period-selector { display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; }
.period-selector a { text-decoration: none; padding: 8px 12px; border-radius: 5px; background-color: #f0f0f0; color: #333; font-size: 0.9em; border: 1px solid #ddd; }
.period-selector a.active { background-color: #007bff; color: #fff; font-weight: bold; border-color: #007bff; }
.status-badge { padding: 4px 8px; border-radius: 12px; font-size: 0.8em; font-weight: bold; color: #fff; text-transform: uppercase; }
.status-available { background-color: #28a745; }
.status-pending { background-color: #ffc107; color: #333; }
.status-sold { background-color: #dc3545; }
.status-deleted { background-color: #6c757d; }
</style>

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

<!-- Sales Chart for this package -->
<div class="chart-container dashboard-card" style="margin-top: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h3><?= htmlspecialchars($chart_title) ?></h3>
        <div class="period-selector">
            <?php foreach ($periods as $days => $label): ?>
                <a href="/member/content/show?id=<?= htmlspecialchars($package['public_id']) ?>&period=<?= $days ?>" class="<?= $current_period == $days ? 'active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <canvas id="packageSalesChart"></canvas>
</div>


<div style="margin-top: 20px;">
    <a href="/member/my_content" class="btn">Kembali ke Konten Saya</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('packageSalesChart').getContext('2d');
    const salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                label: 'Pendapatan (Rp)',
                data: <?= json_encode($chart_data) ?>,
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                borderColor: 'rgba(40, 167, 69, 1)',
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
                    ticks: { callback: function(value) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(value); } }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) { label += ': '; }
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
