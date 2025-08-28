<?php
/**
 * Halaman Manajemen Channel Penyimpanan (Admin).
 */
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/database/PrivateChannelRepository.php';
require_once __DIR__ . '/../core/database/BotRepository.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("Koneksi database gagal.");
}

$channelRepo = new PrivateChannelRepository($pdo);
$botRepo = new BotRepository($pdo);
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_channel') {
        $channel_id = filter_input(INPUT_POST, 'channel_id', FILTER_VALIDATE_INT);
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        if ($channel_id && $name) {
            if ($channelRepo->addChannel($channel_id, $name)) {
                $_SESSION['message'] = "Channel '{$name}' berhasil ditambahkan.";
            } else {
                $_SESSION['message'] = "Gagal menambahkan channel. Mungkin ID sudah ada atau terjadi error lain.";
            }
        } else {
            $_SESSION['message'] = "Data channel tidak valid.";
        }
        header("Location: channels.php");
        exit;
    }

    if ($action === 'delete_channel') {
        $telegram_channel_id = filter_input(INPUT_POST, 'channel_id', FILTER_VALIDATE_INT);
        if ($telegram_channel_id) {
            if ($channelRepo->deleteByTelegramId($telegram_channel_id)) {
                $_SESSION['message'] = "Channel berhasil dihapus.";
            } else {
                $_SESSION['message'] = "Gagal menghapus channel.";
            }
        } else {
            $_SESSION['message'] = "ID Channel tidak valid untuk penghapusan.";
        }
        header("Location: channels.php");
        exit;
    }
}

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

$private_channels = $channelRepo->getAllChannels();
$all_bots = $botRepo->getAllBots(); // Untuk dropdown di modal

$page_title = 'Kelola Channel';
require_once __DIR__ . '/../partials/header.php';
?>

<style>
/* CSS untuk Modal */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
}
.modal-content {
    background-color: #fefefe;
    margin: 10% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 60%;
    max-width: 700px;
    border-radius: 5px;
    position: relative;
}
.close-button {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    position: absolute;
    top: 10px;
    right: 20px;
}
.close-button:hover,
.close-button:focus {
    color: black;
    text-decoration: none;
    cursor: pointer;
}
#bot-list .bot-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px;
    border-bottom: 1px solid #eee;
}
#bot-list .bot-item:last-child {
    border-bottom: none;
}
.bot-status {
    font-size: 0.9em;
    padding: 3px 8px;
    border-radius: 12px;
}
.bot-status.verified {
    background-color: #d4edda;
    color: #155724;
}
.bot-status.unverified {
    background-color: #f8d7da;
    color: #721c24;
}
</style>

<h1>Kelola Channel Penyimpanan</h1>

<?php if ($message): ?>
    <div class="alert alert-info"><?php echo nl2br(htmlspecialchars($message)); ?></div>
<?php endif; ?>

<p class="description">
    Tambahkan channel pribadi yang akan digunakan bot untuk menyimpan file media.
    Anda dapat mengelola bot mana yang memiliki akses ke setiap channel.
</p>

<h2>Tambah Channel Baru</h2>
<form action="channels.php" method="post" class="mb-20">
    <input type="hidden" name="action" value="add_channel">
    <div class="form-group">
        <label for="name">Nama Channel (untuk referensi)</label>
        <input type="text" id="name" name="name" required>
    </div>
    <div class="form-group">
        <label for="channel_id">ID Channel Telegram</label>
        <input type="text" id="channel_id" name="channel_id" required placeholder="-100123456789">
    </div>
    <button type="submit" class="btn">Tambah Channel</button>
</form>

