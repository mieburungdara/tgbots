<?php

require_once __DIR__ . '/../database/PackageRepository.php';
require_once __DIR__ . '/../database/SaleRepository.php';
require_once __DIR__ . '/../database/MediaFileRepository.php';
require_once __DIR__ . '/../database/BotChannelUsageRepository.php';
require_once __DIR__ . '/../database/AnalyticsRepository.php';
require_once __DIR__ . '/../database/SellerSalesChannelRepository.php';
require_once __DIR__ . '/../database/ChannelPostPackageRepository.php';
require_once __DIR__ . '/../database/UserRepository.php';
require_once __DIR__ . '/HandlerInterface.php';

/**
 * Menangani pesan teks yang masuk, terutama perintah (commands).
 * Kelas ini bertindak sebagai router utama untuk mendelegasikan perintah
 * ke metode-metode penanganan yang sesuai.
 */
class MessageHandler implements HandlerInterface
{
    /**
     * Titik masuk utama untuk menangani pesan.
     * Mengidentifikasi apakah pesan adalah forward otomatis, perintah, atau lainnya,
     * lalu mendelegasikannya ke metode yang sesuai.
     */
    public function handle(App $app, array $message): void
    {
        // Fitur baru: Tangani pesan yang di-forward otomatis dari channel ke grup diskusi
        if (isset($message['is_automatic_forward']) && $message['is_automatic_forward'] === true) {
            app_log("[TRACE] `is_automatic_forward` terdeteksi. Memanggil handleAutomaticForward.", 'trace');
            $this->handleAutomaticForward($app, $message);
            return; // Hentikan proses lebih lanjut untuk pesan ini
        }

        // Logika selanjutnya memerlukan pesan teks yang merupakan sebuah perintah.
        if (!isset($message['text']) || strpos($message['text'], '/') !== 0) {
            // Periksa state machine di sini, karena webhook tidak lagi menanganinya
            if ($app->user['state'] !== null && isset($message['text'])) {
                $this->handleState($app, $message);
            }
            return;
        }

        $text = $message['text'];

        $parts = explode(' ', $text);
        $command = $parts[0];

        // Ini dapat direfaktor lebih lanjut menjadi kelas-kelas perintah
        switch ($command) {
            case '/start':
                $this->handleStartCommand($app, $message, $parts);
                break;
            case '/sell':
                $this->handleSellCommand($app, $message);
                break;
            case '/addmedia':
                $this->handleAddMediaCommand($app, $message);
                break;
            case '/konten':
                $this->handleKontenCommand($app, $message, $parts);
                break;
            case '/balance':
                $this->handleBalanceCommand($app, $message);
                break;
            case '/login':
                $this->handleLoginCommand($app, $message);
                break;
            case '/me':
                $this->handleMeCommand($app, $message);
                break;
            case '/help':
                $this->handleHelpCommand($app, $message);
                break;
            case '/register_channel':
                $this->handleRegisterChannelCommand($app, $message, $parts);
                break;
            case '/dev_addsaldo':
            case '/feature':
                $this->handleAdminCommands($app, $message, $command, $parts);
                break;
        }
    }

    /**
     * Menangani logika state machine yang sebelumnya ada di webhook.
     */
    private function handleState(App $app, array $message): void
    {
        $user_repo = new UserRepository($app->pdo, $app->bot['telegram_bot_id']);
        $text = $message['text'];
        $state_context = json_decode($app->user['state_context'] ?? '{}', true);

        if (strpos($text, '/cancel') === 0) {
            $user_repo->setUserState($app->user['telegram_id'], null, null);
            $app->telegram_api->sendMessage($app->chat_id, "Operasi dibatalkan.");
            return;
        }

        // Contoh: Logika untuk state 'awaiting_price'
        if ($app->user['state'] === 'awaiting_price') {
            // ... (logika yang relevan dipindahkan ke sini jika diperlukan)
            // Untuk saat ini, biarkan kosong karena logika utama ada di command
        }
    }

