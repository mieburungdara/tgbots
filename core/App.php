<?php

/**
 * This file is part of the TGBot package.
 *
 * (c) Zidin Mitra Abadi <zidinmitra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TGBot;

use PDO;

/**
 * Class App
 * @package TGBot
 *
 * Sebuah wadah sederhana untuk menampung objek dan data yang sering digunakan
 * di seluruh aplikasi, seperti koneksi database, instance API, dan pengguna saat ini.
 * Ini membantu mengurangi jumlah argumen yang perlu dilewatkan ke berbagai handler.
 */
class App
{
    /** @var PDO Koneksi database. */
    public PDO $pdo;

    /** @var TelegramAPI Klien API Telegram. */
    public TelegramAPI $telegram_api;

    /** @var array Informasi tentang bot yang sedang berjalan. */
    public array $bot;

    /** @var array Pengaturan spesifik untuk bot ini. */
    public array $bot_settings;

    /** @var array Informasi pengguna yang berinteraksi dengan bot. */
    public array $user;

    /** @var int ID obrolan saat ini. */
    public int $chat_id;

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
