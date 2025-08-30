<?php
// This view assumes 'summary', 'chart_labels', 'chart_data', and 'top_packages' are available in the $data array.
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="dashboard-grid">
    <div class="dashboard-card">
        <h3>Total Pendapatan</h3>
        <p class="stat-number">Rp <?= number_format($data['summary']['total_revenue'], 0, ',', '.') ?></p>
    </div>
    <div class="dashboard-card">
        <h3>Total Penjualan</h3>
        <p class="stat-number"><?= number_format($data['summary']['total_sales']) ?> item</p>
    </div>
</div>

<div class="chart-container dashboard-card" style="margin-top: 20px;">
    <h2>Pendapatan 30 Hari Terakhir</h2>
    <canvas id="salesChart"></canvas>
</div>

<div class="table-container" style="margin-top: 20px;">
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
            <?php if (empty($data['top_packages'])): ?>
                <tr><td colspan="4" style="text-align: center;">Belum ada penjualan.</td></tr>
            <?php else: ?>
                <?php foreach ($data['top_packages'] as $pkg): ?>
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
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($data['chart_labels']) ?>,
                datasets: [{
                    label: 'Pendapatan Harian',
                    data: <?= json_encode($data['chart_data']) ?>,
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
    });
</script>
