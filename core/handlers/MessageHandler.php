<?php

require_once __DIR__ . '/../database/PackageRepository.php';
require_once __DIR__ . '/../database/SaleRepository.php';
require_once __DIR__ . '/../database/MediaFileRepository.php';
require_once __DIR__ . '/../database/BotChannelUsageRepository.php';
require_once __DIR__ . '/../database/AnalyticsRepository.php';
require_once __DIR__ . '/../database/SellerSalesChannelRepository.php';
require_once __DIR__ . '/../database/ChannelPostPackageRepository.php';

class MessageHandler
{
    private $pdo;
    private $telegram_api;
    private $user_repo;
    private $package_repo;
    private $media_repo;
    private $bot_channel_usage_repo;
    private $sale_repo;
    private $analytics_repo;
    private $sales_channel_repo;
    private $post_package_repo;
    private $current_user;
    private $chat_id;
    private $message;

    public function __construct(PDO $pdo, TelegramAPI $telegram_api, UserRepository $user_repo, array $current_user, int $chat_id, array $message)
    {
        $this->pdo = $pdo;
        $this->telegram_api = $telegram_api;
        $this->user_repo = $user_repo;
        $this->package_repo = new PackageRepository($pdo);
        $this->media_repo = new MediaFileRepository($pdo);
        $this->bot_channel_usage_repo = new BotChannelUsageRepository($pdo);
        $this->sale_repo = new SaleRepository($pdo);
        $this->analytics_repo = new AnalyticsRepository($pdo);
        $this->sales_channel_repo = new SellerSalesChannelRepository($pdo);
        $this->post_package_repo = new ChannelPostPackageRepository($pdo);
        $this->current_user = $current_user;
        $this->chat_id = $chat_id;
        $this->message = $message;
    }

    public function handle()
    {
        // Fitur baru: Tangani pesan yang di-forward otomatis dari channel ke grup diskusi
        if (isset($this->message['is_automatic_forward']) && $this->message['is_automatic_forward'] === true) {
            $this->handleAutomaticForward();
            return; // Hentikan proses lebih lanjut untuk pesan ini
        }

        // The rest of the logic requires a text message that is a command.
        if (!isset($this->message['text']) || strpos($this->message['text'], '/') !== 0) {
            return;
        }

        $text = $this->message['text'];

        $parts = explode(' ', $text);
        $command = $parts[0];

        // This can be further refactored into command classes
        switch ($command) {
            case '/start':
                $this->handleStartCommand($parts);
                break;
            case '/sell':
                $this->handleSellCommand();
                break;
            case '/addmedia':
                $this->handleAddMediaCommand();
                break;
            case '/konten':
                $this->handleKontenCommand($parts);
                break;
            case '/balance':
                $this->handleBalanceCommand();
                break;
            case '/login':
                $this->handleLoginCommand();
                break;
            case '/me':
                $this->handleMeCommand();
                break;
            case '/help':
                $this->handleHelpCommand();
                break;
            case '/register_channel':
                $this->handleRegisterChannelCommand($parts);
                break;
            case '/dev_addsaldo':
            case '/feature':
                $this->handleAdminCommands($command, $parts);
                break;
        }
    }

