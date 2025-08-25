<?php

/**
 * Repositori untuk mengelola data terkait bot, seperti token dan pengaturan.
 */
class BotRepository
{
    private $pdo;

    /**
     * Membuat instance BotRepository.
     *
     * @param PDO $pdo Objek koneksi database.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Mencari bot berdasarkan ID Telegram-nya dan mengembalikan data bot.
     *
     * @param int $telegram_bot_id ID numerik bot di Telegram.
     * @return array|false Mengembalikan data bot sebagai array asosiatif jika ditemukan, atau `false` jika tidak.
     */
    public function findBotByTelegramId(int $telegram_bot_id)
    {
        $stmt = $this->pdo->prepare("SELECT telegram_bot_id, token FROM bots WHERE telegram_bot_id = ?");
        $stmt->execute([$telegram_bot_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Mengambil semua pengaturan untuk bot tertentu, dengan nilai default jika tidak disetel.
     *
     * @param int $telegram_bot_id ID Telegram bot dari tabel `bots`.
     * @return array Pengaturan bot sebagai array asosiatif.
     */
    public function getBotSettings(int $telegram_bot_id): array
    {
        $stmt_settings = $this->pdo->prepare("SELECT setting_key, setting_value FROM bot_settings WHERE bot_id = ?");
        $stmt_settings->execute([$telegram_bot_id]);
        $bot_settings_raw = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);

        // Tetapkan pengaturan default jika tidak ada di database
        return [
            'save_text_messages'    => $bot_settings_raw['save_text_messages'] ?? '1',
            'save_media_messages'   => $bot_settings_raw['save_media_messages'] ?? '1',
            'save_callback_queries' => $bot_settings_raw['save_callback_queries'] ?? '0',
            'save_edited_messages'  => $bot_settings_raw['save_edited_messages'] ?? '0',
        ];
    }
}
