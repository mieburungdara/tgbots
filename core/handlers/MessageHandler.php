<?php

/**
 * This file is part of the TGBot package.
 *
 * (c) Zidin Mitra Abadi <zidinmitra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TGBot\Handlers;

use PDO;
use TGBot\App;
use TGBot\Handlers\HandlerInterface;
use TGBot\Database\MediaPackageRepository;
use TGBot\Database\SaleRepository;
use TGBot\Database\MediaFileRepository;
use TGBot\Database\BotChannelUsageRepository;
use TGBot\Database\AnalyticsRepository;
use TGBot\Database\BotRepository;
use TGBot\Database\ChannelPostPackageRepository;
use TGBot\Database\FeatureChannelRepository;
use TGBot\Database\UserRepository;

/**
 * Class MessageHandler
 * @package TGBot\Handlers
 */
class MessageHandler implements HandlerInterface
{
    /**
     * Handle a message.
     *
     * @param App $app
     * @param array $message
     * @return void
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
        $command_full = $parts[0];
        // Menangani perintah yang mungkin menyertakan nama bot (misal: /start@nama_bot)
        $command_parts = explode('@', $command_full);
        $command = $command_parts[0];

        // Ini dapat direfaktor lebih lanjut menjadi kelas-kelas perintah
        switch ($command) {
            case '/start':
                $this->handleStartCommand($app, $message, $parts);
                break;
            case '/sell':
                $this->handleSellCommand($app, $message);
                break;
            case '/rate':
                $this->handleRateCommand($app, $message);
                break;
            case '/tanya':
                $this->handleTanyaCommand($app, $message);
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
            case '/dev_addsaldo':
            case '/feature':
                $this->handleAdminCommands($app, $message, $command, $parts);
                break;
        }
    }

    /**
     * Handle the state machine.
     *
     * @param App $app
     * @param array $message
     * @return void
     */
    private function handleState(App $app, array $message): void
    {
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);
        $text = $message['text'];
        $state_context = json_decode($app->user['state_context'] ?? '{}', true);

        if (strpos($text, '/cancel') === 0) {
            $user_repo->setUserState($app->user['id'], null, null);
            $app->telegram_api->sendMessage($app->chat_id, "Operasi dibatalkan.");
            return;
        }

