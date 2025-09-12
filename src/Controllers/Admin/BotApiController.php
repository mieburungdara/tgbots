<?php

namespace TGBot\Controllers\Admin;

use TGBot\Exceptions\BotNotFoundException;
use Exception;
use TGBot\App;

class BotApiController extends BaseApiController
{
    /**
     * Mengatur webhook untuk bot.
     *
     * @purpose Fungsi API untuk mengatur webhook bot langsung dari panel admin.
     *
     * @return void
     */
    public function setWebhook(): void
    {
        try {
            $bot_id = (int)(isset($_POST['bot_id']) ? $_POST['bot_id'] : 0);
            $telegram = $this->getBotAndApi($bot_id);
            $webhook_url = $this->generateWebhookUrl($bot_id);
            $result = $telegram->setWebhook($webhook_url);
            $this->jsonResponse($result);
        } catch (Exception $e) {
            $this->handleApiError($e, __FUNCTION__);
        }
    }

    /**
     * Mendapatkan info webhook untuk bot.
     *
     * @purpose Fungsi API untuk memeriksa status webhook bot langsung dari panel admin.
     *
     * @return void
     */
    public function getWebhookInfo(): void
    {
        try {
            $bot_id = (int)(isset($_POST['bot_id']) ? $_POST['bot_id'] : 0);
            $telegram = $this->getBotAndApi($bot_id);
            $result = $telegram->getWebhookInfo();
            $this->jsonResponse($result);
        } catch (Exception $e) {
            $this->handleApiError($e, __FUNCTION__);
        }
    }

    /**
     * Menghapus webhook untuk bot.
     *
     * @purpose Fungsi API untuk menghapus webhook bot langsung dari panel admin.
     *
     * @return void
     */
    public function deleteWebhook(): void
    {
        try {
            $bot_id = (int)(isset($_POST['bot_id']) ? $_POST['bot_id'] : 0);
            $telegram = $this->getBotAndApi($bot_id);
            $result = $telegram->deleteWebhook();
            $this->jsonResponse($result);
        } catch (Exception $e) {
            $this->handleApiError($e, __FUNCTION__);
        }
    }

    /**
     * Mendapatkan info bot dari Telegram.
     *
     * @purpose Menghubungi API Telegram untuk mendapatkan informasi terbaru tentang bot
     * (nama, username) dan memperbaruinya di database.
     *
     * @return void
     */
    public function getMe(): void
    {
        try {
            $bot_id = (int)(isset($_POST['bot_id']) ? $_POST['bot_id'] : 0);
            $telegram = $this->getBotAndApi($bot_id);
            $bot_info = $telegram->getMe();

            if (!isset($bot_info['ok']) || !$bot_info['ok']) {
                $this->jsonResponse(['error' => "Gagal mendapatkan info dari Telegram: " . (isset($bot_info['description']) ? $bot_info['description'] : 'Error')], 500);
            }

            $bot_result = $bot_info['result'];
            if ($bot_result['id'] != $bot_id) {
                $this->jsonResponse(['error' => "Token tidak cocok dengan ID bot."], 400);
            }

            $first_name = $bot_result['first_name'];
            $username = isset($bot_result['username']) ? $bot_result['username'] : null;

            $pdo = \get_db_connection();
            $stmt_update = $pdo->prepare("UPDATE bots SET first_name = ?, username = ? WHERE id = ?");
            $stmt_update->execute([$first_name, $username, $bot_id]);

            $this->jsonResponse([
                'success' => true,
                'data' => ['first_name' => $first_name, 'username' => $username]
            ]);
        } catch (Exception $e) {
            $this->handleApiError($e, __FUNCTION__);
        }
    }

    /**
     * Menguji webhook untuk bot.
     *
     * @return void
     */
    public function testWebhook(): void
    {
        try {
            $bot_id = (int)(isset($_POST['bot_id']) ? $_POST['bot_id'] : 0);
            $telegram = $this->getBotAndApi($bot_id);
            $webhook_url = $this->generateWebhookUrl($bot_id);

            $ch = curl_init($webhook_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["update_id"=>0, "message"=>["text"=>"/test"]]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response_body = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $this->jsonResponse([
                'status_code' => $http_code,
                'body' => $response_body
            ]);
        } catch (Exception $e) {
            $this->handleApiError($e, __FUNCTION__);
        }
    }

    /**
     * Generates the full webhook URL for a given bot.
     *
     * @param int $bot_id The ID of the bot.
     * @return string The full webhook URL.
     */
    private function generateWebhookUrl(int $bot_id): string
    {
        if (!defined('BASE_URL') || empty(BASE_URL)) {
            throw new Exception("BASE_URL tidak didefinisikan atau kosong di file config.php.");
        }
        return rtrim(BASE_URL, '/') . '/webhook/' . $bot_id;
    }

    private function handleApiError(Exception $e, string $methodName): void
    {
        if ($e instanceof BotNotFoundException) {
            $this->jsonResponse(['error' => $e->getMessage()], 404);
            return;
        }

        App::getLogger()->error('Error in BotApiController/' . $methodName . ': ' . $e->getMessage());
        $this->jsonResponse(['error' => 'An internal error occurred.'], 500);
    }
}