    private function handleRegisterChannelCommand(array $parts)
    {
        if (count($parts) < 2) {
            $this->telegram_api->sendMessage($this->chat_id, "Format perintah salah. Gunakan: `/register_channel <ID atau @username channel>`");
            return;
        }

        $channel_identifier = $parts[1];

        // 1. Verify the user is a registered seller
        if (empty($this->current_user['public_seller_id'])) {
            $this->telegram_api->sendMessage($this->chat_id, "⚠️ Perintah ini hanya untuk penjual terdaftar.");
            return;
        }

        // 2. Verify the bot is an admin in the channel
        $bot_member = $this->telegram_api->getChatMember($channel_identifier, $this->telegram_api->getBotId());

        if (!$bot_member || !$bot_member['ok'] || !in_array($bot_member['result']['status'], ['administrator', 'creator'])) {
            $this->telegram_api->sendMessage($this->chat_id, "⚠️ Pendaftaran gagal: Pastikan bot telah ditambahkan sebagai *admin* di channel `{$channel_identifier}`.", 'Markdown');
            return;
        }

        // 3. Check for necessary permissions
        if (!($bot_member['result']['can_post_messages'] ?? false)) {
             $this->telegram_api->sendMessage($this->chat_id, "⚠️ Pendaftaran gagal: Bot memerlukan izin untuk *'Post Messages'* di channel tersebut.", 'Markdown');
            return;
        }

        // 4. All checks passed, get full channel info to store the numeric ID
        $channel_info = $this->telegram_api->getChat($channel_identifier);
        if (!$channel_info || !$channel_info['ok']) {
            $this->telegram_api->sendMessage($this->chat_id, "⚠️ Pendaftaran gagal: Tidak dapat mengambil informasi untuk channel `{$channel_identifier}`. Pastikan ID atau username channel sudah benar.", 'Markdown');
            return;
        }
        $numeric_channel_id = $channel_info['result']['id'];
        $channel_title = $channel_info['result']['title'];

        // 5. Save to database
        $success = $this->sales_channel_repo->createOrUpdate($this->current_user['id'], $numeric_channel_id);

        if ($success) {
            $escaped_title = $this->telegram_api->escapeMarkdown($channel_title);
            $this->telegram_api->sendMessage($this->chat_id, "✅ Selamat! Channel *{$escaped_title}* (`{$numeric_channel_id}`) telah berhasil didaftarkan sebagai channel jualan Anda.", 'Markdown');
        } else {
            $this->telegram_api->sendMessage($this->chat_id, "⚠️ Terjadi kesalahan database saat mencoba mendaftarkan channel ini.");
        }
    }

    private function handleMeCommand()
    {
        $user_id = $this->current_user['id'];

        // Mengambil statistik
        $sales_stats = $this->analytics_repo->getSellerSummary($user_id); // Reusing existing method

        // Format pesan
        $user_name = $this->telegram_api->escapeMarkdown(trim($this->current_user['first_name'] . ' ' . ($this->current_user['last_name'] ?? '')));
        $balance = "Rp " . number_format($this->current_user['balance'], 0, ',', '.');
        $seller_id = $this->current_user['public_seller_id'] ? "`" . $this->current_user['public_seller_id'] . "`" : "Belum terdaftar";

        $total_sales = $sales_stats['total_sales'];
        $total_revenue = "Rp " . number_format($sales_stats['total_revenue'], 0, ',', '.');

        $message = "👤 *Profil Anda*\n\n";
        $message .= "Nama: *{$user_name}*\n";
        $message .= "Telegram ID: `{$this->current_user['telegram_id']}`\n";
        $message .= "ID Penjual: {$seller_id}\n\n";

        $message .= "💰 *Keuangan*\n";
        $message .= "Saldo Saat Ini: *{$balance}*\n\n";

        $message .= "📈 *Aktivitas Penjualan*\n";
        $message .= "Total Item Terjual: *{$total_sales}* item\n";
        $message .= "Total Pendapatan: *{$total_revenue}*";

        $this->telegram_api->sendMessage($this->chat_id, $message, 'Markdown');
    }

    private function handleHelpCommand()
    {
        $help_text = <<<EOT
*🤖 Panduan Perintah Bot 🤖*

Berikut adalah perintah utama yang bisa Anda gunakan:

*--- UNTUK PENJUAL ---*

➡️ `/sell`
Balas (reply) sebuah media (foto/video/album) dengan perintah ini untuk mulai menjual. Bot akan memandu Anda untuk menetapkan harga.

➡️ `/addmedia`
Gunakan saat proses `/sell` (sebelum menetapkan harga) untuk menambahkan lebih banyak media ke dalam satu paket.

➡️ `/addmedia <ID_PAKET>`
Gunakan sambil me-reply media baru untuk menambahkan media tersebut ke paket yang sudah ada. Contoh: `/addmedia ABCD_0001`

➡️ `/register_channel <ID_CHANNEL>`
Daftarkan channel jualan Anda. Bot harus menjadi admin di channel tersebut. Contoh: `/register_channel @channel_saya`

*--- UNTUK SEMUA PENGGUNA ---*

➡️ `/konten <ID_PAKET>`
Lihat detail atau beli sebuah konten. Contoh: `/konten ABCD_0001`

➡️ `/me`
Lihat profil, ID penjual, dan ringkasan penjualan Anda.

➡️ `/balance`
Cek saldo Anda saat ini.

➡️ `/login`
Dapatkan tautan unik untuk masuk ke panel member di web.
EOT;

        if ($this->current_user['role'] === 'admin') {
            $admin_help_text = <<<EOT


*--- KHUSUS ADMIN ---*

➡️ `/dev_addsaldo <user_id> <jumlah>`
Menambah saldo ke pengguna.

➡️ `/feature <package_id> <channel_id>`
Mempromosikan paket ke channel.
EOT;
            $help_text .= $admin_help_text;
        }

        $this->telegram_api->sendLongMessage($this->chat_id, $help_text, 'Markdown');
    }