        // Contoh: Logika untuk state 'awaiting_price'
        if ($app->user['state'] === 'awaiting_price') {
            $price = filter_var($text, FILTER_VALIDATE_INT);
            if ($price === false || $price <= 0) {
                $app->telegram_api->sendMessage($app->chat_id, "Harga tidak valid. Harap masukkan angka bulat positif.");
                return;
            }

            $post_repo = new MediaPackageRepository($app->pdo);

            $media_message = $state_context['media_messages'][0];
            $message_id = $media_message['message_id'];
            $chat_id = $media_message['chat_id'];

            $stmt = $app->pdo->prepare("SELECT * FROM media_files WHERE message_id = ? AND chat_id = ?");
            $stmt->execute([$message_id, $chat_id]);
            $media_file = $stmt->fetch();

            $package_id = $post_repo->createPackageWithPublicId(
                $app->user['id'],
                $app->bot['id'],
                $media_file['caption'] ?? '',
                $media_file['id'],
                'sell'
            );

            $post_repo->updatePrice($package_id, $price);

            if ($media_file['media_group_id']) {
                $stmt = $app->pdo->prepare("UPDATE media_files SET package_id = ? WHERE media_group_id = ?");
                $stmt->execute([$package_id, $media_file['media_group_id']]);
            } else {
                $stmt = $app->pdo->prepare("UPDATE media_files SET package_id = ? WHERE id = ?");
                $stmt->execute([$package_id, $media_file['id']]);
            }

            $post = $post_repo->find($package_id);
            $public_id = $post['public_id'];

            $user_repo->setUserState($app->user['id'], null, null);

            $message_text = "✅ Paket berhasil dibuat dengan ID: `{$public_id}`\n";
            $message_text .= "Harga: *Rp " . number_format($price, 0, ',', '.') . "*\n\n";
            $message_text .= "Anda dapat melihat konten Anda dengan perintah `/konten {$public_id}`.";

            $app->telegram_api->sendMessage($app->chat_id, $message_text, 'Markdown');
        }
    }

    /**
     * Handle the /me command.
     *
     * @param App $app
     * @param array $message
     * @return void
     */
    private function handleMeCommand(App $app, array $message): void
    {
        $analytics_repo = new AnalyticsRepository($app->pdo);
        $user_id = $app->user['id'];
        $sales_stats = $analytics_repo->getSellerSummary($user_id);

        $user_name = $app->telegram_api->escapeMarkdown(trim($app->user['first_name'] . ' ' . ($app->user['last_name'] ?? '')));
        $balance = "Rp " . number_format($app->user['balance'], 0, ',', '.');
        $seller_id = $app->user['public_seller_id'] ? "`" . $app->user['public_seller_id'] . "`" : "Belum terdaftar";

        $total_sales = $sales_stats['total_sales'];
        $total_revenue = "Rp " . number_format($sales_stats['total_revenue'], 0, ',', '.');

        $response = "👤 *Profil Anda*\n\n";
        $response .= "Nama: *{$user_name}*\n";
        $response .= "Telegram ID: `{$app->user['id']}`\n";
        $response .= "ID Penjual: {$seller_id}\n\n";
        $response .= "💰 *Keuangan*\n";
        $response .= "Saldo Saat Ini: *{$balance}*\n\n";
        $response .= "📈 *Aktivitas Penjualan*\n";
        $response .= "Total Item Terjual: *{$total_sales}* item\n";
        $response .= "Total Pendapatan: *{$total_revenue}*";

        $app->telegram_api->sendMessage($app->chat_id, $response, 'Markdown');
    }

    /**
     * Handle the /help command.
     *
     * @param App $app
     * @param array $message
     * @return void
     */
    private function handleHelpCommand(App $app, array $message): void
    {
        $feature = $app->bot['assigned_feature'] ?? 'general';

        $help_text = "*🤖 Panduan Perintah Bot 🤖*\n\n";

        switch ($feature) {
            case 'sell':
                $help_text .= "*--- FITUR JUAL BELI ---*\n";
                $help_text .= "➡️ `/sell`\nBalas (reply) media untuk mulai menjual.\n";
                $help_text .= "➡️ `/addmedia`\nTambah media saat proses `/sell`.\n";
                $help_text .= "➡️ `/addmedia <ID_PAKET>`\nTambah media ke paket yang sudah ada.\n\n";
                $help_text .= "*--- PERINTAH UMUM ---*\n";
                $help_text .= "➡️ `/konten <ID_PAKET>`\nLihat detail atau beli konten.\n";
                $help_text .= "➡️ `/me`\nLihat profil dan ringkasan penjualan.\n";
                $help_text .= "➡️ `/balance`\nCek saldo Anda.\n";
                $help_text .= "➡️ `/login`\nMasuk ke panel member web.\n";
                break;

            case 'rate':
                $help_text .= "*--- FITUR RATING ---*\n";
                $help_text .= "➡️ `/rate`\nBalas (reply) media untuk memberi rating.\n\n";
                $help_text .= "*--- PERINTAH UMUM ---*\n";
                $help_text .= "➡️ `/me`\nLihat profil Anda.\n";
                $help_text .= "➡️ `/login`\nMasuk ke panel member web.\n";
                break;

            case 'tanya':
                $help_text .= "*--- FITUR TANYA ---*\n";
                $help_text .= "➡️ `/tanya`\nBalas (reply) pesan untuk bertanya.\n\n";
                $help_text .= "*--- PERINTAH UMUM ---*\n";
                $help_text .= "➡️ `/me`\nLihat profil Anda.\n";
                $help_text .= "➡️ `/login`\nMasuk ke panel member web.\n";
                break;

            default: // General or null feature
                $help_text .= "Berikut adalah perintah utama yang bisa Anda gunakan:\n\n";
                $help_text .= "➡️ `/me`\nLihat profil Anda.\n";
                $help_text .= "➡️ `/balance`\nCek saldo Anda.\n";
                $help_text .= "➡️ `/login`\nMasuk ke panel member web.\n";
                break;
        }

        if ($app->user['role'] === 'Admin') {
            $help_text .= "\n*--- KHUSUS ADMIN ---*\n";
            $help_text .= "➡️ `/dev_addsaldo <user_id> <jumlah>`\nMenambah saldo pengguna.\n";
            $help_text .= "➡️ `/feature <package_id> <channel_id>`\nMempromosikan paket ke channel.\n";
        }

        $app->telegram_api->sendLongMessage($app->chat_id, $help_text, 'Markdown');
    }

    /**
     * Handle the /start command.
     *
     * @param App $app
     * @param array $message
     * @param array $parts
     * @return void
     */
    private function handleStartCommand(App $app, array $message, array $parts): void
    {
        $package_repo = new MediaPackageRepository($app->pdo);
        $sale_repo = new SaleRepository($app->pdo);
        $feature_channel_repo = new FeatureChannelRepository($app->pdo);

        if (count($parts) > 1 && strpos($parts[1], 'package_') === 0) {
            $public_id = substr($parts[1], strlen('package_'));
            $package = $package_repo->findByPublicId($public_id);

            if (!$package) {
                $app->telegram_api->sendMessage($app->chat_id, "Maaf, item ini tidak ditemukan.");
                return;
            }

            $package_id = $package['id'];
            $telegram_user_id = $app->user['id'];

            $is_seller = ($package['seller_user_id'] == $telegram_user_id);
            $has_purchased = $sale_repo->hasUserPurchased($package_id, $telegram_user_id);

            if ($is_seller) {
                $response = "Anda adalah pemilik konten ini. Anda dapat melihat atau mem-postingnya ke channel.";
                $keyboard_buttons = [[['text' => 'Lihat Selengkapnya 📂', 'callback_data' => "view_page_{$public_id}_0"]]];
                $sales_channels = $feature_channel_repo->findAllByOwnerAndFeature($telegram_user_id, 'sell');
                if (!empty($sales_channels)) {
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
     * Handle the /sell command.
     *
     * @param App $app
     * @param array $message
     * @return void
     */
    private function handleSellCommand(App $app, array $message): void
    {
        if (isset($app->bot['assigned_feature']) && $app->bot['assigned_feature'] !== 'sell') {
            $bot_repo = new BotRepository($app->pdo);
            $correct_bots = $bot_repo->findAllBotsByFeature('sell');
            $suggestion = "";
            if (!empty($correct_bots)) {
                $suggestion = "\n\nFitur ini tersedia di bot berikut:\n";
                foreach ($correct_bots as $bot) {
                    $suggestion .= "- @" . $bot['username'] . "\n";
                }
            }
            $app->telegram_api->sendMessage($app->chat_id, "Perintah `/sell` tidak tersedia di bot ini." . $suggestion);
            return;
        }

        $user_repo = new UserRepository($app->pdo, $app->bot['id']);

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
        $user_repo->setUserState($app->user['id'], 'awaiting_price', $state_context);

        $message_text = "✅ Media telah siap untuk dijual.\n\n";
        if (!empty($description)) {
            $message_text .= "Deskripsi: *\"" . $app->telegram_api->escapeMarkdown($description) . "\"*\n";
        }
        $message_text .= "Sekarang, silakan masukkan harga untuk paket ini (contoh: 50000).\n\n";
        $message_text .= "_Ketik /cancel untuk membatalkan._";

        $app->telegram_api->sendMessage($app->chat_id, $message_text, 'Markdown');
    }

    /**
     * Handle the /konten command.
     *
     * @param App $app
     * @param array $message
     * @param array $parts
     * @return void
     */
    private function handleKontenCommand(App $app, array $message, array $parts): void
    {
        $package_repo = new MediaPackageRepository($app->pdo);
        $sale_repo = new SaleRepository($app->pdo);
        $feature_channel_repo = new FeatureChannelRepository($app->pdo);

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

        // Pastikan thumbnail dan detail penyimpanannya valid sebelum melanjutkan.
        if (!$thumbnail || empty($thumbnail['storage_channel_id']) || empty($thumbnail['storage_message_id'])) {
            $app->telegram_api->sendMessage($app->chat_id, "Konten ini tidak memiliki media yang dapat ditampilkan atau data media rusak. Silakan hubungi admin.");
            \app_log("Gagal menampilkan konten: Thumbnail atau detail penyimpanan tidak valid untuk package_id: {$package_id}", 'warning', ['package' => $package, 'thumbnail' => $thumbnail]);
            return;
        }

        $is_admin = ($app->user['role'] === 'Admin');
        $is_seller = ($package['seller_user_id'] == $app->user['id']);
        $has_purchased = $sale_repo->hasUserPurchased($package_id, $app->user['id']);
        $has_access = $is_admin || $is_seller || $has_purchased;

        $keyboard = [];
        if ($has_access) {
            $keyboard_buttons = [[['text' => 'Lihat Selengkapnya 📂', 'callback_data' => "view_page_{$package['public_id']}_0"]]];
            if ($is_seller) {
                $sales_channels = $feature_channel_repo->findAllByOwnerAndFeature($app->user['id'], 'sell');
                if (!empty($sales_channels)) {
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
     * Handle the /balance command.
     *
     * @param App $app
     * @param array $message
     * @return void
     */
    private function handleBalanceCommand(App $app, array $message): void
    {
        $balance = "Rp " . number_format($app->user['balance'], 2, ',', '.');
        $app->telegram_api->sendMessage($app->chat_id, "Saldo Anda saat ini: {$balance}");
    }

    /**
     * Handle the /login command.
     *
     * @param App $app
     * @param array $message
     * @return void
     */
    private function handleLoginCommand(App $app, array $message): void
    {
        if (!defined('BASE_URL') || empty(BASE_URL)) {
            $app->telegram_api->sendMessage($app->chat_id, "Maaf, terjadi kesalahan teknis (ERR:CFG01).");
            return;
        }

        $login_token = bin2hex(random_bytes(32));
        $app->pdo->prepare("UPDATE users SET login_token = ?, token_created_at = NOW(), token_used = 0 WHERE id = ?")
             ->execute([$login_token, $app->user['id']]);

        $login_link = rtrim(BASE_URL, '/') . '/member/token-login?token=' . $login_token;
        $response = "Klik tombol di bawah ini untuk masuk ke Panel Anda. Tombol ini hanya dapat digunakan satu kali.";
        $keyboard = ['inline_keyboard' => [[['text' => 'Login ke Panel', 'url' => $login_link]]]];
        $app->telegram_api->sendMessage($app->chat_id, $response, null, json_encode($keyboard));
    }

    /**
     * Handle admin commands.
     *
     * @param App $app
     * @param array $message
     * @param string $command
     * @param array $parts
     * @return void
     */
    private function handleAdminCommands(App $app, array $message, string $command, array $parts): void
    {
        if ($app->user['role'] !== 'Admin') {
            return;
        }
        // Logika untuk perintah admin
    }

    private function handleRateCommand(App $app, array $message): void
    {
        if (isset($app->bot['assigned_feature']) && $app->bot['assigned_feature'] !== 'rate') {
            $bot_repo = new BotRepository($app->pdo);
            $correct_bots = $bot_repo->findAllBotsByFeature('rate');
            $suggestion = "";
            if (!empty($correct_bots)) {
                $suggestion = "\n\nFitur ini tersedia di bot berikut:\n";
                foreach ($correct_bots as $bot) {
                    $suggestion .= "- @" . $bot['username'] . "\n";
                }
            }
            $app->telegram_api->sendMessage($app->chat_id, "Perintah `/rate` tidak tersedia di bot ini." . $suggestion);
            return;
        }

        $user_repo = new UserRepository($app->pdo, $app->bot['id']);
        $post_repo = new MediaPackageRepository($app->pdo);

        if ($post_repo->hasPendingPost($app->user['id'])) {
            $app->telegram_api->sendMessage($app->chat_id, "Anda masih memiliki kiriman yang sedang dalam proses moderasi. Harap tunggu hingga selesai sebelum mengirim yang baru.");
            return;
        }

        if (!isset($message['reply_to_message'])) {
            $app->telegram_api->sendMessage($app->chat_id, "Untuk memberi rate, silakan reply media yang ingin Anda beri rate dengan perintah /rate.");
            return;
        }

        $replied_message = $message['reply_to_message'];

        // For now, let's just check if it's a media message
        if (!isset($replied_message['photo']) && !isset($replied_message['video'])) {
            $app->telegram_api->sendMessage($app->chat_id, "⚠️ Gagal. Pastikan Anda me-reply pesan media (foto/video).");
            return;
        }

        $state_context = [
            'message_id' => $replied_message['message_id'],
            'chat_id' => $replied_message['chat']['id'],
            'from_id' => $replied_message['from']['id'],
        ];

        // Check if user is replying to their own message
        if ($message['from']['id'] !== $replied_message['from']['id']) {
            $app->telegram_api->sendMessage($app->chat_id, "⚠️ Gagal. Anda hanya bisa memberi rate pada media milik Anda sendiri.");
            return;
        }

        $user_repo->setUserState($app->user['id'], 'awaiting_rate_category', $state_context);

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'Cewek', 'callback_data' => 'rate_category_cewek'],
                    ['text' => 'Cowok', 'callback_data' => 'rate_category_cowok'],
                ]
            ]
        ];

        $app->telegram_api->sendMessage(
            $app->chat_id,
            "Silakan pilih kategori:",
            null,
            json_encode($keyboard),
            $replied_message['message_id']
        );
    }

    private function handleTanyaCommand(App $app, array $message): void
    {
        if (isset($app->bot['assigned_feature']) && $app->bot['assigned_feature'] !== 'tanya') {
            $bot_repo = new BotRepository($app->pdo);
            $correct_bots = $bot_repo->findAllBotsByFeature('tanya');
            $suggestion = "";
            if (!empty($correct_bots)) {
                $suggestion = "\n\nFitur ini tersedia di bot berikut:\n";
                foreach ($correct_bots as $bot) {
                    $suggestion .= "- @" . $bot['username'] . "\n";
                }
            }
            $app->telegram_api->sendMessage($app->chat_id, "Perintah `/tanya` tidak tersedia di bot ini." . $suggestion);
            return;
        }

        $user_repo = new UserRepository($app->pdo, $app->bot['id']);
        $post_repo = new MediaPackageRepository($app->pdo);

        if ($post_repo->hasPendingPost($app->user['id'])) {
            $app->telegram_api->sendMessage($app->chat_id, "Anda masih memiliki kiriman yang sedang dalam proses moderasi. Harap tunggu hingga selesai sebelum mengirim yang baru.");
            return;
        }

        if (!isset($message['reply_to_message'])) {
            $app->telegram_api->sendMessage($app->chat_id, "Untuk bertanya, silakan reply pesan yang ingin Anda tanyakan dengan perintah /tanya.");
            return;
        }

        $replied_message = $message['reply_to_message'];

        $state_context = [
            'message_id' => $replied_message['message_id'],
            'chat_id' => $replied_message['chat']['id'],
            'from_id' => $replied_message['from']['id'],
            'text' => $replied_message['text'] ?? $replied_message['caption'] ?? ''
        ];

        // Check if user is replying to their own message
        if ($message['from']['id'] !== $replied_message['from']['id']) {
            $app->telegram_api->sendMessage($app->chat_id, "⚠️ Gagal. Anda hanya bisa bertanya pada pesan milik Anda sendiri.");
            return;
        }

        $user_repo->setUserState($app->user['id'], 'awaiting_tanya_category', $state_context);

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'Mutualan', 'callback_data' => 'tanya_category_mutualan'],
                    ['text' => 'Tanya', 'callback_data' => 'tanya_category_tanya'],
                    ['text' => 'Dll', 'callback_data' => 'tanya_category_dll'],
                ]
            ]
        ];

        $app->telegram_api->sendMessage(
            $app->chat_id,
            "Silakan pilih kategori:",
            null,
            json_encode($keyboard),
            $replied_message['message_id']
        );
    }

    /**
     * Handle the /addmedia command.
     *
     * @param App $app
     * @param array $message
     * @return void
     */
    private function handleAddMediaCommand(App $app, array $message): void
    {
        $parts = explode(' ', $message['text']);
        if (count($parts) > 1) {
            $this->addMediaToExistingPackage($app, $message, $parts[1]);
        } else {
            $this->addMediaToNewPackage($app, $message);
        }
    }

    /**
     * Add media to a new package.
     *
     * @param App $app
     * @param array $message
     * @return void
     */
    private function addMediaToNewPackage(App $app, array $message): void
    {
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);

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
     * Add media to an existing package.
     *
     * @param App $app
     * @param array $message
     * @param string $public_package_id
     * @return void
     */
    private function addMediaToExistingPackage(App $app, array $message, string $public_package_id): void
    {
        $package_repo = new MediaPackageRepository($app->pdo);

        if (!isset($message['reply_to_message'])) {
            $app->telegram_api->sendMessage($app->chat_id, "Untuk menambah media, silakan reply media yang ingin Anda tambahkan.");
            return;
        }

        $package = $package_repo->findByPublicId($public_package_id);
        if (!$package || $package['seller_user_id'] != $app->user['id']) {
            $app->telegram_api->sendMessage($app->chat_id, "⚠️ Anda tidak memiliki izin untuk mengubah paket ini.");
            return;
        }

        // ... (sisa logika)
    }

    /**
     * Handle automatic forwards.
     *
     * @param App $app
     * @param array $message
     * @return void
     */
    private function handleAutomaticForward(App $app, array $message): void
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
