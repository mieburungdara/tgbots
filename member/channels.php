<?php
/**
 * Halaman Manajemen Channel Jualan untuk Anggota.
 *
 * Halaman ini memungkinkan anggota (penjual) untuk mendaftarkan atau memperbarui
 * channel Telegram mereka dan grup diskusi yang terhubung.
 */
session_start();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['channel_identifier']) && empty($error_message)) {
    $channel_identifier = trim($_POST['channel_identifier']);
    $group_identifier = trim($_POST['group_identifier'] ?? '');

    if (empty($channel_identifier)) {
        $error_message = "ID atau username channel tidak boleh kosong.";
    } else {
        try {
            $bot_id = $telegram_api->getBotId();

            // 1. Verifikasi Channel
            $bot_member_channel = $telegram_api->getChatMember($channel_identifier, $bot_id);
            if (!$bot_member_channel || !$bot_member_channel['ok'] || !in_array($bot_member_channel['result']['status'], ['administrator', 'creator'])) {
                throw new Exception("Verifikasi Channel Gagal: Pastikan bot telah ditambahkan sebagai *admin* di channel `{$channel_identifier}`.");
            }
            if (!($bot_member_channel['result']['can_post_messages'] ?? false)) {
                throw new Exception("Verifikasi Channel Gagal: Bot memerlukan izin untuk *'Post Messages'* di channel.");
            }
            $channel_info = $telegram_api->getChat($channel_identifier);
            if (!$channel_info || !$channel_info['ok']) {
                throw new Exception("Verifikasi Channel Gagal: Tidak dapat mengambil informasi untuk channel `{$channel_identifier}`.");
            }
            $numeric_channel_id = $channel_info['result']['id'];
            $channel_title = $channel_info['result']['title'];
            $linked_chat_id = $channel_info['result']['linked_chat_id'] ?? null;

            // 2. Verifikasi Grup Diskusi (jika diisi)
            $numeric_group_id = null;
            if (!empty($group_identifier)) {
                $group_info = $telegram_api->getChat($group_identifier);
                if (!$group_info || !$group_info['ok']) {
                    throw new Exception("Verifikasi Grup Gagal: Tidak dapat mengambil informasi untuk grup `{$group_identifier}`.");
                }
                $numeric_group_id = $group_info['result']['id'];

                // Verifikasi bahwa grup ini memang terhubung dengan channel
                if ($numeric_group_id != $linked_chat_id) {
                    throw new Exception("Verifikasi Grup Gagal: Grup `{$group_identifier}` tidak terhubung dengan channel `{$channel_identifier}` di pengaturan Telegram.");
                }
            } elseif ($linked_chat_id) {
                // Jika user tidak mengisi grup tapi channel punya linked group, gunakan itu
                $numeric_group_id = $linked_chat_id;
            }

            // 3. Simpan ke database
            $success = $channelRepo->createOrUpdate($user_id, $numeric_channel_id, $numeric_group_id);

            if ($success) {
                $success_message = "Selamat! Channel '{$channel_title}' telah berhasil didaftarkan.";
                 if ($numeric_group_id) {
                    $group_info = $telegram_api->getChat($numeric_group_id);
                    $group_title = $group_info['ok'] ? $group_info['result']['title'] : 'grup diskusi';
                    $success_message .= " Grup diskusi '{$group_title}' juga telah terhubung.";
                 }
            } else {
                $error_message = "Terjadi kesalahan database saat mencoba mendaftarkan channel ini.";
            }

        } catch (Exception $e) {
            $error_message = "Terjadi error: " . $e->getMessage();
        }
    }
}

$current_channel = $channelRepo->findBySellerId($user_id);
if ($current_channel) {
    $current_channel['title'] = 'Info tidak tersedia';
    $current_channel['username'] = null;
    $current_channel['group_title'] = null;

    try {
        if (isset($telegram_api)) {
            $chat_info = $telegram_api->getChat($current_channel['channel_id']);
            if ($chat_info && $chat_info['ok']) {
                $current_channel['title'] = $chat_info['result']['title'];
                $current_channel['username'] = $chat_info['result']['username'] ?? null;
            }
            if (!empty($current_channel['discussion_group_id'])) {
                 $group_info = $telegram_api->getChat($current_channel['discussion_group_id']);
                 if ($group_info && $group_info['ok']) {
                    $current_channel['group_title'] = $group_info['result']['title'];
                 }
            }
        }
    } catch (Exception $e) {
        $error_message = $error_message ?? "Gagal menghubungi Telegram untuk memperbarui info: " . $e->getMessage();
    }
}