    private function handleStartCommand(array $parts)
    {
        if (count($parts) > 1 && strpos($parts[1], 'package_') === 0) {
            $package_id = substr($parts[1], strlen('package_'));
            $package = $this->package_repo->find((int)$package_id);
            if ($package && $package['status'] == 'available') {
                $price_formatted = "Rp " . number_format($package['price'], 0, ',', '.');
                $balance_formatted = "Rp " . number_format($this->current_user['balance'], 0, ',', '.');
                $escaped_description = $this->telegram_api->escapeMarkdown($package['description']);
                $reply_text = "Anda tertarik dengan item berikut:\n\n*Deskripsi:* {$escaped_description}\n*Harga:* {$price_formatted}\n\nSaldo Anda saat ini: {$balance_formatted}.";
                $keyboard = ['inline_keyboard' => [[['text' => "Beli Sekarang ({$price_formatted})", 'callback_data' => "buy_{$package_id}"]]]];
                $this->telegram_api->sendMessage($this->chat_id, $reply_text, 'Markdown', json_encode($keyboard));
            } else {
                $this->telegram_api->sendMessage($this->chat_id, "Maaf, item ini sudah tidak tersedia atau tidak ditemukan.");
            }
        } else {
            $welcome_message = "👋 *Selamat Datang di Bot Marketplace!* 🤖\n\n" .
                               "Berikut adalah beberapa perintah yang bisa Anda gunakan:\n\n" .
                               "- *Menjual Konten* 📸\n" .
                               "  Reply media (foto/video) yang ingin Anda jual dengan perintah `/sell`.\n\n" .
                               "- *Cek Saldo* 💰\n" .
                               "  Gunakan perintah `/balance` untuk melihat saldo Anda.\n\n" .
                               "- *Akses Konten* 📂\n" .
                               "  Gunakan `/konten <ID Paket>` untuk mengunduh kembali konten yang sudah Anda beli atau jual.\n\n" .
                               "- *Login ke Panel* 🌐\n" .
                               "  Gunakan perintah `/login` untuk mendapatkan link akses ke panel member Anda.\n\n" .
                               "Ada yang bisa saya bantu?";
            $this->telegram_api->sendMessage($this->chat_id, $welcome_message, 'Markdown');
        }
    }

