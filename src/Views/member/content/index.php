<?php
// This view assumes 'my_packages' is available in the $data array.
?>

<h2>Konten Saya</h2>
<p>Di bawah ini adalah daftar semua konten yang telah Anda buat. Anda dapat mengelola setiap item dari sini.</p>

<div class="table-responsive" style="margin-top: 20px;">
    <table class="list-table">
        <thead>
            <tr>
                <th>ID Konten</th>
                <th>Deskripsi</th>
                <th>Harga</th>
                <th>Status</th>
                <th>Dibuat pada</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($data['my_packages'])): ?>
                <tr>
                    <td colspan="6" style="text-align: center;">Anda belum membuat konten apa pun.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($data['my_packages'] as $package): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($package['public_id']) ?></strong></td>
                        <td><?= htmlspecialchars($package['description']) ?></td>
                        <td>Rp <?= number_format($package['price'] ?? 0, 0, ',', '.') ?></td>
                        <td><span class="status-badge status-<?= htmlspecialchars($package['status']) ?>"><?= ucfirst(htmlspecialchars($package['status'])) ?></span></td>
                        <td><?= htmlspecialchars(date('d M Y H:i', strtotime($package['created_at']))) ?></td>
                        <td style="white-space: nowrap;">
                            <a href="/member/content/show?id=<?= htmlspecialchars($package['public_id']) ?>" class="btn btn-sm">Lihat</a>
                            <a href="/member/content/edit?id=<?= htmlspecialchars($package['public_id']) ?>" class="btn btn-edit btn-sm">Edit</a>
                            <a href="#" class="btn btn-delete btn-sm">Hapus</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

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
