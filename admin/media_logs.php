<?php
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("Koneksi database gagal.");
}

// TODO: Tambahkan otentikasi admin di sini

// Ambil semua data media, gabungkan dengan info pengguna dan bot
// Ini adalah query yang kompleks, mungkin perlu dioptimalkan di masa depan
$sql = "
    SELECT
        mf.id,
        mf.type,
        mf.file_name,
        mf.caption,
        mf.file_size,
        mf.media_group_id,
        mf.created_at,
        u.first_name as user_first_name,
        u.username as user_username,
        b.name as bot_name,
        b.id as bot_id
    FROM media_files mf
    LEFT JOIN users u ON mf.user_id = u.telegram_id
    LEFT JOIN messages m ON mf.message_id = m.telegram_message_id
    LEFT JOIN bots b ON m.bot_id = b.id
    WHERE b.id IS NOT NULL
    ORDER BY mf.created_at DESC
    LIMIT 100; -- Batasi hasil untuk performa awal
";

$media_logs_flat = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Kelompokkan media berdasarkan media_group_id
$grouped_logs = [];
foreach ($media_logs_flat as $log) {
    $group_key = $log['media_group_id'] ?? 'single_' . $log['id'];
    if (!isset($grouped_logs[$group_key])) {
        $grouped_logs[$group_key] = [
            'items' => [],
            'group_info' => [
                'user' => $log['user_first_name'] . ($log['user_username'] ? ' (@' . $log['user_username'] . ')' : ''),
                'bot' => $log['bot_name'],
                'bot_id' => $log['bot_id'],
                'time' => $log['created_at'],
                'media_group_id' => $log['media_group_id']
            ]
        ];
    }
    $grouped_logs[$group_key]['items'][] = $log;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Media - Admin Panel</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 40px; background-color: #f4f6f8; color: #333; }
        .container { max-width: 1200px; margin: 0 auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        nav { margin-bottom: 20px; }
        nav a { text-decoration: none; color: #007bff; padding: 10px; }
        nav a.active { font-weight: bold; }
        .log-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .log-table th, .log-table td { padding: 12px 15px; text-align: left; border: 1px solid #ddd; }
        .log-table th { background-color: #f4f4f4; }
        .group-header td { background-color: #f9f9f9; font-weight: bold; }
        .media-item { display: flex; align-items: center; gap: 15px; padding: 5px 0; }
        .media-icon { font-size: 1.5em; }
        .btn-forward { padding: 5px 10px; font-size: 0.9em; background-color: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn-forward:hover { background-color: #117a8b; }
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
            <a href="media_logs.php" class="active">Log Media</a> |
            <a href="channels.php">Channel</a> |
            <a href="database.php">Database</a> |
            <a href="logs.php">Logs</a>
        </nav>

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
                <?php if (empty($grouped_logs)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">Belum ada log media.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($grouped_logs as $group): ?>
                        <tr class="group-header">
                            <td><?= htmlspecialchars($group['group_info']['time']) ?></td>
                            <td><?= htmlspecialchars($group['group_info']['user']) ?></td>
                            <td><?= htmlspecialchars($group['group_info']['bot']) ?></td>
                            <td>
                                <?php foreach ($group['items'] as $item): ?>
                                    <div class="media-item">
                                        <span class="media-icon">
                                            <?php
                                                // Simple icon representation
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
    </div>

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
                    const response = await fetch('forward_manager.php', {
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
</body>
</html>