    private function handleSellCommand()
    {
        if (!isset($this->message['reply_to_message'])) {
            $this->telegram_api->sendMessage($this->chat_id, "Untuk menjual, silakan reply media yang ingin Anda jual dengan perintah /sell.");
            return;
        }

        // Cek apakah pengguna sudah menjadi penjual
        if (empty($this->current_user['public_seller_id'])) {
            $text = "Anda belum terdaftar sebagai penjual. Apakah Anda ingin mendaftar sekarang?\n\nDengan mendaftar, Anda akan mendapatkan ID Penjual unik.";
            $keyboard = ['inline_keyboard' => [[['text' => "Ya, Daftar Sekarang", 'callback_data' => "register_seller"]]]];
            $this->telegram_api->sendMessage($this->chat_id, $text, null, json_encode($keyboard));
            return;
        }

        $replied_message = $this->message['reply_to_message'];

        // Cek apakah media yang di-reply sudah ada di database.
        // Ini penting karena kita butuh data media untuk proses selanjutnya.
        $stmt_check_media = $this->pdo->prepare("SELECT COUNT(*) FROM media_files WHERE message_id = ? AND chat_id = ?");
        $stmt_check_media->execute([$replied_message['message_id'], $replied_message['chat']['id']]);
        if ($stmt_check_media->fetchColumn() == 0) {
             $this->telegram_api->sendMessage($this->chat_id, "⚠️ Gagal. Pastikan Anda me-reply pesan media (foto/video) yang sudah tersimpan di bot.");
             return;
        }

        // Ambil info media untuk ditampilkan ke pengguna
        $stmt_media_info = $this->pdo->prepare("SELECT media_group_id, caption FROM media_files WHERE message_id = ? AND chat_id = ?");
        $stmt_media_info->execute([$replied_message['message_id'], $replied_message['chat']['id']]);
        $media_info = $stmt_media_info->fetch(PDO::FETCH_ASSOC);

        $media_group_id = $media_info['media_group_id'] ?? null;
        $description = $media_info['caption'] ?? ''; // Default

        if ($media_group_id) {
            // Jika ini adalah media group, cari caption dari salah satu item
            $stmt_caption = $this->pdo->prepare("SELECT caption FROM media_files WHERE media_group_id = ? AND caption IS NOT NULL AND caption != '' LIMIT 1");
            $stmt_caption->execute([$media_group_id]);
            $group_caption = $stmt_caption->fetchColumn();
            if ($group_caption) {
                $description = $group_caption;
            }
        }

        // Dapatkan rincian jenis media
        $media_details_str = '';
        $emoji_map = [
            'photo' => '🖼️', 'video' => '📹', 'document' => '📄', 'audio' => '🎵',
            'voice' => '🎤', 'animation' => '🎬', 'video_note' => '⏺️'
        ];

        if ($media_group_id) {
            $stmt_types = $this->pdo->prepare("SELECT type FROM media_files WHERE media_group_id = ?");
            $stmt_types->execute([$media_group_id]);
            $types = $stmt_types->fetchAll(PDO::FETCH_COLUMN);
            $type_counts = array_count_values($types);

            $details_parts = [];
            foreach ($type_counts as $type => $count) {
                $emoji = $emoji_map[$type] ?? '❓';
                $details_parts[] = "{$count} {$emoji}";
            }
            $media_details_str = implode(', ', $details_parts);
        } else {
            $stmt_type = $this->pdo->prepare("SELECT type FROM media_files WHERE message_id = ? AND chat_id = ?");
            $stmt_type->execute([$replied_message['message_id'], $replied_message['chat']['id']]);
            $type = $stmt_type->fetchColumn();
            $emoji = $emoji_map[$type] ?? '❓';
            $media_details_str = "1 {$emoji}";
        }

        // Simpan konteks pesan yang akan dijual untuk langkah selanjutnya.
        // Strukturnya adalah array of messages untuk mendukung /addmedia
        $state_context = [
            'media_messages' => [
                [
                    'message_id' => $replied_message['message_id'],
                    'chat_id' => $replied_message['chat']['id']
                ]
            ]
        ];
        $this->user_repo->setUserState($this->current_user['id'], 'awaiting_price', $state_context);

        // Buat dan kirim pesan yang informatif
        $message_text = "✅ Media telah siap untuk dijual.\n\n";
        if (!empty($description)) {
            $description_escaped = $this->telegram_api->escapeMarkdown($description);
            $message_text .= "Deskripsi: *\"" . $description_escaped . "\"*\n";
        }
        $message_text .= "Isi Konten: *{$media_details_str}*\n\n";
        $message_text .= "Sekarang, silakan masukkan harga untuk paket ini (contoh: 50000).\n\n";
        $message_text .= "_Ketik /cancel untuk membatalkan._";

        $this->telegram_api->sendMessage($this->chat_id, $message_text, 'Markdown');
    }

