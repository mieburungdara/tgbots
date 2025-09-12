<?php
declare(strict_types=1);

namespace TGBot\Controllers;

use TGBot\Database\BotRepository;
use TGBot\Database\RawUpdateRepository;
use TGBot\UpdateDispatcher;
use TGBot\TelegramAPI;
use TGBot\Logger;
use Throwable;

class WebhookController
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

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
                        $bot_id = $params['id'] ?? null;
            if (!$bot_id || !filter_var($bot_id, FILTER_VALIDATE_INT)) {
                $this->setHttpResponseCode(400);
                $this->logger->error("Webhook Error: ID bot dari URL tidak valid atau tidak ada.");
                $this->terminate();
            }
            $bot_id = (int)$bot_id;
            $this->logger->info("Webhook dipanggil untuk bot ID: {$bot_id}");

            $pdo = $this->getDbConnection();
            if (!$pdo) {
                $this->logger->critical("Webhook Error: Gagal terkoneksi ke database.");
                $this->setHttpResponseCode(500);
                $this->terminate();
            }

            $bot_repo = $this->getBotRepository($pdo);
            $bot = $bot_repo->findBotByTelegramId($bot_id);
            if (!$bot) {
                $this->setHttpResponseCode(404);
                $this->logger->error("Webhook Error: Bot dengan ID Telegram {$bot_id} tidak ditemukan.");
                $this->terminate();
            }
            $this->logger->info("Bot ditemukan: " . json_encode($bot));

            
            $api_for_globals = $this->getTelegramAPI($bot['token'], $pdo, $bot_id, $this->logger);
            $bot_info = $api_for_globals->getMe();
            if ($bot_info['ok'] && !defined('BOT_USERNAME')) {
                @define('BOT_USERNAME', $bot_info['result']['username'] ?? '');
            }

            $update_json = $this->getPhpInput();
            if (empty($update_json)) {
                $this->setHttpResponseCode(200);
                $this->terminate();
            }
            $this->logger->info("Update mentah diterima: {$update_json}");

            $raw_update_repo = $this->getRawUpdateRepository($pdo);
            $raw_update_repo->create($update_json);

            $update = json_decode($update_json, true);
            if (!$update) {
                $this->logger->warning("Webhook Error: Gagal mendekode JSON dari Telegram.");
                $this->setHttpResponseCode(200);
                $this->terminate();
            }

            $dispatcher = $this->getUpdateDispatcher($pdo, $bot, $update, $this->logger);
            $dispatcher->dispatch();

            $this->setHttpResponseCode(200);

        } catch (Throwable $e) {
            $error_message = sprintf("Fatal Webhook Error: %s in %s on line %d", $e->getMessage(), $e->getFile(), $e->getLine());
            $this->logger->error($error_message);
            $this->setHttpResponseCode(500);
            $this->terminate();
        }
        $this->terminate();
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
        // In production, it would typically return a real PDO connection.
        // For now, we'll return null to avoid calling a global function that might not be defined.
        return null; // Or a default PDO instance if applicable
    }

    protected function getBotRepository($pdo)
    {
        return new BotRepository($pdo);
    }

    protected function getRawUpdateRepository($pdo)
    {
        return new RawUpdateRepository($pdo);
    }

    protected function getTelegramAPI($token, $pdo, $botId, $logger)
    {
        return new TelegramAPI($token, $pdo, $botId, $logger);
    }

    protected function getUpdateDispatcher($pdo, $bot, $update, $logger)
    {
        return new UpdateDispatcher($pdo, $bot, $update, $logger);
    }
}