$page_title = 'Manajemen Channel';
require_once __DIR__ . '/../partials/header.php';
?>

<h2>Manajemen Channel Jualan</h2>

<?php if ($error_message): ?>
    <div class="alert alert-danger"><?= nl2br(htmlspecialchars($error_message)) ?></div>
<?php endif; ?>
<?php if ($success_message): ?>
    <div class="alert alert-success"><?= nl2br(htmlspecialchars($success_message)) ?></div>
<?php endif; ?>

<?php if ($current_channel): ?>
    <!-- Management View -->
    <div class="dashboard-card" style="margin-top: 20px;">
        <h3>Channel Terdaftar</h3>
        <p>
            <strong>Nama Channel:</strong> <?= htmlspecialchars($current_channel['title']) ?><br>
            <strong>ID Channel:</strong> <code><?= htmlspecialchars($current_channel['channel_id']) ?></code><br>
            <?php if ($current_channel['username']): ?>
                <strong>Username:</strong> @<?= htmlspecialchars($current_channel['username']) ?><br>
            <?php endif; ?>

            <?php if ($current_channel['group_title']): ?>
                <strong>Grup Diskusi:</strong> <?= htmlspecialchars($current_channel['group_title']) ?><br>
            <?php endif; ?>

            <?php if ($bot_details && isset($bot_details['username'])): ?>
                <strong>Terhubung dengan Bot:</strong> @<?= htmlspecialchars($bot_details['username']) ?><br>
            <?php endif; ?>
            <strong>Status:</strong> <span style="color: green; font-weight: bold;">Aktif</span>
        </p>
        <hr>
        <p>Untuk mengubah channel atau grup, silakan daftarkan kembali menggunakan formulir di bawah ini.</p>
    </div>
<?php else: ?>
    <!-- Wizard/Setup View -->
    <div class="dashboard-card" style="margin-top: 20px;">
        <h3>Selamat Datang!</h3>
        <p>Sepertinya Anda belum mendaftarkan channel jualan. Channel jualan digunakan oleh bot untuk mem-posting pratinjau konten Anda. Ikuti langkah-langkah di bawah ini untuk memulai.</p>
        <h4>Langkah 1: Siapkan Channel Anda</h4>
        <ol>
            <li>Buat sebuah Channel baru di Telegram (jika belum punya).</li>
            <li>Tambahkan bot <strong>@<?= htmlspecialchars($bot_details['username'] ?? 'bot_sistem') ?></strong> sebagai <strong>Admin</strong> di channel Anda.</li>
            <li>Beri bot izin untuk <strong>'Post Messages'</strong>.</li>
            <li>(Opsional) Buat Grup Diskusi dan tautkan ke channel Anda melalui Pengaturan Channel di Telegram.</li>
        </ol>
    </div>
<?php endif; ?>


<div class="dashboard-card" style="margin-top: 20px;">
    <h3><?= $current_channel ? 'Perbarui Channel dan Grup Diskusi' : 'Langkah 2: Daftarkan Channel Anda' ?></h3>
    <form action="channels.php" method="POST">
        <div style="margin-bottom: 15px;">
            <label for="channel_identifier"><strong>ID atau Username Channel</strong></label>
            <input type="text" id="channel_identifier" name="channel_identifier" required placeholder="@channel_jualan_anda">
        </div>
        <div style="margin-bottom: 15px;">
            <label for="group_identifier"><strong>ID atau Username Grup Diskusi (Opsional)</strong></label>
            <input type="text" id="group_identifier" name="group_identifier" placeholder="@grup_diskusi_anda">
            <small>Bot akan mencoba mendeteksi grup secara otomatis jika terhubung di pengaturan Telegram.</small>
        </div>
        <div>
            <button type="submit" class="btn">Simpan Konfigurasi</button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../partials/footer.php';
?>
