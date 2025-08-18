<?php
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/database/PrivateChannelRepository.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("Koneksi database gagal.");
}

$channelRepo = new PrivateChannelRepository($pdo);
$message = null;

// Handle form submissions
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
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            if ($channelRepo->deleteChannel($id)) {
                $_SESSION['message'] = "Channel berhasil dihapus.";
            } else {
                $_SESSION['message'] = "Gagal menghapus channel.";
            }
        }
        header("Location: channels.php");
        exit;
    }
}

// Pesan untuk ditampilkan setelah proses
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Get current channels for display
$private_channels = $channelRepo->getAllChannels();

$page_title = 'Kelola Channel';
require_once __DIR__ . '/../partials/header.php';
?>

<h1>Kelola Channel Penyimpanan</h1>

        <?php if ($message): ?>
            <div class="alert alert-info">
                <?php echo nl2br(htmlspecialchars($message)); ?>
            </div>
        <?php endif; ?>

        <p class="description">
            Tambahkan atau hapus channel pribadi yang akan digunakan bot untuk menyimpan file media.
            Bot akan mendistribusikan file ke channel-channel ini secara bergantian (round-robin) untuk setiap bot.
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
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($private_channels)): ?>
                    <tr>
                        <td colspan="3">Belum ada channel yang ditambahkan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($private_channels as $channel): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($channel['name']); ?></td>
                            <td><?php echo htmlspecialchars($channel['channel_id']); ?></td>
                            <td>
                                <form action="channels.php" method="post" onsubmit="return confirm('Yakin ingin menghapus channel ini?');">
                                    <input type="hidden" name="action" value="delete_channel">
                                    <input type="hidden" name="id" value="<?php echo $channel['id']; ?>">
                                    <button type="submit" class="btn btn-danger">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

<?php
require_once __DIR__ . '/../partials/footer.php';
?>