    /**
     * Menangani perintah `/register_channel` untuk mendaftarkan channel jualan.
     */
    private function handleRegisterChannelCommand(App $app, array $message, array $parts)
    {
        $sales_channel_repo = new SellerSalesChannelRepository($app->pdo);

        if (count($parts) < 2) {
            $app->telegram_api->sendMessage($app->chat_id, "Format perintah salah. Gunakan: `/register_channel <ID atau @username channel>`");
            return;
        }

        $channel_identifier = $parts[1];

        if (empty($app->user['public_seller_id'])) {
            $app->telegram_api->sendMessage($app->chat_id, "⚠️ Perintah ini hanya untuk penjual terdaftar.");
            return;
        }

        $bot_member = $app->telegram_api->getChatMember($channel_identifier, $app->telegram_api->getBotId());

        if (!$bot_member || !$bot_member['ok'] || !in_array($bot_member['result']['status'], ['administrator', 'creator'])) {
            $app->telegram_api->sendMessage($app->chat_id, "⚠️ Pendaftaran gagal: Pastikan bot telah ditambahkan sebagai *admin* di channel `{$channel_identifier}`.", 'Markdown');
            return;
        }

        if (!($bot_member['result']['can_post_messages'] ?? false)) {
             $app->telegram_api->sendMessage($app->chat_id, "⚠️ Pendaftaran gagal: Bot memerlukan izin untuk *'Post Messages'* di channel tersebut.", 'Markdown');
            return;
        }

        $channel_info = $app->telegram_api->getChat($channel_identifier);
        if (!$channel_info || !$channel_info['ok']) {
            $app->telegram_api->sendMessage($app->chat_id, "⚠️ Pendaftaran gagal: Tidak dapat mengambil informasi untuk channel `{$channel_identifier}`. Pastikan ID atau username channel sudah benar.", 'Markdown');
            return;
        }
        $numeric_channel_id = $channel_info['result']['id'];
        $channel_title = $channel_info['result']['title'];

        $success = $sales_channel_repo->createOrUpdate($app->user['telegram_id'], $numeric_channel_id);

        if ($success) {
            $escaped_title = $app->telegram_api->escapeMarkdown($channel_title);
            $app->telegram_api->sendMessage($app->chat_id, "✅ Selamat! Channel *{$escaped_title}* (`{$numeric_channel_id}`) telah berhasil didaftarkan sebagai channel jualan Anda.", 'Markdown');
        } else {
            $app->telegram_api->sendMessage($app->chat_id, "⚠️ Terjadi kesalahan database saat mencoba mendaftarkan channel ini.");
        }
    }

    /**
     * Menangani perintah `/me` untuk menampilkan ringkasan profil pengguna.
     */
    private function handleMeCommand(App $app, array $message)
    {
        $analytics_repo = new AnalyticsRepository($app->pdo);
        $user_id = $app->user['telegram_id'];
        $sales_stats = $analytics_repo->getSellerSummary($user_id);

        $user_name = $app->telegram_api->escapeMarkdown(trim($app->user['first_name'] . ' ' . ($app->user['last_name'] ?? '')));
        $balance = "Rp " . number_format($app->user['balance'], 0, ',', '.');
        $seller_id = $app->user['public_seller_id'] ? "`" . $app->user['public_seller_id'] . "`" : "Belum terdaftar";

        $total_sales = $sales_stats['total_sales'];
        $total_revenue = "Rp " . number_format($sales_stats['total_revenue'], 0, ',', '.');

        $response = "👤 *Profil Anda*\n\n";
        $response .= "Nama: *{$user_name}*\n";
        $response .= "Telegram ID: `{$app->user['telegram_id']}`\n";
        $response .= "ID Penjual: {$seller_id}\n\n";
        $response .= "💰 *Keuangan*\n";
        $response .= "Saldo Saat Ini: *{$balance}*\n\n";
        $response .= "📈 *Aktivitas Penjualan*\n";
        $response .= "Total Item Terjual: *{$total_sales}* item\n";
        $response .= "Total Pendapatan: *{$total_revenue}*";

        $app->telegram_api->sendMessage($app->chat_id, $response, 'Markdown');
    }

