<?php

declare(strict_types=1);

/**
 * Class App
 *
 * Sebuah wadah sederhana untuk menampung objek dan data yang sering digunakan
 * di seluruh aplikasi, seperti koneksi database, instance API, dan pengguna saat ini.
 * Ini membantu mengurangi jumlah argumen yang perlu dilewatkan ke berbagai handler.
 */
class App
{
    /** @var PDO Koneksi database. */
    public $pdo;

    /** @var TelegramAPI Klien API Telegram. */
    public $telegram_api;

    /** @var array Informasi tentang bot yang sedang berjalan. */
    public $bot;

    /** @var array Pengaturan spesifik untuk bot ini. */
    public $bot_settings;

    /** @var array Informasi pengguna yang berinteraksi dengan bot. */
    public $user;

    /** @var int ID obrolan saat ini. */
    public $chat_id;

    /**
     * App constructor.
     *
     * @param PDO $pdo
     * @param TelegramAPI $telegram_api
     * @param array $bot
     * @param array $bot_settings
     * @param array $user
     * @param int $chat_id
     */
    public function __construct(
        PDO $pdo,
        TelegramAPI $telegram_api,
        array $bot,
        array $bot_settings,
        array $user,
        int $chat_id
    ) {
        $this->pdo = $pdo;
        $this->telegram_api = $telegram_api;
        $this->bot = $bot;
        $this->bot_settings = $bot_settings;
        $this->user = $user;
        $this->chat_id = $chat_id;
    }
}