<h2>Daftar Channel Tersimpan</h2>
<table>
    <thead>
        <tr>
            <th>Nama</th>
            <th>ID Channel</th>
            <th>Jumlah Bot</th>
            <th>Bot Terhubung</th>
            <th colspan="2">Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($private_channels)): ?>
            <tr><td colspan="6">Belum ada channel yang ditambahkan.</td></tr>
        <?php else: ?>
            <?php foreach ($private_channels as $channel): ?>
                <tr data-row-id="<?php echo $channel['channel_id']; ?>">
                    <td class="channel-name"><?php echo htmlspecialchars($channel['name']); ?></td>
                    <td><?php echo htmlspecialchars($channel['channel_id']); ?></td>
                    <td><?php echo htmlspecialchars($channel['bot_count']); ?></td>
                    <td><?php echo htmlspecialchars($channel['bot_usernames'] ?? 'N/A'); ?></td>
                    <td>
                        <button class="btn btn-primary manage-bots-btn"
                                data-channel-id="<?php echo $channel['channel_id']; ?>"
                                data-channel-name="<?php echo htmlspecialchars($channel['name']); ?>">
                            Kelola Bot
                        </button>
                    </td>
                    <td>
                        <button class="btn btn-secondary btn-sm refresh-channel-btn" data-channel-id="<?php echo $channel['channel_id']; ?>">
                            Refresh
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<!-- Modal untuk Manajemen Bot -->
<div id="manage-bots-modal" class="modal">
    <div class="modal-content">
        <span class="close-button">&times;</span>
        <h2 id="modal-title">Kelola Bot untuk Channel: </h2>
        <div id="modal-notifications"></div>

        <h3>Bot Terhubung</h3>
        <div id="bot-list">
            <!-- Daftar bot akan dimuat di sini oleh JavaScript -->
            <p>Memuat...</p>
        </div>

        <hr>

        <h3>Tambah Bot ke Channel</h3>
        <form id="add-bot-to-channel-form">
            <input type="hidden" id="modal-channel-id" name="channel_id">
            <div class="form-group">
                <label for="bot-select">Pilih Bot:</label>
                <select id="bot-select" name="bot_id" required>
                    <option value="">-- Pilih Bot --</option>
                    <?php foreach ($all_bots as $bot): ?>
                        <option value="<?php echo $bot['id']; ?>">
                            <?php echo htmlspecialchars($bot['first_name'] . ' (@' . $bot['username'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn">Tambah Bot</button>
        </form>

        <hr>

        <form id="delete-channel-form" action="channels.php" method="post" onsubmit="return confirm('Yakin ingin menghapus channel ini beserta semua hubungan bot-nya?');">
            <input type="hidden" name="action" value="delete_channel">
            <input type="hidden" id="delete-channel-id-input" name="channel_id">
            <button type="submit" class="btn btn-danger">Hapus Channel Ini</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('manage-bots-modal');
    const closeModalBtn = modal.querySelector('.close-button');
    const manageBtns = document.querySelectorAll('.manage-bots-btn');
    const botListDiv = document.getElementById('bot-list');
    const addBotForm = document.getElementById('add-bot-to-channel-form');
    const modalChannelIdInput = document.getElementById('modal-channel-id');
    const modalNotifications = document.getElementById('modal-notifications');

    const closeModal = () => modal.style.display = 'none';

    function showNotification(message, type = 'info') {
        modalNotifications.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
        setTimeout(() => modalNotifications.innerHTML = '', 4000);
    }

    async function apiRequest(url, body) {
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(body)
            });
            if (!response.ok) throw new Error('Network response was not ok.');
            return await response.json();
        } catch (error) {
            return { status: 'error', message: 'Request failed: ' + error.message };
        }
    }

    function renderBotList(bots, channelId) {
        botListDiv.innerHTML = '';
        if (bots.length === 0) {
            botListDiv.innerHTML = '<p>Belum ada bot yang terhubung ke channel ini.</p>';
            return;
        }
        bots.forEach(bot => {
            const verifiedStatus = bot.verified_at
                ? `<span class="bot-status verified">Terverifikasi</span>`
                : `<span class="bot-status unverified">Belum Diverifikasi</span>`;

            const actionButton = !bot.verified_at
                ? `<button class="btn btn-sm btn-verify" data-bot-id="${bot.id}">Verifikasi</button>`
                : '';

            const item = document.createElement('div');
            item.className = 'bot-item';
            item.innerHTML = `
                <div>
                    <strong>@${bot.username}</strong> (${bot.first_name}) ${verifiedStatus}
                </div>
                <div>
                    ${actionButton}
                    <button class="btn btn-sm btn-danger btn-remove" data-bot-id="${bot.id}">Hapus</button>
                </div>
            `;
            botListDiv.appendChild(item);
        });
    }

    async function loadConnectedBots(channelId) {
        botListDiv.innerHTML = '<p>Memuat...</p>';
        try {
            const response = await fetch(`api/get_channel_bots.php?channel_id=${channelId}`);
            const data = await response.json();
            if (data.status === 'success') {
                renderBotList(data.bots, channelId);
            } else {
                botListDiv.innerHTML = `<p class="text-danger">Error: ${data.message}</p>`;
            }
        } catch (error) {
            botListDiv.innerHTML = `<p class="text-danger">Gagal memuat bot: ${error.message}</p>`;
        }
    }

    closeModalBtn.addEventListener('click', closeModal);
    window.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });

    manageBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const channelId = btn.dataset.channelId;
            const channelName = btn.dataset.channelName;
            modal.querySelector('#modal-title').innerText = 'Kelola Bot untuk Channel: ' + channelName;
            modalChannelIdInput.value = channelId;
            modal.querySelector('#delete-channel-id-input').value = channelId;
            modalNotifications.innerHTML = '';
            modal.style.display = 'block';
            loadConnectedBots(channelId);
        });
    });

    addBotForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const channelId = modalChannelIdInput.value;
        const botId = this.querySelector('#bot-select').value;
        if (!botId) {
            showNotification('Silakan pilih bot terlebih dahulu.', 'danger');
            return;
        }
        const data = await apiRequest('api/add_bot_to_channel.php', { channel_id: channelId, bot_id: botId });
        showNotification(data.message, data.status === 'success' ? 'success' : 'danger');
        if (data.status === 'success') {
            loadConnectedBots(channelId);
        }
    });

    botListDiv.addEventListener('click', async function (e) {
        const channelId = modalChannelIdInput.value;
        const target = e.target;

        if (target.classList.contains('btn-verify')) {
            target.disabled = true;
            target.innerText = 'Memverifikasi...';
            const botId = target.dataset.botId;
            const data = await apiRequest('api/verify_channel_bot.php', { channel_id: channelId, bot_id: botId });
            showNotification(data.message, data.status === 'success' ? 'success' : 'danger');
            loadConnectedBots(channelId);
        }

        if (target.classList.contains('btn-remove')) {
            if (!confirm('Yakin ingin menghapus bot ini dari channel?')) return;
            target.disabled = true;
            const botId = target.dataset.botId;
            const data = await apiRequest('api/remove_bot_from_channel.php', { channel_id: channelId, bot_id: botId });
            showNotification(data.message, data.status === 'success' ? 'success' : 'danger');
            loadConnectedBots(channelId);
        }
    });

    document.querySelector('tbody').addEventListener('click', async function(e) {
        const target = e.target;
        if (target.classList.contains('refresh-channel-btn')) {
            const channelId = target.dataset.channelId;
            target.disabled = true;
            target.innerText = '...';

            const data = await apiRequest('api/update_channel_info.php', { channel_id: channelId });
            if (data.status === 'success') {
                const row = document.querySelector(`tr[data-row-id="${channelId}"]`);
                if (row) {
                    row.querySelector('.channel-name').innerText = data.newName;
                }
                alert(data.message);
            } else {
                alert('Error: ' + data.message);
            }

            target.disabled = false;
            target.innerText = 'Refresh';
        }
    });
});
</script>

<?php
require_once __DIR__ . '/../partials/footer.php';
?>