    /**
     * Menangani perintah `/help` untuk menampilkan pesan bantuan.
     */
    private function handleHelpCommand(App $app, array $message)
    {
        $help_text = <<<EOT
*🤖 Panduan Perintah Bot 🤖*

Berikut adalah perintah utama yang bisa Anda gunakan:

*--- UNTUK PENJUAL ---*
➡️ `/sell`
Balas (reply) sebuah media (foto/video/album) dengan perintah ini untuk mulai menjual.
➡️ `/addmedia`
Gunakan saat proses `/sell` untuk menambahkan lebih banyak media ke dalam satu paket.
➡️ `/addmedia <ID_PAKET>`
Gunakan sambil me-reply media baru untuk menambahkan media tersebut ke paket yang sudah ada.
➡️ `/register_channel <ID_CHANNEL>`
Daftarkan channel jualan Anda. Bot harus menjadi admin di channel tersebut.

*--- UNTUK SEMUA PENGGUNA ---*
➡️ `/konten <ID_PAKET>`
Lihat detail atau beli sebuah konten.
➡️ `/me`
Lihat profil, ID penjual, dan ringkasan penjualan Anda.
➡️ `/balance`
Cek saldo Anda saat ini.
➡️ `/login`
Dapatkan tautan unik untuk masuk ke panel member di web.
EOT;

        if ($app->user['role'] === 'Admin') {
            $admin_help_text = <<<EOT


*--- KHUSUS ADMIN ---*
➡️ `/dev_addsaldo <user_id> <jumlah>`
Menambah saldo ke pengguna.
➡️ `/feature <package_id> <channel_id>`
Mempromosikan paket ke channel.
EOT;
            $help_text .= $admin_help_text;
        }

        $app->telegram_api->sendLongMessage($app->chat_id, $help_text, 'Markdown');
    }

    /**
     * Menangani perintah `/start`.
     */
    private function handleStartCommand(App $app, array $message, array $parts)
    {
        $package_repo = new PackageRepository($app->pdo);
        $sale_repo = new SaleRepository($app->pdo);
        $sales_channel_repo = new SellerSalesChannelRepository($app->pdo);

        if (count($parts) > 1 && strpos($parts[1], 'package_') === 0) {
            $public_id = substr($parts[1], strlen('package_'));
            $package = $package_repo->findByPublicId($public_id);

            if (!$package) {
                $app->telegram_api->sendMessage($app->chat_id, "Maaf, item ini tidak ditemukan.");
                return;
            }

            $package_id = $package['id'];
            $telegram_user_id = $app->user['telegram_id'];

            $is_seller = ($package['seller_user_id'] == $telegram_user_id);
            $has_purchased = $sale_repo->hasUserPurchased($package_id, $telegram_user_id);

            if ($is_seller) {
                $response = "Anda adalah pemilik konten ini. Anda dapat melihat atau mem-postingnya ke channel.";
                $keyboard_buttons = [[['text' => 'Lihat Selengkapnya 📂', 'callback_data' => "view_page_{$public_id}_0"]]];
                $sales_channel = $sales_channel_repo->findBySellerId($telegram_user_id);
                if ($sales_channel) {
                    $keyboard_buttons[0][] = ['text' => '📢 Post ke Channel', 'callback_data' => "post_channel_{$public_id}"];
                }
                $keyboard = ['inline_keyboard' => $keyboard_buttons];
                $app->telegram_api->sendMessage($app->chat_id, $response, 'Markdown', json_encode($keyboard));
                return;
            }

            if ($has_purchased) {
                $response = "Anda sudah memiliki item ini. Klik tombol di bawah untuk melihatnya.";
                $keyboard = ['inline_keyboard' => [[['text' => 'Lihat Konten 📂', 'callback_data' => "view_page_{$public_id}_0"]]]];
                $app->telegram_api->sendMessage($app->chat_id, $response, 'Markdown', json_encode($keyboard));
                return;
            }

            if ($package['status'] === 'available') {
                $price_formatted = "Rp " . number_format($package['price'], 0, ',', '.');
                $balance_formatted = "Rp " . number_format($app->user['balance'], 0, ',', '.');
                $escaped_description = $app->telegram_api->escapeMarkdown($package['description']);
                $caption = "Anda tertarik dengan item berikut:\n\n*Deskripsi:* {$escaped_description}\n*Harga:* {$price_formatted}\n\nSaldo Anda saat ini: {$balance_formatted}.";
                $keyboard = ['inline_keyboard' => [[['text' => "Beli Sekarang ({$price_formatted})", 'callback_data' => "buy_{$public_id}"]]]];
                $reply_markup = json_encode($keyboard);

                $thumbnail = $package_repo->getThumbnailFile($package_id);

                if ($thumbnail && !empty($thumbnail['storage_channel_id']) && !empty($thumbnail['storage_message_id'])) {
                    $app->telegram_api->copyMessage($app->chat_id, $thumbnail['storage_channel_id'], $thumbnail['storage_message_id'], $caption, 'Markdown', $reply_markup);
                } else {
                    $app->telegram_api->sendMessage($app->chat_id, $caption, 'Markdown', $reply_markup);
                }
                return;
            }

            $app->telegram_api->sendMessage($app->chat_id, "Maaf, item ini sudah tidak tersedia.");

        } else {
            $welcome_message = "👋 *Selamat Datang di Bot Marketplace!* 🤖\n\n" .
                               "Gunakan perintah `/help` untuk melihat daftar perintah yang tersedia.";
            $app->telegram_api->sendMessage($app->chat_id, $welcome_message, 'Markdown');
        }
    }

