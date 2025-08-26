<?php

require_once __DIR__ . '/../database/SellerSalesChannelRepository.php';
require_once __DIR__ . '/HandlerInterface.php';

/**
 * Menangani pembaruan yang berasal dari sebuah channel (channel post).
 * Terutama untuk menangani perintah administrasi yang dikirim di channel
 * dan menyimpan post itu sendiri jika diperlukan.
 */
class ChannelPostHandler implements HandlerInterface
{
    /**
     * Titik masuk utama untuk menangani post dari channel.
     *
     * @param App $app Wadah aplikasi.
     * @param array $channel_post Data post channel lengkap dari Telegram.
     */
    public function handle(App $app, array $channel_post): void
    {
        // 1. Log the incoming channel post to the messages table
        $telegram_message_id = $channel_post['message_id'];
        $chat_id = $channel_post['chat']['id'];
        $text_content = $channel_post['text'] ?? ($channel_post['caption'] ?? '');
        $timestamp = $channel_post['date'] ?? time();
        $message_date = date('Y-m-d H:i:s', $timestamp);
        $chat_type = $channel_post['chat']['type'] ?? 'channel';
        $update_json = json_encode(['channel_post' => $channel_post]); // Re-encode for storage

        $stmt = $app->pdo->prepare(
            "INSERT INTO messages (user_id, bot_id, telegram_message_id, chat_id, chat_type, update_type, text, raw_data, direction, telegram_timestamp)
             VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, 'incoming', ?)"
        );
        $stmt->execute([
            $app->bot['telegram_bot_id'],
            $telegram_message_id,
            $chat_id,
            $chat_type,
            'channel_post',
            $text_content,
            $update_json,
            $message_date
        ]);

        // 2. Handle any commands within the channel post
        if (!isset($channel_post['text'])) {
            return;
        }

        $text = $channel_post['text'];
        $parts = explode(' ', $text);
        $command = $parts[0];

        if ($command === '/register_channel') {
            $this->handleRegisterChannelCommand($app, $channel_post);
        }
    }

    /**
     * Menangani perintah `/register_channel` yang dikirim di sebuah channel.
     */
    private function handleRegisterChannelCommand(App $app, array $channel_post)
    {
        $channel_id = $channel_post['chat']['id'];

        // Verifikasi harus didasarkan pada siapa yang menjadi admin channel.
        // Untuk saat ini, asumsikan perintah ini hanya bisa dijalankan oleh SUPER_ADMIN.
        if (!defined('SUPER_ADMIN_TELEGRAM_ID') || ($channel_post['from']['id'] ?? 0) != SUPER_ADMIN_TELEGRAM_ID) {
             $app->telegram_api->sendMessage($channel_id, "Fitur ini dalam pengembangan.");
             return;
        }

        // Logika pendaftaran channel di masa depan akan ditempatkan di sini.
    }
}
