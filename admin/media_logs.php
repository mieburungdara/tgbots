<?php
/**
 * Halaman Log Media (Admin).
 *
 * Halaman ini menampilkan log dari semua file media yang dikirim oleh pengguna ke bot.
 * Media yang dikirim bersamaan sebagai album (dengan `media_group_id` yang sama)
 * akan dikelompokkan menjadi satu entri dalam tabel.
 *
 * Fitur:
 * - Mengambil data media dari tabel `media_files`.
 * - Menggabungkan (JOIN) dengan data pengguna dan bot untuk informasi kontekstual.
 * - Mengelompokkan item media berdasarkan `media_group_id`.
 * - Menyediakan tombol untuk meneruskan media/grup media ke semua admin.
 */
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("Koneksi database gagal.");
}

// TODO: Tambahkan otentikasi admin di sini

// Ambil semua data media, gabungkan dengan info pengguna dan bot.
// Query ini menggabungkan beberapa tabel untuk mendapatkan konteks lengkap untuk setiap file media.
// Catatan: Query ini bisa menjadi lambat pada tabel yang sangat besar dan mungkin memerlukan optimasi.
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
    LEFT JOIN users u ON mf.user_id = u.id
    LEFT JOIN messages m ON mf.message_id = m.telegram_message_id
    LEFT JOIN bots b ON m.bot_id = b.id
    WHERE b.id IS NOT NULL
    ORDER BY mf.created_at DESC
    LIMIT 100; -- Batasi hasil untuk performa awal
";

$media_logs_flat = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Kelompokkan hasil query flat menjadi sebuah struktur data bersarang.
// Setiap entri di `$grouped_logs` akan mewakili satu media tunggal atau satu album media.
$grouped_logs = [];
foreach ($media_logs_flat as $log) {
    // Gunakan media_group_id sebagai kunci pengelompokan.
    // Jika tidak ada, buat kunci unik untuk media tunggal agar tidak bertabrakan.
    $group_key = $log['media_group_id'] ?? 'single_' . $log['id'];

    // Jika ini adalah item pertama dari grupnya, inisialisasi entri grup.
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
    // Tambahkan item media saat ini ke dalam grupnya.
    $grouped_logs[$group_key]['items'][] = $log;
}

$page_title = 'Log Media';
require_once __DIR__ . '/../partials/header.php';
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
<?php
require_once __DIR__ . '/../partials/footer.php';
?>
