<?php
// This view assumes the following variables are available:
// 'page_title', 'configs', 'flash_message'
?>

<h1><?= htmlspecialchars($data['page_title']) ?></h1>

<?php if ($data['flash_message']): ?>
    <div class="alert alert-success"><?= htmlspecialchars($data['flash_message']) ?></div>
<?php endif; ?>

<div class="actions">
    <a href="/admin/feature-channels/create" class="btn btn-primary">Tambah Konfigurasi Baru</a>
</div>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Nama Konfigurasi</th>
            <th>Jenis Fitur</th>
            <th>Bot Pengelola</th>
            <th>Pemilik</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($data['configs'])): ?>
            <tr>
                <td colspan="6" style="text-align: center;">Belum ada konfigurasi channel.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($data['configs'] as $config): ?>
                <tr>
                    <td><?= htmlspecialchars($config['id']) ?></td>
                    <td><?= htmlspecialchars($config['name']) ?></td>
                    <td><?= htmlspecialchars(ucfirst($config['feature_type'])) ?></td>
                    <td><?= htmlspecialchars($config['bot_name']) ?> (ID: <?= htmlspecialchars($config['managing_bot_id']) ?>)</td>
                    <td><?= htmlspecialchars($config['owner_username'] ?? 'Admin') ?></td>
                    <td>
                        <a href="/admin/feature-channels/edit?id=<?= $config['id'] ?>" class="btn btn-edit">Edit</a>
                        <form action="/admin/feature-channels/destroy" method="POST" style="display:inline;" onsubmit="return confirm('Anda yakin ingin menghapus konfigurasi ini?');">
                            <input type="hidden" name="id" value="<?= $config['id'] ?>">
                            <button type="submit" class="btn btn-danger">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
