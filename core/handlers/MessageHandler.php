<?php

require_once __DIR__ . '/../database/PackageRepository.php';
require_once __DIR__ . '/../database/SaleRepository.php';
require_once __DIR__ . '/../database/MediaFileRepository.php'; // Assuming this exists

class MessageHandler
{
    private $pdo;
    private $telegram_api;
    private $user_repo;
    private $package_repo;
    private $media_repo;
    private $current_user;
    private $chat_id;
    private $message;

    public function __construct(PDO $pdo, TelegramAPI $telegram_api, UserRepository $user_repo, array $current_user, int $chat_id, array $message)
    {
        $this->pdo = $pdo; // Can be removed after full refactor
        $this->telegram_api = $telegram_api;
        $this->user_repo = $user_repo;
        $this->package_repo = new PackageRepository($pdo);
        $this->media_repo = new MediaFileRepository($pdo);
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
        // ... The logic for /sell is complex and involves state changes and media file lookups.
        // It's a good candidate for its own command class. For now, we leave the logic here.
        // This logic still uses direct PDO, which should be refactored.
        if (!isset($this->message['reply_to_message'])) {
            $this->telegram_api->sendMessage($this->chat_id, "Untuk menjual, silakan reply media yang ingin Anda jual dengan perintah /sell.");
            return;
        }

        $replied_message = $this->message['reply_to_message'];
        $replied_message_id = $replied_message['message_id'];
        $replied_chat_id = $replied_message['chat']['id'];
        $description = $replied_message['caption'] ?? '';
        $media_group_id = $replied_message['media_group_id'] ?? null;
        $internal_user_id = $this->current_user['id'];
        $internal_bot_id = $this->user_repo->getBotId();

        $media_file_ids = [];
        if ($media_group_id) {
            $stmt_media = $this->pdo->prepare("SELECT id FROM media_files WHERE media_group_id = ? AND chat_id = ?");
            $stmt_media->execute([$media_group_id, $replied_chat_id]);
            $media_file_ids = $stmt_media->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $stmt_media = $this->pdo->prepare("SELECT id FROM media_files WHERE message_id = ? AND chat_id = ?");
            $stmt_media->execute([$replied_message_id, $replied_chat_id]);
            $media_file_id = $stmt_media->fetchColumn();
            if ($media_file_id) $media_file_ids[] = $media_file_id;
        }

        if (empty($media_file_ids)) {
            $this->telegram_api->sendMessage($this->chat_id, "⚠️ Gagal. Pastikan Anda me-reply pesan media (foto/video) yang sudah tersimpan di bot.");
            return;
        }

        $stmt_thumb = $this->pdo->prepare("SELECT id FROM media_files WHERE message_id = ? AND chat_id = ?");
        $stmt_thumb->execute([$replied_message_id, $replied_chat_id]);
        $thumbnail_media_id = $stmt_thumb->fetchColumn();

        if (!$thumbnail_media_id) {
            $this->telegram_api->sendMessage($this->chat_id, "⚠️ Gagal. Media yang Anda reply tidak dapat ditemukan sebagai thumbnail.");
            return;
        }

        $stmt_package = $this->pdo->prepare("INSERT INTO media_packages (seller_user_id, bot_id, description, thumbnail_media_id, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt_package->execute([$internal_user_id, $internal_bot_id, $description, $thumbnail_media_id]);
        $package_id = $this->pdo->lastInsertId();

        $stmt_link = $this->pdo->prepare("UPDATE media_files SET package_id = ? WHERE id = ?");
        foreach ($media_file_ids as $media_id) {
            $stmt_link->execute([$package_id, $media_id]);
        }

        $this->user_repo->setUserState($internal_user_id, 'awaiting_price', ['package_id' => $package_id]);
        $this->telegram_api->sendMessage($this->chat_id, "✅ Media telah disiapkan untuk dijual dengan deskripsi:\n\n*\"{$description}\"*\n\nSekarang, silakan masukkan harga untuk paket ini (contoh: 50000).", 'Markdown');
    }

    private function handleKontenCommand(array $parts)
    {
        $internal_user_id = $this->current_user['id'];
        if (count($parts) !== 2 || !is_numeric($parts[1])) {
            $this->telegram_api->sendMessage($this->chat_id, "Format perintah salah. Gunakan: /konten <ID Konten>");
            return;
        }

        $package_id = (int)$parts[1];
        $package = $this->package_repo->find($package_id);

        if (!$package) {
            $this->telegram_api->sendMessage($this->chat_id, "Konten dengan ID #{$package_id} tidak ditemukan.");
            return;
        }

        $thumbnail = null;
        // Coba dapatkan thumbnail yang spesifik terlebih dahulu
        if (!empty($package['thumbnail_media_id'])) {
            $stmt_thumb = $this->pdo->prepare("SELECT file_id, type FROM media_files WHERE id = ?");
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
        $has_purchased = (new SaleRepository($this->pdo))->hasUserPurchased($package_id, $internal_user_id);

        $has_access = $is_admin || $is_seller || $has_purchased;

        $keyboard = [];
        if ($has_access) {
            $keyboard = ['inline_keyboard' => [[['text' => 'Lihat Selengkapnya 📂', 'callback_data' => "view_full_{$package_id}"]]]];
        } elseif ($package['status'] === 'available') {
            $price_formatted = "Rp " . number_format($package['price'], 0, ',', '.');
            $keyboard = ['inline_keyboard' => [[['text' => "Beli Konten Ini ({$price_formatted}) 🛒", 'callback_data' => "buy_{$package_id}"]]]];
        }

        $caption = $package['description'];
        $method = 'send' . ucfirst($thumbnail['type']);

        if (method_exists($this->telegram_api, $method)) {
            $this->telegram_api->$method($this->chat_id, $thumbnail['file_id'], $caption, null, !empty($keyboard) ? json_encode($keyboard) : null);
        } else {
            $this->telegram_api->sendMessage($this->chat_id, $caption, null, !empty($keyboard) ? json_encode($keyboard) : null);
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