    private function handleKontenCommand(array $parts)
    {
        $internal_user_id = $this->current_user['id'];
        if (count($parts) !== 2) {
            $this->telegram_api->sendMessage($this->chat_id, "Format perintah salah. Gunakan: /konten <ID Konten>");
            return;
        }

        $public_id = $parts[1];
        $package = $this->package_repo->findByPublicId($public_id);

        if (!$package) {
            $this->telegram_api->sendMessage($this->chat_id, "Konten dengan ID `{$public_id}` tidak ditemukan.", 'Markdown');
            return;
        }
        $package_id = $package['id'];

        $thumbnail = null;
        // Coba dapatkan thumbnail yang spesifik terlebih dahulu
        if (!empty($package['thumbnail_media_id'])) {
            $stmt_thumb = $this->pdo->prepare("SELECT type, chat_id, message_id FROM media_files WHERE id = ?");
            $stmt_thumb->execute([$package['thumbnail_media_id']]);
            $thumbnail = $stmt_thumb->fetch(PDO::FETCH_ASSOC);
        }

        // Jika tidak ada thumbnail spesifik atau tidak ditemukan, gunakan file pertama dari paket
        if (!$thumbnail) {
            $package_files = $this->package_repo->getPackageFiles($package_id);
            if (!empty($package_files)) {
                $thumbnail = $package_files[0];
            }
        }

        if (!$thumbnail) {
            $this->telegram_api->sendMessage($this->chat_id, "Konten ini tidak memiliki media yang dapat ditampilkan.");
            return;
        }

        $is_admin = ($this->current_user['role'] === 'admin');
        $is_seller = ($package['seller_user_id'] == $internal_user_id);

        // Defensive check: Inexplicably, sale_repo is sometimes null here.
        // Re-initialize it to prevent a fatal error.
        if ($this->sale_repo === null) {
            app_log("DEFENSIVE: Re-initializing SaleRepository in MessageHandler because it was null.", 'warning');
            $this->sale_repo = new SaleRepository($this->pdo);
        }

        $has_purchased = $this->sale_repo->hasUserPurchased($package_id, $internal_user_id);

        $has_access = $is_admin || $is_seller || $has_purchased;

        $keyboard = [];
        if ($has_access) {
            $keyboard_buttons = [[['text' => 'Lihat Selengkapnya 📂', 'callback_data' => "view_page_{$package['public_id']}_0"]]];

            // Tambahkan tombol "Post ke Channel" jika pengguna adalah penjual dan punya channel terdaftar
            if ($is_seller) {
                $sales_channel = $this->sales_channel_repo->findBySellerId($internal_user_id);
                if ($sales_channel) {
                    $keyboard_buttons[0][] = ['text' => '📢 Post ke Channel', 'callback_data' => "post_channel_{$package['public_id']}"];
                }
            }
            $keyboard = ['inline_keyboard' => $keyboard_buttons];

        } elseif ($package['status'] === 'available') {
            $price_formatted = "Rp " . number_format($package['price'], 0, ',', '.');
            $keyboard = ['inline_keyboard' => [[['text' => "Beli Konten Ini ({$price_formatted}) 🛒", 'callback_data' => "buy_{$package['public_id']}"]]]];
        }

        $caption = $package['description'];
        $reply_markup = !empty($keyboard) ? json_encode($keyboard) : null;

        // Gunakan copyMessage untuk mengirim pratinjau
        if (isset($thumbnail['chat_id']) && isset($thumbnail['message_id'])) {
            $this->telegram_api->copyMessage(
                $this->chat_id,
                $thumbnail['chat_id'],
                $thumbnail['message_id'],
                $caption,
                null, // parse_mode
                $reply_markup
            );
        } else {
            // Fallback jika karena alasan tertentu chat_id atau message_id tidak ada
            $this->telegram_api->sendMessage($this->chat_id, $caption, null, $reply_markup);
        }
    }

    private function handleBalanceCommand()
    {
        $balance = "Rp " . number_format($this->current_user['balance'], 2, ',', '.');
        $this->telegram_api->sendMessage($this->chat_id, "Saldo Anda saat ini: {$balance}");
    }

    private function handleLoginCommand()
    {
        if (!defined('BASE_URL') || empty(BASE_URL)) {
            $this->telegram_api->sendMessage($this->chat_id, "Maaf, terjadi kesalahan teknis (ERR:CFG01).");
            return;
        }
        $login_token = bin2hex(random_bytes(32));
        $this->pdo->prepare("UPDATE members SET login_token = ?, token_created_at = NOW(), token_used = 0 WHERE user_id = ?")
             ->execute([$login_token, $this->current_user['id']]);
        $login_link = rtrim(BASE_URL, '/') . '/member/index.php?token=' . $login_token;
        $keyboard = ['inline_keyboard' => [[['text' => 'Login ke Panel Member', 'url' => $login_link]]]];
        $this->telegram_api->sendMessage($this->chat_id, "Klik tombol di bawah ini untuk masuk ke Panel Member Anda. Tombol ini hanya dapat digunakan satu kali.", null, json_encode($keyboard));
    }

    private function handleAdminCommands(string $command, array $parts)
    {
        if ($this->current_user['role'] !== 'admin') {
            return; // Not an admin
        }
        // ... Logic for admin commands, still using direct PDO.
        // This can also be refactored.
    }

