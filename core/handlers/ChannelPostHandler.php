<?php

require_once __DIR__ . '/../database/SellerSalesChannelRepository.php';

/**
 * Menangani pembaruan yang berasal dari sebuah channel (channel post).
 * Terutama untuk menangani perintah administrasi yang dikirim di channel.
 */
class ChannelPostHandler
{
    private $pdo;
    private $telegram_api;
    private $user_repo;
    private $sales_channel_repo;
    private $current_user;
    private $channel_id;
    private $message;

    /**
     * Membuat instance ChannelPostHandler.
     *
     * @param PDO $pdo Objek koneksi database.
     * @param TelegramAPI $telegram_api Klien untuk berinteraksi dengan API Telegram.
     * @param UserRepository $user_repo Repositori untuk operasi terkait pengguna.
     * @param array $current_user Data pengguna yang terkait dengan post (jika ada).
     * @param int $channel_id ID channel tempat post dibuat.
     * @param array $message Data post channel lengkap dari Telegram.
     */
    public function __construct(PDO $pdo, TelegramAPI $telegram_api, UserRepository $user_repo, array $current_user, int $channel_id, array $message)
    {
        $this->pdo = $pdo;
        $this->telegram_api = $telegram_api;
        $this->user_repo = $user_repo;
        $this->sales_channel_repo = new SellerSalesChannelRepository($pdo);
        $this->current_user = $current_user;
        $this->channel_id = $channel_id;
        $this->message = $message;
    }

    /**
     * Titik masuk utama untuk menangani post dari channel.
     * Menganalisis teks post untuk perintah dan mendelegasikannya.
     */
    public function handle()
    {
        if (!isset($this->message['text'])) {
            return;
        }

        $text = $this->message['text'];
        $parts = explode(' ', $text);
        $command = $parts[0];

        if ($command === '/register_channel') {
            $this->handleRegisterChannelCommand();
        }
    }

    /**
     * Menangani perintah `/register_channel` yang dikirim di sebuah channel.
     * Saat ini, logika ini masih dalam pengembangan dan hanya dapat diakses oleh SUPER_ADMIN.
     */
    private function handleRegisterChannelCommand()
    {
        // Dalam konteks channel post, 'from' tidak selalu ada.
        // Verifikasi harus didasarkan pada siapa yang menjadi admin channel.
        // Untuk saat ini, kita asumsikan perintah ini hanya bisa dijalankan oleh SUPER_ADMIN
        // sebagai langkah keamanan awal.
        if (!defined('SUPER_ADMIN_TELEGRAM_ID') || $this->message['from']['id'] != SUPER_ADMIN_TELEGRAM_ID) {
             $this->telegram_api->sendMessage($this->channel_id, "Fitur ini dalam pengembangan.");
             return;
        }

        // NOTE: The logic below is a sketch for future implementation when we can reliably
        // identify the seller who is an admin in the channel.
        /*
        $sender_id = $this->message['from']['id'];

        if ($sender_id != $this->current_user['telegram_id']) {
            return;
        }

        if (empty($this->current_user['public_seller_id'])) {
            $this->telegram_api->sendMessage($this->channel_id, "⚠️ Pendaftaran channel gagal: Anda belum terdaftar sebagai penjual.");
            return;
        }

        $bot_info = $this->telegram_api->getMe();
        if (!$bot_info || !$bot_info['ok']) {
            $this->telegram_api->sendMessage($this->channel_id, "⚠️ Gagal memverifikasi status bot.");
            return;
        }
        $bot_id = $bot_info['result']['id'];
        $bot_member = $this->telegram_api->getChatMember($this->channel_id, $bot_id);

        if (!$bot_member || !$bot_member['ok'] || !in_array($bot_member['result']['status'], ['administrator', 'creator'])) {
            $this->telegram_api->sendMessage($this->channel_id, "⚠️ Pendaftaran channel gagal: Bot harus menjadi admin.");
            return;
        }

        if (!($bot_member['result']['can_post_messages'] ?? false)) {
             $this->telegram_api->sendMessage($this->channel_id, "⚠️ Pendaftaran channel gagal: Bot memerlukan izin 'Post Messages'.");
            return;
        }

        $user_member = $this->telegram_api->getChatMember($this->channel_id, $sender_id);
        if (!$user_member || !$user_member['ok'] || !in_array($user_member['result']['status'], ['administrator', 'creator'])) {
            $this->telegram_api->sendMessage($this->channel_id, "⚠️ Pendaftaran channel gagal: Anda harus menjadi admin channel.");
            return;
        }

        $success = $this->sales_channel_repo->createOrUpdate($this->current_user['id'], $this->channel_id);

        if ($success) {
            $channel_title = $this->message['chat']['title'] ?? 'channel ini';
            $escaped_title = $this->telegram_api->escapeMarkdown($channel_title);
            $this->telegram_api->sendMessage($this->channel_id, "✅ Channel *{$escaped_title}* berhasil didaftarkan sebagai channel jualan Anda.", 'Markdown');
        } else {
            $this->telegram_api->sendMessage($this->channel_id, "⚠️ Terjadi kesalahan database.");
        }
        */
    }
}