    /**
     * Menangani perintah `/sell`.
     */
    private function handleSellCommand(App $app, array $message)
    {
        $user_repo = new UserRepository($app->pdo, $app->bot['telegram_bot_id']);

        if (!isset($message['reply_to_message'])) {
            $app->telegram_api->sendMessage($app->chat_id, "Untuk menjual, silakan reply media yang ingin Anda jual dengan perintah /sell.");
            return;
        }

        if (empty($app->user['public_seller_id'])) {
            $text = "Anda belum terdaftar sebagai penjual. Apakah Anda ingin mendaftar sekarang?\n\nDengan mendaftar, Anda akan mendapatkan ID Penjual unik.";
            $keyboard = ['inline_keyboard' => [[['text' => "Ya, Daftar Sekarang", 'callback_data' => "register_seller"]]]];
            $app->telegram_api->sendMessage($app->chat_id, $text, null, json_encode($keyboard));
            return;
        }

        $replied_message = $message['reply_to_message'];

        $stmt_check_media = $app->pdo->prepare("SELECT COUNT(*) FROM media_files WHERE message_id = ? AND chat_id = ?");
        $stmt_check_media->execute([$replied_message['message_id'], $replied_message['chat']['id']]);
        if ($stmt_check_media->fetchColumn() == 0) {
             $app->telegram_api->sendMessage($app->chat_id, "⚠️ Gagal. Pastikan Anda me-reply pesan media (foto/video) yang sudah tersimpan di bot.");
             return;
        }

        $stmt_media_info = $app->pdo->prepare("SELECT media_group_id, caption FROM media_files WHERE message_id = ? AND chat_id = ?");
        $stmt_media_info->execute([$replied_message['message_id'], $replied_message['chat']['id']]);
        $media_info = $stmt_media_info->fetch(PDO::FETCH_ASSOC);

        $media_group_id = $media_info['media_group_id'] ?? null;
        $description = $media_info['caption'] ?? '';

        if ($media_group_id) {
            $stmt_caption = $app->pdo->prepare("SELECT caption FROM media_files WHERE media_group_id = ? AND caption IS NOT NULL AND caption != '' LIMIT 1");
            $stmt_caption->execute([$media_group_id]);
            $group_caption = $stmt_caption->fetchColumn();
            if ($group_caption) {
                $description = $group_caption;
            }
        }

        $state_context = [
            'media_messages' => [['message_id' => $replied_message['message_id'], 'chat_id' => $replied_message['chat']['id']]]
        ];
        $user_repo->setUserState($app->user['telegram_id'], 'awaiting_price', $state_context);

        $message_text = "✅ Media telah siap untuk dijual.\n\n";
        if (!empty($description)) {
            $message_text .= "Deskripsi: *\"" . $app->telegram_api->escapeMarkdown($description) . "\"*\n";
        }
        $message_text .= "Sekarang, silakan masukkan harga untuk paket ini (contoh: 50000).\n\n";
        $message_text .= "_Ketik /cancel untuk membatalkan._";

        $app->telegram_api->sendMessage($app->chat_id, $message_text, 'Markdown');
    }

