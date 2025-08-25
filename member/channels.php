<?php
/**
 * Halaman Manajemen Channel Jualan untuk Anggota.
 *
 * Halaman ini memungkinkan anggota (penjual) untuk memilih bot, mendaftarkan channel,
 * dan menghubungkan grup diskusi.
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

// Ambil semua bot yang tersedia untuk dropdown
$all_bots = get_all_bots($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['channel_identifier'])) {
    $selected_bot_id = filter_input(INPUT_POST, 'bot_id', FILTER_VALIDATE_INT);
    $channel_identifier = trim($_POST['channel_identifier']);
    $group_identifier = trim($_POST['group_identifier'] ?? '');

    if (empty($channel_identifier) || !$selected_bot_id) {
        $error_message = "Harap pilih bot dan isi ID atau username channel.";
    } else {
        try {
            $bot_token = get_bot_token($pdo, $selected_bot_id);
            if (!$bot_token) {
                throw new Exception("Bot yang dipilih tidak valid atau tidak memiliki token.");
            }
            $telegram_api = new TelegramAPI($bot_token);

            // Verifikasi Channel
            $bot_member_channel = $telegram_api->getChatMember($channel_identifier, $selected_bot_id);
            if (!$bot_member_channel || !$bot_member_channel['ok'] || !in_array($bot_member_channel['result']['status'], ['administrator', 'creator'])) {
                throw new Exception("Verifikasi Channel Gagal: Pastikan bot yang dipilih telah ditambahkan sebagai admin di channel `{$channel_identifier}`.");
            }
            if (!($bot_member_channel['result']['can_post_messages'] ?? false)) {
                throw new Exception("Verifikasi Channel Gagal: Bot yang dipilih memerlukan izin untuk 'Post Messages' di channel.");
            }
            $channel_info = $telegram_api->getChat($channel_identifier);
            if (!$channel_info || !$channel_info['ok']) {
                throw new Exception("Verifikasi Channel Gagal: Tidak dapat mengambil informasi untuk channel `{$channel_identifier}`.");
            }
            $numeric_channel_id = $channel_info['result']['id'];
            $channel_title = $channel_info['result']['title'];
            $linked_chat_id = $channel_info['result']['linked_chat_id'] ?? null;

            // Verifikasi Grup Diskusi
            $numeric_group_id = null;
            if (!empty($group_identifier)) {
                $group_info = $telegram_api->getChat($group_identifier);
                if (!$group_info || !$group_info['ok']) throw new Exception("Verifikasi Grup Gagal: Tidak dapat mengambil informasi untuk grup `{$group_identifier}`.");
                $numeric_group_id = $group_info['result']['id'];
                if ($numeric_group_id != $linked_chat_id) throw new Exception("Verifikasi Grup Gagal: Grup `{$group_identifier}` tidak terhubung dengan channel `{$channel_identifier}` di pengaturan Telegram.");
            } elseif ($linked_chat_id) {
                $numeric_group_id = $linked_chat_id;
            }

            // Simpan ke database
            $success = $channelRepo->createOrUpdate($user_id, $selected_bot_id, $numeric_channel_id, $numeric_group_id);

            if ($success) {
                $success_message = "Selamat! Channel '{$channel_title}' telah berhasil dikonfigurasi.";
            } else {
                $error_message = "Terjadi kesalahan database saat mencoba menyimpan konfigurasi.";
            }

        } catch (Exception $e) {
            $error_message = "Terjadi error: " . $e->getMessage();
        }
    }
}

$current_channel = $channelRepo->findBySellerId($user_id);
$bot_details = null;
if ($current_channel && !empty($current_channel['bot_id'])) {
    $bot_details = get_bot_details($pdo, $current_channel['bot_id']);
    try {
        if ($bot_details) {
            $telegram_api_for_info = new TelegramAPI($bot_details['token']);
            $chat_info = $telegram_api_for_info->getChat($current_channel['channel_id']);
            $current_channel['title'] = ($chat_info && $chat_info['ok']) ? $chat_info['result']['title'] : 'Info tidak tersedia';
            $current_channel['username'] = ($chat_info && $chat_info['ok']) ? ($chat_info['result']['username'] ?? null) : null;

            if (!empty($current_channel['discussion_group_id'])) {
                 $group_info = $telegram_api_for_info->getChat($current_channel['discussion_group_id']);
                 $current_channel['group_title'] = ($group_info && $group_info['ok']) ? $group_info['result']['title'] : null;
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
        <h3>Konfigurasi Saat Ini</h3>
        <p>
            <strong>Bot Terhubung:</strong> @<?= htmlspecialchars($bot_details['username'] ?? 'N/A') ?><br>
            <strong>Channel Jualan:</strong> <?= htmlspecialchars($current_channel['title'] ?? 'N/A') ?> (<code><?= htmlspecialchars($current_channel['channel_id']) ?></code>)<br>
            <?php if (!empty($current_channel['group_title'])): ?>
                <strong>Grup Diskusi:</strong> <?= htmlspecialchars($current_channel['group_title']) ?><br>
            <?php endif; ?>
        </p>
        <hr>
        <p>Untuk mengubah konfigurasi, silakan gunakan formulir di bawah ini.</p>
    </div>
<?php else: ?>
    <!-- Wizard/Setup View -->
    <div class="dashboard-card" style="margin-top: 20px;">
        <h3>Panduan Konfigurasi Channel</h3>
        <p>Hubungkan bot dengan channel jualan Anda untuk mulai mem-posting konten.</p>
        <ol>
            <li>Pilih salah satu bot yang tersedia dari daftar.</li>
            <li>Tambahkan bot yang Anda pilih sebagai <strong>Admin</strong> di channel Telegram Anda.</li>
            <li>Beri bot tersebut izin untuk <strong>'Post Messages'</strong>.</li>
            <li>(Opsional) Tautkan Grup Diskusi ke channel Anda melalui Pengaturan Channel di Telegram.</li>
            <li>Isi formulir di bawah ini dan simpan.</li>
        </ol>
    </div>
<?php endif; ?>

<div class="dashboard-card" style="margin-top: 20px;">
    <h3><?= $current_channel ? 'Perbarui Konfigurasi' : 'Daftarkan Konfigurasi Baru' ?></h3>
    <form action="channels.php" method="POST">
        <div style="margin-bottom: 15px;">
            <label for="bot_id"><strong>Pilih Bot</strong></label>
            <select id="bot_id" name="bot_id" required>
                <option value="">-- Pilih Bot --</option>
                <?php foreach ($all_bots as $bot): ?>
                    <option value="<?= $bot['id'] ?>" <?= (isset($current_channel['bot_id']) && $current_channel['bot_id'] == $bot['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($bot['name']) ?> (@<?= htmlspecialchars($bot['username']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="margin-bottom: 15px;">
            <label for="channel_identifier"><strong>ID atau Username Channel</strong></label>
            <input type="text" id="channel_identifier" name="channel_identifier" required value="<?= htmlspecialchars($current_channel['channel_id'] ?? '') ?>" placeholder="@channel_jualan_anda">
        </div>
        <div style="margin-bottom: 15px;">
            <label for="group_identifier"><strong>ID atau Username Grup Diskusi (Opsional)</strong></label>
            <input type="text" id="group_identifier" name="group_identifier" value="<?= htmlspecialchars($current_channel['discussion_group_id'] ?? '') ?>" placeholder="@grup_diskusi_anda">
            <small>Kosongkan untuk deteksi otomatis jika grup sudah terhubung di pengaturan Telegram.</small>
        </div>
        <div>
            <button type="submit" class="btn">Simpan Konfigurasi</button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../partials/footer.php';
?>
