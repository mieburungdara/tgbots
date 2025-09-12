<?php
declare(strict_types=1);

namespace TGBot\Controllers;

use TGBot\Database\BotRepository;
use TGBot\Database\RawUpdateRepository;
use TGBot\UpdateDispatcher;
use TGBot\TelegramAPI;
use TGBot\App;
use Throwable;

class WebhookController
{

    /**
     * Menangani permintaan webhook yang masuk dari Telegram.
     *
     * @purpose Method ini bertugas untuk:
     * 1. Menerima data JSON yang dikirim oleh Telegram.
     * 2. Memvalidasi ID bot dari URL untuk memastikan permintaan itu sah.
     * 3. Menyimpan data mentah dari Telegram ke dalam database (untuk logging dan debug).
     * 4. Memanggil UpdateDispatcher, sebuah kelas lain yang bertugas menganalisis jenis update 
     *    (misalnya, apakah itu pesan teks, perintah, atau klik tombol) dan meneruskannya 
     *    ke handler yang sesuai.
     *
     * @param array $params Parameter dari router, contoh: ['id' => 123]
     */
    public function handle($params)
    {
        try {
            App::getLogger()->info("Memulai proses webhook...");
            $bot_id = $params['id'] ?? null;
            if (!$bot_id || !filter_var($bot_id, FILTER_VALIDATE_INT)) {
                $this->setHttpResponseCode(400);
                App::getLogger()->error("Webhook Error: ID bot dari URL tidak valid atau tidak ada.");
                return;
            }
            $bot_id = (int)$bot_id;
            App::getLogger()->info("Webhook dipanggil untuk bot ID: {$bot_id}");

            App::getLogger()->info("Mendapatkan koneksi database...");
            $pdo = $this->getDbConnection();
            if (!$pdo) {
                App::getLogger()->critical("Webhook Error: Gagal terkoneksi ke database.");
                $this->setHttpResponseCode(500);
                return;
            }
            App::getLogger()->info("Koneksi database berhasil didapatkan.");

            App::getLogger()->info("Mencari bot dengan ID: {$bot_id}...");
            $bot_repo = $this->getBotRepository($pdo);
            $bot = $bot_repo->findBotByTelegramId($bot_id);
            if (!$bot) {
                $this->setHttpResponseCode(404);
                App::getLogger()->error("Webhook Error: Bot dengan ID Telegram {$bot_id} tidak ditemukan.");
                return;
            }
            App::getLogger()->info("Bot ditemukan.");

            $telegram_api = $this->getTelegramAPI($bot['token'], $pdo, $bot_id, App::getLogger(), $bot);

            App::getLogger()->info("Membaca input mentah dari Telegram...");
            $update_json = $this->getPhpInput();
            App::getLogger()->info("Input mentah diterima, ukuran: " . strlen($update_json) . " bytes.");
            if (empty($update_json)) {
                App::getLogger()->info("Input mentah kosong, proses dihentikan.");
                $this->setHttpResponseCode(200);
                return;
            }

            App::getLogger()->info("Menyimpan update mentah ke database...");
            $raw_update_repo = $this->getRawUpdateRepository($pdo);
            $raw_update_repo->create($update_json);
            App::getLogger()->info("Update mentah berhasil disimpan.");

            App::getLogger()->info("Mendekode JSON update...");
            $update = json_decode($update_json, true);
            if (!$update) {
                App::getLogger()->warning("Webhook Error: Gagal mendekode JSON dari Telegram.");
                $this->setHttpResponseCode(200);
                return;
            }
            App::getLogger()->info("JSON berhasil didekode.");

            App::getLogger()->info("Memanggil UpdateDispatcher...");
            $dispatcher = $this->getUpdateDispatcher($pdo, $bot, $update, $telegram_api);
            $dispatcher->dispatch();
            App::getLogger()->info("UpdateDispatcher selesai.");

            $this->setHttpResponseCode(200);
            App::getLogger()->info("Proses webhook selesai dengan sukses.");

        } catch (Throwable $e) {
            $error_message = sprintf("Fatal Webhook Error: %s in %s on line %d", $e->getMessage(), $e->getFile(), $e->getLine());
            App::getLogger()->error($error_message);
            $this->setHttpResponseCode(500);
        }
    }

    protected function setHttpResponseCode(int $code): void
    {
        http_response_code($code);
    }

    protected function terminate(): void
    {
        exit;
    }

    protected function getPhpInput(): string
    {
        return file_get_contents('php://input');
    }

    protected function getDbConnection()
    {
        // This method is intended to be mocked in tests.
        // In production, it should return a real PDO connection.
        return \get_db_connection();
    }

    protected function getBotRepository($pdo)
    {
        return new BotRepository($pdo);
    }

    protected function getRawUpdateRepository($pdo)
    {
        return new RawUpdateRepository($pdo);
    }

    protected function getTelegramAPI($token, $pdo, $botId, $logger, $bot_data)
    {
        return new TelegramAPI($token, $pdo, $botId, $logger, $bot_data);
    }

    protected function getUpdateDispatcher($pdo, $bot, $update, $telegram_api)
    {
        return new UpdateDispatcher($pdo, $bot, $update, $telegram_api);
    }
}