<?php
// This view assumes 'grouped_logs' is available in the $data array.
?>

<h1>Log Media</h1>
<p>Menampilkan media yang dikirim oleh pengguna. Media yang dikirim bersamaan dikelompokkan.</p>

<table class="log-table">
    <thead>
        <tr>
            <th>Waktu</th>
            <th>Pengguna</th>
            <th>Bot</th>
            <th>Detail Media</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($data['grouped_logs'])): ?>
            <tr>
                <td colspan="5" style="text-align: center;">Belum ada log media.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($data['grouped_logs'] as $group): ?>
                <tr class="group-header">
                    <td><?= htmlspecialchars($group['group_info']['time']) ?></td>
                    <td><?= htmlspecialchars($group['group_info']['user']) ?></td>
                    <td><?= htmlspecialchars($group['group_info']['bot']) ?></td>
                    <td>
                        <?php foreach ($group['items'] as $item): ?>
                            <div class="media-item">
                                <span class="media-icon">
                                    <?php
                                        if ($item['type'] === 'photo') echo '🖼️';
                                        elseif ($item['type'] === 'video') echo '🎬';
                                        elseif ($item['type'] === 'audio') echo '🎵';
                                        elseif ($item['type'] === 'voice') echo '🎤';
                                        elseif ($item['type'] === 'document') echo '📄';
                                        else echo '❔';
                                    ?>
                                </span>
                                <span>
                                    <strong><?= htmlspecialchars(ucfirst($item['type'])) ?></strong>
                                    <?= $item['file_name'] ? '- ' . htmlspecialchars($item['file_name']) : '' ?>
                                    <?= $item['caption'] ? '<br><small><em>' . htmlspecialchars($item['caption']) . '</em></small>' : '' ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <button class="btn-forward"
                                data-group-id="<?= htmlspecialchars($group['group_info']['media_group_id'] ?? 'single_' . $group['items'][0]['id']) ?>"
                                data-bot-id="<?= htmlspecialchars($group['group_info']['bot_id']) ?>">
                            Teruskan ke Admin
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-forward').forEach(button => {
        button.addEventListener('click', async function() {
            const groupId = this.dataset.groupId;
            const botId = this.dataset.botId;

            if (!confirm('Anda yakin ingin meneruskan media ini ke semua admin?')) {
                return;
            }

            const originalText = this.textContent;
            this.textContent = 'Mengirim...';
            this.disabled = true;

            const formData = new FormData();
            formData.append('group_id', groupId);
            formData.append('bot_id', botId);

            try {
                const response = await fetch('/api/admin/media/forward', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                if (!response.ok) {
                    throw new Error(result.message || 'Server error');
                }

                alert('Hasil: ' + result.message);

            } catch (error) {
                alert('Gagal melakukan permintaan: ' + error.message);
            } finally {
                this.textContent = originalText;
                this.disabled = false;
            }
        });
    });
});
</script>

<style>
.log-table { width: 100%; border-collapse: collapse; }
.log-table th, .log-table td { padding: 12px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
.log-table th { background-color: #f2f2f2; }
.group-header { border-top: 2px solid #333; }
.media-item { margin-bottom: 8px; }
.media-icon { margin-right: 8px; }
</style>