    private function handleAddMediaCommand()
    {
        $parts = explode(' ', $this->message['text']);
        $has_id = count($parts) > 1;

        if ($has_id) {
            // Logic for adding media to an existing package
            $this->addMediaToExistingPackage($parts[1]);
        } else {
            // Logic for adding media to a new package being created
            $this->addMediaToNewPackage();
        }
    }

    private function addMediaToNewPackage()
    {
        if ($this->current_user['state'] !== 'awaiting_price') {
            $this->telegram_api->sendMessage($this->chat_id, "⚠️ Perintah ini hanya bisa digunakan saat Anda sedang dalam proses menjual item (setelah `/sell`).");
            return;
        }

        if (!isset($this->message['reply_to_message'])) {
            $this->telegram_api->sendMessage($this->chat_id, "Untuk menambah media, silakan reply media yang ingin Anda tambahkan dengan perintah /addmedia.");
            return;
        }

        $replied_message = $this->message['reply_to_message'];

        $stmt_check_media = $this->pdo->prepare("SELECT COUNT(*) FROM media_files WHERE message_id = ? AND chat_id = ?");
        $stmt_check_media->execute([$replied_message['message_id'], $replied_message['chat']['id']]);
        if ($stmt_check_media->fetchColumn() == 0) {
             $this->telegram_api->sendMessage($this->chat_id, "⚠️ Gagal. Pastikan Anda me-reply pesan media (foto/video) yang sudah tersimpan di bot.");
             return;
        }

        $state_context = json_decode($this->current_user['state_context'], true);

        foreach ($state_context['media_messages'] as $msg) {
            if ($msg['message_id'] == $replied_message['message_id']) {
                $this->telegram_api->sendMessage($this->chat_id, "⚠️ Media ini sudah ada dalam paket.");
                return;
            }
        }

        $state_context['media_messages'][] = [
            'message_id' => $replied_message['message_id'],
            'chat_id' => $replied_message['chat']['id']
        ];

        $this->user_repo->setUserState($this->current_user['id'], 'awaiting_price', $state_context);

        $this->telegram_api->sendMessage($this->chat_id, "✅ Media berhasil ditambahkan. Silakan tambah media lagi dengan /addmedia, atau masukkan harga untuk menyelesaikan.");
    }

    private function addMediaToExistingPackage($public_package_id)
    {
        if (!isset($this->message['reply_to_message'])) {
            $this->telegram_api->sendMessage($this->chat_id, "Untuk menambah media ke paket yang sudah ada, silakan reply media baru dengan perintah `/addmedia <ID_PAKET>`.");
            return;
        }

        // 1. Find package and check ownership
        $package = $this->package_repo->findByPublicId($public_package_id);
        if (!$package) {
            $this->telegram_api->sendMessage($this->chat_id, "⚠️ Paket dengan ID `{$public_package_id}` tidak ditemukan.", 'Markdown');
            return;
        }
        if ($package['seller_user_id'] != $this->current_user['id']) {
            $this->telegram_api->sendMessage($this->chat_id, "⚠️ Anda tidak memiliki izin untuk mengubah paket ini.");
            return;
        }

        $replied_message = $this->message['reply_to_message'];

        // 2. Validate the new media
        $stmt_check_media = $this->pdo->prepare("SELECT id, media_group_id FROM media_files WHERE message_id = ? AND chat_id = ?");
        $stmt_check_media->execute([$replied_message['message_id'], $replied_message['chat']['id']]);
        $new_media_info = $stmt_check_media->fetch(PDO::FETCH_ASSOC);
        if (!$new_media_info) {
             $this->telegram_api->sendMessage($this->chat_id, "⚠️ Gagal. Pastikan Anda me-reply pesan media (foto/video) yang sudah tersimpan di bot.");
             return;
        }

        // 3. Get storage channel from existing package files
        $stmt_storage = $this->pdo->prepare("SELECT storage_channel_id FROM media_files WHERE package_id = ? AND storage_channel_id IS NOT NULL LIMIT 1");
        $stmt_storage->execute([$package['id']]);
        $storage_channel_id = $stmt_storage->fetchColumn();
        if (!$storage_channel_id) {
            $this->telegram_api->sendMessage($this->chat_id, "⚠️ Gagal menemukan channel penyimpanan untuk paket ini. Hubungi admin.");
            return;
        }

        // 4. Collect all new media files to be added
        $media_to_copy = [];
        if ($new_media_group_id = $new_media_info['media_group_id']) {
            $stmt_group = $this->pdo->prepare("SELECT id, message_id FROM media_files WHERE media_group_id = ?");
            $stmt_group->execute([$new_media_group_id]);
            while ($row = $stmt_group->fetch(PDO::FETCH_ASSOC)) {
                $media_to_copy[$row['id']] = $row['message_id'];
            }
        } else {
            $media_to_copy[$new_media_info['id']] = $replied_message['message_id'];
        }

        // 5. Copy new media to storage channel
        $original_db_ids = array_keys($media_to_copy);
        $original_message_ids = array_values($media_to_copy);

        $copied_messages_result = $this->telegram_api->copyMessages($storage_channel_id, $replied_message['chat']['id'], json_encode($original_message_ids));
        if (!$copied_messages_result || !isset($copied_messages_result['ok']) || !$copied_messages_result['ok'] || count($copied_messages_result['result']) !== count($original_message_ids)) {
            $this->telegram_api->sendMessage($this->chat_id, "⚠️ Gagal menyimpan media baru. Proses dibatalkan.");
            return;
        }

        // 6. Link new media to the existing package
        $new_storage_message_ids = $copied_messages_result['result'];
        $stmt_link = $this->pdo->prepare("UPDATE media_files SET package_id = ?, storage_channel_id = ?, storage_message_id = ? WHERE id = ?");
        for ($i = 0; $i < count($original_db_ids); $i++) {
            $stmt_link->execute([$package['id'], $storage_channel_id, $new_storage_message_ids[$i]['message_id'], $original_db_ids[$i]]);
        }

        $this->telegram_api->sendMessage($this->chat_id, "✅ " . count($original_db_ids) . " media baru telah ditambahkan ke paket *{$public_package_id}*.", 'Markdown');
    }

