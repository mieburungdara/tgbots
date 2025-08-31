<?php
declare(strict_types=1);

namespace TGBot\Controllers;

use TGBot\Database\BotRepository;
use TGBot\Database\RawUpdateRepository;
use TGBot\UpdateDispatcher;
use TGBot\TelegramAPI;
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
            $bot_id = $params['id'] ?? null;
            if (!$bot_id || !filter_var($bot_id, FILTER_VALIDATE_INT)) {
                http_response_code(400);
                app_log("Webhook Error: ID bot dari URL tidak valid atau tidak ada.", 'bot');
                exit;
            }
            $bot_id = (int)$bot_id;

            $pdo = get_db_connection();
            if (!$pdo) {
                app_log("Webhook Error: Gagal terkoneksi ke database.", 'critical');
                http_response_code(500);
                exit;
            }

            $bot_repo = new BotRepository($pdo);
            $bot = $bot_repo->findBotByTelegramId($bot_id);
            if (!$bot) {
                http_response_code(404);
                app_log("Webhook Error: Bot dengan ID Telegram {$bot_id} tidak ditemukan.", 'bot');
                exit;
            }

            
            $api_for_globals = new TelegramAPI($bot['token'], $pdo, $bot_id);
            $bot_info = $api_for_globals->getMe();
            if ($bot_info['ok'] && !defined('BOT_USERNAME')) {
                define('BOT_USERNAME', $bot_info['result']['username'] ?? '');
            }

            $update_json = file_get_contents('php://input');
            if (empty($update_json)) {
                http_response_code(200);
                exit;
            }

            $raw_update_repo = new RawUpdateRepository($pdo);
            $raw_update_repo->create($update_json);

            $update = json_decode($update_json, true);
            if (!$update) {
                app_log("Webhook Error: Gagal mendekode JSON dari Telegram.", 'warning');
                http_response_code(200);
                exit;
            }

            $dispatcher = new UpdateDispatcher($pdo, $bot, $update);
            $dispatcher->dispatch();

            http_response_code(200);

        } catch (Throwable $e) {
            $error_message = sprintf("Fatal Webhook Error: %s in %s on line %d", $e->getMessage(), $e->getFile(), $e->getLine());
            app_log($error_message, 'error');
            http_response_code(500);
        }
        exit;
    }
}