<?php

require_once __DIR__ . '/../database/PackageRepository.php';
require_once __DIR__ . '/../database/SaleRepository.php';
require_once __DIR__ . '/../database/MediaFileRepository.php';
require_once __DIR__ . '/../database/BotChannelUsageRepository.php';

class MessageHandler
{
    private $pdo;
    private $telegram_api;
    private $user_repo;
    private $package_repo;
    private $media_repo;
    private $bot_channel_usage_repo;
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
        $this->current_user = $current_user;
        $this->chat_id = $chat_id;
        $this->message = $message;
    }

    public function handle()
    {
        if (!isset($this->message['text'])) {
            return;
        }

        $text = $this->message['text'];

        // Command routing
        if (strpos($text, '/') !== 0) {
            return; // Not a command, ignore
        }

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
            case '/konten':
                $this->handleKontenCommand($parts);
                break;
            case '/balance':
                $this->handleBalanceCommand();
                break;
            case '/login':
                $this->handleLoginCommand();
                break;
            case '/dev_addsaldo':
            case '/feature':
                $this->handleAdminCommands($command, $parts);
                break;
        }
    }

    private function handleStartCommand(array $parts)
    {
        if (count($parts) > 1 && strpos($parts[1], 'package_') === 0) {
            $package_id = substr($parts[1], strlen('package_'));
            $package = $this->package_repo->find($package_id);
            if ($package && $package['status'] == 'available') {
                $price_formatted = "Rp " . number_format($package['price'], 0, ',', '.');
                $balance_formatted = "Rp " . number_format($this->current_user['balance'], 0, ',', '.');
                $reply_text = "Anda tertarik dengan item berikut:\n\n*Deskripsi:* {$package['description']}\n*Harga:* {$price_formatted}\n\nSaldo Anda saat ini: {$balance_formatted}.";
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
        $state_context = [
            'reply_to_message_id' => $replied_message['message_id'],
            'reply_to_chat_id' => $replied_message['chat']['id']
        ];
        $this->user_repo->setUserState($this->current_user['id'], 'awaiting_price', $state_context);

        // Buat dan kirim pesan yang informatif
        $message_text = "✅ Media telah siap untuk dijual.\n\n";
        if (!empty($description)) {
            $description_escaped = str_replace(['*', '_', '`', '['], ['\*', '\_', '\`', '\['], $description);
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
            $stmt_thumb = $this->pdo->prepare("SELECT file_id, type, chat_id, message_id FROM media_files WHERE id = ?");
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
        $has_purchased = $this->sale_repo->hasUserPurchased($package_id, $internal_user_id);

        $has_access = $is_admin || $is_seller || $has_purchased;

        $keyboard = [];
        if ($has_access) {
            $keyboard = ['inline_keyboard' => [[['text' => 'Lihat Selengkapnya 📂', 'callback_data' => "view_full_{$package['public_id']}"]]]];
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
}