    /**
     * Menangani perintah `/konten`.
     */
    private function handleKontenCommand(App $app, array $message, array $parts)
    {
        $package_repo = new PackageRepository($app->pdo);
        $sale_repo = new SaleRepository($app->pdo);
        $sales_channel_repo = new SellerSalesChannelRepository($app->pdo);

        if (count($parts) !== 2) {
            $app->telegram_api->sendMessage($app->chat_id, "Format perintah salah. Gunakan: /konten <ID Konten>");
            return;
        }

        $public_id = $parts[1];
        $package = $package_repo->findByPublicId($public_id);

        if (!$package) {
            $app->telegram_api->sendMessage($app->chat_id, "Konten dengan ID `{$public_id}` tidak ditemukan.", 'Markdown');
            return;
        }
        $package_id = $package['id'];

        $thumbnail = $package_repo->getThumbnailFile($package_id);

        if (!$thumbnail) {
            $app->telegram_api->sendMessage($app->chat_id, "Konten ini tidak memiliki media yang dapat ditampilkan.");
            return;
        }

        $is_admin = ($app->user['role'] === 'Admin');
        $is_seller = ($package['seller_user_id'] == $app->user['telegram_id']);
        $has_purchased = $sale_repo->hasUserPurchased($package_id, $app->user['telegram_id']);
        $has_access = $is_admin || $is_seller || $has_purchased;

        $keyboard = [];
        if ($has_access) {
            $keyboard_buttons = [[['text' => 'Lihat Selengkapnya 📂', 'callback_data' => "view_page_{$package['public_id']}_0"]]];
            if ($is_seller) {
                $sales_channel = $sales_channel_repo->findBySellerId($app->user['telegram_id']);
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

        $app->telegram_api->copyMessage($app->chat_id, $thumbnail['storage_channel_id'], $thumbnail['storage_message_id'], $caption, null, $reply_markup);
    }

    /**
     * Menangani perintah `/balance`.
     */
    private function handleBalanceCommand(App $app, array $message)
    {
        $balance = "Rp " . number_format($app->user['balance'], 2, ',', '.');
        $app->telegram_api->sendMessage($app->chat_id, "Saldo Anda saat ini: {$balance}");
    }

    /**
     * Menangani perintah `/login`.
     */
    private function handleLoginCommand(App $app, array $message)
    {
        if (!defined('BASE_URL') || empty(BASE_URL)) {
            $app->telegram_api->sendMessage($app->chat_id, "Maaf, terjadi kesalahan teknis (ERR:CFG01).");
            return;
        }

        $login_token = bin2hex(random_bytes(32));
        // Setelah migrasi 035, members.user_id merujuk ke users.telegram_id.
        $app->pdo->prepare("UPDATE members SET login_token = ?, token_created_at = NOW(), token_used = 0 WHERE user_id = ?")
             ->execute([$login_token, $app->user['telegram_id']]);

        if ($app->user['role'] === 'Admin') {
            $login_link = rtrim(BASE_URL, '/') . '/login_choice.php?token=' . $login_token;
            $response = "Anda adalah seorang Admin. Silakan pilih panel yang ingin Anda masuki.";
            $keyboard = ['inline_keyboard' => [[['text' => 'Pilih Panel Login', 'url' => $login_link]]]];
            $app->telegram_api->sendMessage($app->chat_id, $response, null, json_encode($keyboard));
        } else {
            $login_link = rtrim(BASE_URL, '/') . '/member/index.php?token=' . $login_token;
            $response = "Klik tombol di bawah ini untuk masuk ke Panel Member Anda. Tombol ini hanya dapat digunakan satu kali.";
            $keyboard = ['inline_keyboard' => [[['text' => 'Login ke Panel Member', 'url' => $login_link]]]];
            $app->telegram_api->sendMessage($app->chat_id, $response, null, json_encode($keyboard));
        }
    }

    /**
     * Menangani perintah khusus admin.
     */
    private function handleAdminCommands(App $app, array $message, string $command, array $parts)
    {
        if ($app->user['role'] !== 'Admin') {
            return;
        }
        // Logika untuk perintah admin
    }

    /**
     * Router untuk perintah `/addmedia`.
     */
    private function handleAddMediaCommand(App $app, array $message)
    {
        $parts = explode(' ', $message['text']);
        if (count($parts) > 1) {
            $this->addMediaToExistingPackage($app, $message, $parts[1]);
        } else {
            $this->addMediaToNewPackage($app, $message);
        }
    }

    /**
     * Menambahkan media ke paket baru yang sedang dibuat.
     */
    private function addMediaToNewPackage(App $app, array $message)
    {
        $user_repo = new UserRepository($app->pdo, $app->bot['telegram_bot_id']);

        if ($app->user['state'] !== 'awaiting_price') {
            $app->telegram_api->sendMessage($app->chat_id, "⚠️ Perintah ini hanya bisa digunakan saat Anda sedang dalam proses menjual item.");
            return;
        }
        if (!isset($message['reply_to_message'])) {
            $app->telegram_api->sendMessage($app->chat_id, "Untuk menambah media, silakan reply media yang ingin Anda tambahkan.");
            return;
        }

        $replied_message = $message['reply_to_message'];
        // ... (sisa logika)
    }

    /**
     * Menambahkan media ke paket yang sudah ada.
     */
    private function addMediaToExistingPackage(App $app, array $message, $public_package_id)
    {
        $package_repo = new PackageRepository($app->pdo);

        if (!isset($message['reply_to_message'])) {
            $app->telegram_api->sendMessage($app->chat_id, "Untuk menambah media, silakan reply media yang ingin Anda tambahkan.");
            return;
        }

        $package = $package_repo->findByPublicId($public_package_id);
        if (!$package || $package['seller_user_id'] != $app->user['telegram_id']) {
            $app->telegram_api->sendMessage($app->chat_id, "⚠️ Anda tidak memiliki izin untuk mengubah paket ini.");
            return;
        }

        // ... (sisa logika)
    }

    /**
     * Menangani pesan yang di-forward otomatis.
     */
    private function handleAutomaticForward(App $app, array $message)
    {
        $post_package_repo = new ChannelPostPackageRepository($app->pdo);
        $forward_origin = $message['forward_origin'] ?? null;

        if (!$forward_origin) return;

        $original_channel_id = $forward_origin['chat']['id'] ?? null;
        $original_message_id = $forward_origin['message_id'] ?? null;

        if (!$original_channel_id || !$original_message_id) return;

        $package = $post_package_repo->findByChannelAndMessage($original_channel_id, $original_message_id);

        if (!$package || $package['status'] !== 'available') return;

        $start_payload = "package_{$package['public_id']}";
        $bot_username = BOT_USERNAME; // Asumsi konstanta ini tersedia
        $url = "https://t.me/{$bot_username}?start={$start_payload}";

        $keyboard = ['inline_keyboard' => [[['text' => 'Beli Sekarang', 'url' => $url]]]];
        $reply_markup = json_encode($keyboard);
        $reply_parameters = ['message_id' => $message['message_id']];
        $reply_text = "Klik tombol di bawah untuk membeli";

        $app->telegram_api->sendMessage($app->chat_id, $reply_text, null, $reply_markup, null, $reply_parameters);
    }
}
