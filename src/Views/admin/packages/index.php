<?php
// This view assumes $packages and $message are passed from the controller.
?>

<h1>Manajemen Konten</h1>
<p>Halaman ini menampilkan semua paket konten dalam sistem. Admin dapat menghapus konten secara permanen jika diperlukan.</p>

<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<table>
    <thead>
        <tr>
            <th>ID Publik</th>
            <th>Deskripsi</th>
            <th>Penjual</th>
            <th>Harga</th>
            <th>Status</th>
            <th>Dibuat</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($packages)): ?>
            <tr>
                <td colspan="7" style="text-align: center;">Tidak ada konten.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($packages as $package): ?>
                <tr>
                    <td><?= htmlspecialchars($package['public_id'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($package['description'] ?: 'N/A') ?></td>
                    <td>@<?= htmlspecialchars($package['seller_username'] ?? 'N/A') ?></td>
                    <td>Rp <?= number_format($package['price'] ?? 0, 0, ',', '.') ?></td>
                    <td>
                        <span class="status status-<?= strtolower($package['status']) ?>">
                            <?= htmlspecialchars(ucfirst($package['status'])) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($package['created_at']) ?></td>
                    <td>
                        <form action="/admin/packages/delete" method="post" onsubmit="return confirm('PERINGATAN: Aksi ini akan menghapus data dari database dan channel secara permanen. Anda yakin?');">
                            <input type="hidden" name="package_id" value="<?= $package['id'] ?>">
                            <button type="submit" class="btn-delete" <?= $package['status'] === 'sold' ? 'disabled' : '' ?>>
                                Hard Delete
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<style>
.status-available { background-color: #28a745; color: white; padding: 4px 8px; border-radius: 4px; }
.status-pending { background-color: #ffc107; color: black; padding: 4px 8px; border-radius: 4px; }
.status-sold { background-color: #6c757d; color: white; padding: 4px 8px; border-radius: 4px; }
.status-deleted { background-color: #343a40; color: white; padding: 4px 8px; border-radius: 4px; }
</style>
