<?php
// This view assumes 'sales_channels' is available in the $data array.
?>

<h1>Manajemen Channel Jualan</h1>
<p>Halaman ini menampilkan semua channel yang telah didaftarkan oleh penjual untuk berjualan.</p>

<div class="table-responsive">
    <table class="chat-log-table">
        <thead>
            <tr>
                <th>Nama Channel</th>
                <th>Nama Grup Diskusi</th>
                <th>Pemilik Channel</th>
                <th>Bot Admin</th>
                <th>Status</th>
                <th>Tanggal Didaftarkan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($data['sales_channels'])): ?>
                <tr>
                    <td colspan="6" style="text-align:center;">Tidak ada channel jualan yang terdaftar.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($data['sales_channels'] as $channel): ?>
                    <tr>
                        <td><?= htmlspecialchars($channel['channel_title']) ?><br><code><?= htmlspecialchars($channel['channel_id']) ?></code></td>
                        <td><?= htmlspecialchars($channel['group_title']) ?><br><code><?= htmlspecialchars($channel['discussion_group_id']) ?></code></td>
                        <td><?= htmlspecialchars($channel['seller_name'] . ' (@' . $channel['seller_username'] . ')') ?></td>
                        <td>@<?= htmlspecialchars($channel['bot_username']) ?></td>
                        <td><?= $channel['is_active'] ? '<span class="status-active">Aktif</span>' : '<span class="status-blocked">Tidak Aktif</span>' ?></td>
                        <td><?= htmlspecialchars($channel['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.status-active {
    color: #28a745;
    font-weight: bold;
}
.status-blocked {
    color: #dc3545;
    font-weight: bold;
}
</style>
