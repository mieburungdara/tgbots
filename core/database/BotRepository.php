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
     * Mengambil semua bot dari database.
     *
     * @return array Daftar semua bot.
     */
    public function getAllBots(): array
    {
        $stmt = $this->pdo->query("SELECT id, first_name, username FROM bots ORDER BY first_name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mencari bot berdasarkan ID Telegram-nya dan mengembalikan data bot.
     *
     * @param int $bot_id ID numerik bot di Telegram.
     * @return array|false Mengembalikan data bot sebagai array asosiatif jika ditemukan, atau `false` jika tidak.
     */
    public function findBotByTelegramId(int $bot_id)
    {
        // Bot sekarang dicari berdasarkan ID Telegram-nya, yang merupakan Primary Key.
        $stmt = $this->pdo->prepare("SELECT id, token FROM bots WHERE id = ?");
        $stmt->execute([$bot_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Mengambil semua pengaturan untuk bot tertentu, dengan nilai default jika tidak disetel.
     *
     * @param int $bot_id ID internal bot dari tabel `bots`.
     * @return array Pengaturan bot sebagai array asosiatif.
     */
    public function getBotSettings(int $bot_id): array
    {
        $stmt_settings = $this->pdo->prepare("SELECT setting_key, setting_value FROM bot_settings WHERE bot_id = ?");
        $stmt_settings->execute([$bot_id]);
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
