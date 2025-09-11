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
                http_response_code(400);
                $this->logger->error("Webhook Error: ID bot dari URL tidak valid atau tidak ada.");
                exit;
            }
            $bot_id = (int)$bot_id;
            $this->logger->info("Webhook dipanggil untuk bot ID: {$bot_id}");

            $pdo = \get_db_connection($this->logger);
            if (!$pdo) {
                $this->logger->critical("Webhook Error: Gagal terkoneksi ke database.");
                http_response_code(500);
                exit;
            }

            $bot_repo = new BotRepository($pdo);
            $bot = $bot_repo->findBotByTelegramId($bot_id);
            if (!$bot) {
                http_response_code(404);
                $this->logger->error("Webhook Error: Bot dengan ID Telegram {$bot_id} tidak ditemukan.");
                exit;
            }
            $this->logger->info("Bot ditemukan: " . json_encode($bot));

            
            $api_for_globals = new TelegramAPI($bot['token'], $pdo, $bot_id, $this->logger);
            $bot_info = $api_for_globals->getMe();
            if ($bot_info['ok'] && !defined('BOT_USERNAME')) {
                define('BOT_USERNAME', $bot_info['result']['username'] ?? '');
            }

            $update_json = file_get_contents('php://input');
            if (empty($update_json)) {
                http_response_code(200);
                exit;
            }
            $this->logger->info("Update mentah diterima: {$update_json}");

            $raw_update_repo = new RawUpdateRepository($pdo);
            $raw_update_repo->create($update_json);

            $update = json_decode($update_json, true);
            if (!$update) {
                $this->logger->warning("Webhook Error: Gagal mendekode JSON dari Telegram.");
                http_response_code(200);
                exit;
            }

            $dispatcher = new UpdateDispatcher($pdo, $bot, $update, $this->logger);
            $dispatcher->dispatch();

            http_response_code(200);

        } catch (Throwable $e) {
            $error_message = sprintf("Fatal Webhook Error: %s in %s on line %d", $e->getMessage(), $e->getFile(), $e->getLine());
            $this->logger->error($error_message);
            http_response_code(500);
        }
        exit;
    }