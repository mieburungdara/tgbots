<?php
// This view assumes $data['results'] is an array of check results.
?>

<style>
    .status-badge {
        display: inline-block;
        padding: 0.3em 0.6em;
        font-size: 0.8rem;
        font-weight: 700;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: 0.25rem;
        color: #fff;
    }
    .status-SUCCESS { background-color: #28a745; } /* Green */
    .status-ERROR   { background-color: #dc3545; } /* Red */
    .status-WARN    { background-color: #ffc107; color: #212529; } /* Yellow */

    .btn-refresh {
        display: inline-block;
        margin-bottom: 1rem;
        padding: 0.75rem 1.5rem;
        background-color: #007bff;
        color: white;
        font-weight: 600;
        border-radius: 0.375rem;
        text-decoration: none;
        transition: background-color 0.2s ease-in-out;
    }
    .btn-refresh:hover {
        background-color: #0056b3;
    }
    .health-table {
        width: 100%;
        border-collapse: collapse;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border-radius: 8px;
        overflow: hidden; /* Ensures border-radius is applied to corners */
    }
    .health-table th, .health-table td {
        padding: 1rem;
        text-align: left;
        border-bottom: 1px solid #dee2e6;
    }
    .health-table th {
        background-color: #f8f9fa;
        font-weight: 600;
    }
    .health-table tr:last-child td {
        border-bottom: none;
    }
    .health-table td:first-child { width: 25%; }
    .health-table td:nth-child(2) { width: 15%; }
</style>

<div class="container mx-auto p-4">
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold"><?= htmlspecialchars($data['page_title']) ?></h1>
        <a href="/xoradmin/health_check" class="btn-refresh">Jalankan Ulang Pemeriksaan</a>
    </div>

    <p class="mb-4 text-gray-600">Hasil pemeriksaan otomatis dari skrip <code>doctor.php</code>. Gunakan ini untuk mendiagnosis masalah konfigurasi atau lingkungan.</p>

    <div class="table-responsive">
        <table class="health-table">
            <thead>
                <tr>
                    <th>Pemeriksaan</th>
                    <th>Status</th>
                    <th>Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['results'])): ?>
                    <tr>
                        <td colspan="3" style="text-align: center; padding: 2rem;">Tidak ada hasil pemeriksaan yang tersedia.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($data['results'] as $result): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($result['description']) ?></strong></td>
                            <td>
                                <span class="status-badge status-<?= htmlspecialchars($result['status']) ?>">
                                    <?= htmlspecialchars($result['status']) ?>
                                </span>
                            </td>
                            <td><?= nl2br(htmlspecialchars($result['message'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>