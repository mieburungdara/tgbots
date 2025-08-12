<?php

class BotRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Cari bot berdasarkan ID Telegram-nya dan kembalikan data bot.
     *
     * @param int $telegram_bot_id
     * @return array|false
     */
    public function findBotByTelegramId(int $telegram_bot_id)
    {
        $stmt = $this->pdo->prepare("SELECT id, token FROM bots WHERE token LIKE ?");
        $stmt->execute(["{$telegram_bot_id}:%"]);
        return $stmt->fetch();
    }

    /**
     * Ambil pengaturan untuk bot tertentu.
     *
     * @param int $internal_bot_id
     * @return array
     */
    public function getBotSettings(int $internal_bot_id): array
    {
        $stmt_settings = $this->pdo->prepare("SELECT setting_key, setting_value FROM bot_settings WHERE bot_id = ?");
        $stmt_settings->execute([$internal_bot_id]);
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
