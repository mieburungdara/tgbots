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
use Monolog\Logger;

/**
 * Class App
 * @package TGBot
 *
 * @purpose Berfungsi sebagai "wadah" atau kontainer untuk menyimpan objek-objek penting 
 * yang sering diakses di seluruh bagian aplikasi. Tujuannya adalah untuk mempermudah 
 * pengelolaan dan akses ke sumber daya seperti koneksi database dan informasi bot 
 * tanpa harus membuatnya berulang kali.
 */
class App
{
    /** @var PDO Koneksi ke database. */
    public PDO $pdo;

    /** @var TelegramAPI Klien untuk berinteraksi dengan API Telegram. */
    public TelegramAPI $telegram_api;

    /** @var array Data bot yang sedang aktif (ID, nama, dll.). */
    public array $bot;

    /** @var array Pengaturan spesifik untuk bot tersebut. */
    public array $bot_settings;

    /** @var array Data pengguna yang sedang berinteraksi dengan bot. */
    public array $user;

    /** @var int ID obrolan saat ini. */
    public int $chat_id;

    /** @var Logger Instansi logger terpusat. */
    private static Logger $logger;

    /**
     * App constructor.
     *
     * @purpose Method ini dijalankan saat objek App dibuat. Fungsinya adalah untuk 
     * menginisialisasi semua properti kelas dengan data yang relevan.
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

    /**
     * Mengatur instansi logger terpusat.
     *
     * @param Logger $logger Instansi logger.
     * @return void
     */
    public static function setLogger(Logger $logger): void
    {
        self::$logger = $logger;
    }

    /**
     * Mengambil instansi logger terpusat.
     *
     * @return Logger Instansi logger.
     */
    public static function getLogger(): Logger
    {
        if (!isset(self::$logger)) {
            self::$logger = LoggerFactory::create();
        }
        return self::$logger;
    }
}