    private function handleAutomaticForward()
    {
        app_log("[DEBUG] handleAutomaticForward triggered for chat " . $this->chat_id, 'bot_debug');

        // 1. Get the original channel post's details
        $forward_info = $this->message['forward_from_chat'] ?? null;
        $original_message_id = $this->message['forward_from_message_id'] ?? null;

        if (!$forward_info || !$original_message_id) {
            app_log("[DEBUG] Message is not a valid automatic forward. Exiting.", 'bot_debug');
            return; // Not a valid forward from a channel
        }
        $original_channel_id = $forward_info['id'];
        app_log("[DEBUG] Forward info found: Channel ID {$original_channel_id}, Message ID {$original_message_id}", 'bot_debug');

        // 2. Look up the package_id from the database
        app_log("[DEBUG] Querying database for package...", 'bot_debug');
        $package = $this->post_package_repo->findByChannelAndMessage($original_channel_id, $original_message_id);

        if (!$package || $package['status'] !== 'available') {
            app_log("[DEBUG] No linked package found in DB or package is not available. Exiting.", 'bot_debug');
            return; // Not a valid forward from a channel
        }
        $original_channel_id = $forward_info['id'];

        // 2. Look up the package_id from the database
        $package = $this->post_package_repo->findByChannelAndMessage($original_channel_id, $original_message_id);

        if (!$package || $package['status'] !== 'available') {
            return; // No linked package found, or package is not available
        }

        $public_id = $package['public_id'];
        app_log("[DEBUG] Package found: ID {$package['id']}, Public ID {$public_id}. Proceeding to send reply.", 'bot_debug');

        // 3. Create the new keyboard with callback_data
        $price_formatted = "Rp " . number_format($package['price'], 0, ',', '.');
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => "Beli ({$price_formatted}) 🛒", 'callback_data' => "buy_{$public_id}"]
                ]
            ]
        ];
        $reply_markup = json_encode($keyboard);

        // 4. Send the reply to the forwarded message in the discussion group
        $reply_parameters = json_encode(['message_id' => $this->message['message_id']]);

        $reply_text = "Klik untuk membeli atau berdiskusi:";

        $this->telegram_api->sendMessage(
            $this->chat_id,
            $reply_text,
            'Markdown',
            $reply_markup,
            null, // message_thread_id
            $reply_parameters
        );

        app_log("[DEBUG] Reply message sent successfully to chat " . $this->chat_id . " in reply to " . $this->message['message_id'], 'bot_debug');
    }
}
