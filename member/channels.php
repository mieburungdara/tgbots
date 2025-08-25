<?php
/**
 * Halaman Manajemen Channel Jualan untuk Anggota.
 *
 * Halaman ini memungkinkan anggota (penjual) untuk mendaftarkan atau memperbarui
 * channel Telegram mereka yang akan digunakan bot untuk mem-posting konten.
 */
session_start();

// Jika belum login, redirect ke halaman login
if (!isset($_SESSION['member_user_id'])) {
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/database/SellerSalesChannelRepository.php';
require_once __DIR__ . '/../core/TelegramAPI.php';
require_once __DIR__ . '/../core/helpers.php';

$pdo = get_db_connection();
$channelRepo = new SellerSalesChannelRepository($pdo);
$user_id = $_SESSION['member_user_id'];
$error_message = null;
$success_message = null;

// Ambil bot_id dari session atau dari sumber lain jika tersedia
// Untuk saat ini, kita asumsikan ada bot default atau bot pertama yang terdaftar
// Ini mungkin perlu disesuaikan tergantung pada logika aplikasi yang lebih luas
$bot_details = null;
try {
    $default_bot_id = get_default_bot_id($pdo);
    if (!$default_bot_id) {
        throw new Exception("Tidak ada bot yang terkonfigurasi di sistem.");
    }
    $bot_details = get_bot_details($pdo, $default_bot_id);
    if (!$bot_details || !isset($bot_details['token'])) {
        throw new Exception("Gagal mengambil detail atau token untuk bot default.");
    }
    $telegram_api = new TelegramAPI($bot_details['token']);
} catch (Exception $e) {
    $error_message = "Error inisialisasi sistem: " . $e->getMessage();
}


// Handle POST request (form submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['channel_identifier']) && empty($error_message)) {
    $channel_identifier = trim($_POST['channel_identifier']);

    if (empty($channel_identifier)) {
        $error_message = "ID atau username channel tidak boleh kosong.";
    } else {
        try {
            // 1. Verifikasi bot adalah admin di channel target
            $bot_id = $telegram_api->getBotId();
            $bot_member = $telegram_api->getChatMember($channel_identifier, $bot_id);

            if (!$bot_member || !$bot_member['ok'] || !in_array($bot_member['result']['status'], ['administrator', 'creator'])) {
                throw new Exception("Pendaftaran gagal: Pastikan bot telah ditambahkan sebagai *admin* di channel `{$channel_identifier}`.");
            }

            // 2. Verifikasi bot memiliki izin untuk posting
            if (!($bot_member['result']['can_post_messages'] ?? false)) {
                throw new Exception("Pendaftaran gagal: Bot memerlukan izin untuk *'Post Messages'* di channel tersebut.");
            }

            // 3. Dapatkan informasi channel untuk menyimpan ID numeriknya
            $channel_info = $telegram_api->getChat($channel_identifier);
            if (!$channel_info || !$channel_info['ok']) {
                throw new Exception("Pendaftaran gagal: Tidak dapat mengambil informasi untuk channel `{$channel_identifier}`. Pastikan ID atau username sudah benar.");
            }
            $numeric_channel_id = $channel_info['result']['id'];
            $channel_title = $channel_info['result']['title'];

            // 4. Simpan ke database
            $success = $channelRepo->createOrUpdate($user_id, $numeric_channel_id);

            if ($success) {
                $success_message = "Selamat! Channel '{$channel_title}' (`{$numeric_channel_id}`) telah berhasil didaftarkan sebagai channel jualan Anda.";
            } else {
                $error_message = "Terjadi kesalahan database saat mencoba mendaftarkan channel ini.";
            }

        } catch (Exception $e) {
            $error_message = "Terjadi error: " . $e->getMessage();
        }
    }
}

// Ambil channel yang terdaftar saat ini untuk ditampilkan
$current_channel = $channelRepo->findBySellerId($user_id);
if ($current_channel) {
    // Inisialisasi kunci array untuk menghindari warning
    $current_channel['title'] = 'Info tidak tersedia';
    $current_channel['username'] = null;

    // Jika channel ada, coba dapatkan info terbarunya dari Telegram
    try {
        if (!isset($telegram_api)) { throw new Exception("API Telegram tidak terinisialisasi."); }
        $chat_info = $telegram_api->getChat($current_channel['channel_id']);
        if ($chat_info && $chat_info['ok']) {
            $current_channel['title'] = $chat_info['result']['title'];
            $current_channel['username'] = $chat_info['result']['username'] ?? null;
        }
    } catch (Exception $e) {
        // Biarkan title default, karena sudah diatur sebagai 'Info tidak tersedia' atau 'Gagal mengambil'
        $current_channel['title'] = 'Gagal mengambil info channel.';
        $error_message = $error_message ?? "Gagal menghubungi Telegram: " . $e->getMessage();
    }
}


$page_title = 'Manajemen Channel';
require_once __DIR__ . '/../partials/header.php';
?>

<h2>Manajemen Channel Jualan</h2>
<p>Daftarkan channel Telegram Anda di sini. Bot akan menggunakan channel ini untuk mem-posting pratinjau konten Anda.</p>

<?php if ($error_message): ?>
    <div class="alert alert-danger"><?= nl2br(htmlspecialchars($error_message)) ?></div>
<?php endif; ?>
<?php if ($success_message): ?>
    <div class="alert alert-success"><?= nl2br(htmlspecialchars($success_message)) ?></div>
<?php endif; ?>


<div class="dashboard-card" style="margin-top: 20px;">
    <h3>Channel Terdaftar Saat Ini</h3>
    <?php if ($current_channel): ?>
        <p>
            <strong>Nama Channel:</strong> <?= htmlspecialchars($current_channel['title']) ?><br>
            <strong>ID Channel:</strong> <code><?= htmlspecialchars($current_channel['channel_id']) ?></code><br>
            <?php if ($current_channel['username']): ?>
                <strong>Username:</strong> @<?= htmlspecialchars($current_channel['username']) ?><br>
            <?php endif; ?>
            <?php if ($bot_details && isset($bot_details['username'])): ?>
                <strong>Terhubung dengan Bot:</strong> @<?= htmlspecialchars($bot_details['username']) ?><br>
            <?php endif; ?>
            <strong>Status:</strong> <span style="color: green; font-weight: bold;">Aktif</span>
        </p>
    <?php else: ?>
        <p>Anda belum mendaftarkan channel jualan.</p>
    <?php endif; ?>
</div>


<div class="dashboard-card" style="margin-top: 20px;">
    <h3>Daftarkan atau Perbarui Channel</h3>
    <p>Masukkan ID Channel atau username (contoh: <code>-100123456789</code> atau <code>@namachannel</code>). Pastikan bot sudah menjadi admin di channel tersebut dengan izin untuk 'Post Messages'.</p>
    <form action="channels.php" method="POST">
        <div style="margin-bottom: 15px;">
            <label for="channel_identifier"><strong>ID atau Username Channel</strong></label>
            <input type="text" id="channel_identifier" name="channel_identifier" required>
        </div>
        <div>
            <button type="submit" class="btn">Simpan Channel</button>
        </div>
    </form>
</div>


<?php
require_once __DIR__ . '/../partials/footer.php';
?>
