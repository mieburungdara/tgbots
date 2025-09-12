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
        } catch (BotNotFoundException $e) {
            $this->jsonResponse(array('error' => $e->getMessage()), 404);
        } catch (Exception $e) {
            App::getLogger()->error('Error in BotApiController/setWebhook: ' . $e->getMessage());
            $this->jsonResponse(array('error' => 'An internal error occurred.'), 500);
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
        } catch (BotNotFoundException $e) {
            $this->jsonResponse(array('error' => $e->getMessage()), 404);
        } catch (Exception $e) {
            App::getLogger()->error('Error in BotApiController/getWebhookInfo: ' . $e->getMessage());
            $this->jsonResponse(array('error' => 'An internal error occurred.'), 500);
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
        } catch (BotNotFoundException $e) {
            $this->jsonResponse(array('error' => $e->getMessage()), 404);
        } catch (Exception $e) {
            App::getLogger()->error('Error in BotApiController/deleteWebhook: ' . $e->getMessage());
            $this->jsonResponse(array('error' => 'An internal error occurred.'), 500);
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
                $this->jsonResponse(array('error' => "Gagal mendapatkan info dari Telegram: " . (isset($bot_info['description']) ? $bot_info['description'] : 'Error')), 500);
            }

            $bot_result = $bot_info['result'];
            if ($bot_result['id'] != $bot_id) {
                $this->jsonResponse(array('error' => "Token tidak cocok dengan ID bot."), 400);
            }

            $first_name = $bot_result['first_name'];
            $username = isset($bot_result['username']) ? $bot_result['username'] : null;

            $pdo = \get_db_connection();
            $stmt_update = $pdo->prepare("UPDATE bots SET first_name = ?, username = ? WHERE id = ?");
            $stmt_update->execute(array($first_name, $username, $bot_id));

            $this->jsonResponse(array(
                'success' => true,
                'data' => array('first_name' => $first_name, 'username' => $username)
            ));
        } catch (BotNotFoundException $e) {
            $this->jsonResponse(array('error' => $e->getMessage()), 404);
        } catch (Exception $e) {
            App::getLogger()->error('Error in BotApiController/getMe: ' . $e->getMessage());
            $this->jsonResponse(array('error' => 'An internal error occurred.'), 500);
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
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array("update_id"=>0, "message"=>array("text"=>"/test"))));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response_body = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $this->jsonResponse(array(
                'status_code' => $http_code,
                'body' => $response_body
            ));
        } catch (BotNotFoundException $e) {
            $this->jsonResponse(array('error' => $e->getMessage()), 404);
        } catch (Exception $e) {
            App::getLogger()->error('Error in BotApiController/testWebhook: ' . $e->getMessage());
            $this->jsonResponse(array('error' => 'An internal error occurred.'), 500);
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
        $protocol = 'http://';
        if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) {
            $protocol = 'https://';
        }

        $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $script_name = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
        $base_path = rtrim(dirname($script_name), "/\\");

        if ($base_path === '/' || $base_path === '\\') {
            $base_path = '';
        }

        return $protocol . $domain . $base_path . '/webhook/' . $bot_id;
    }
}
