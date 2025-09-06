<?php

/**
 * This file is part of the TGBot package.
 *
 * (c) Zidin Mitra Abadi <zidinmitra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TGBot\Controllers\Admin;



use Exception;
use PDO;
use TGBot\Controllers\BaseController;
use TGBot\TelegramAPI;

/**
 * Class BotController
 * @package TGBot\Controllers\Admin
 *
 * @purpose Mengelola semua operasi yang terkait dengan bot dari panel admin,
 * seperti menambah, mengedit, dan mengonfigurasi bot.
 */
class BotController extends BaseController
{
    /**
     * @var string[]
     */
    private $available_features = [
        'sell' => 'Jual (/sell)',
        'rate' => 'Rating (/rate)',
        'tanya' => 'Tanya (/tanya)',
    ];

    /**
     * Menampilkan halaman manajemen bot.
     *
     * @purpose Menampilkan halaman utama "Kelola Bot" yang berisi daftar semua bot
     * yang terdaftar di sistem.
     *
     * @return void
     */
    public function index(): void
    {
        try {
            $pdo = \get_db_connection();
            $bots = $pdo->query("SELECT id, first_name, username, created_at, assigned_feature FROM bots ORDER BY created_at DESC")->fetchAll();

            $error = $_SESSION['flash_error'] ?? null;
            $success = $_SESSION['flash_success'] ?? null;

            // Unset flash messages so they don't show again
            unset($_SESSION['flash_error']);
            unset($_SESSION['flash_success']);

            $this->view('admin/bots/index', [
                'page_title' => 'Kelola Bot',
                'bots' => $bots,
                'error' => $error,
                'success' => $success
            ], 'admin_layout');
        } catch (Exception $e) {
            \app_log('Error in BotController/index: ' . $e->getMessage(), 'error');
            $this->view('admin/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading the bot management page.'
            ], 'admin_layout');
        }
    }

    /**
     * Menyimpan bot baru.
     *
     * @purpose Memproses formulir penambahan bot baru. Fungsi ini mengambil token bot,
     * memvalidasinya dengan menghubungi API Telegram, dan jika valid, menyimpannya ke database.
     *
     * @return void
     */
    public function store(): void
    {
        $pdo = \get_db_connection();
        $error = null;
        $success = null;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /xoradmin/bots');
            exit();
        }

        $token = trim($_POST['token']);

        if (empty($token)) {
            $error = "Token tidak boleh kosong.";
        } else {
            try {
                $telegram_api = new TelegramAPI($token);
                $bot_info = $telegram_api->getMe();

                if (isset($bot_info['ok']) && $bot_info['ok'] === true) {
                    $bot_result = $bot_info['result'];
                    $first_name = $bot_result['first_name'];
                    $username = $bot_result['username'] ?? null;
                    $bot_id = $bot_result['id'];

                    $stmt_check_token = $pdo->prepare("SELECT id FROM bots WHERE token = ?");
                    $stmt_check_token->execute([$token]);
                    if ($stmt_check_token->fetch()) {
                         throw new Exception("Token ini sudah ada di database.", 23000);
                    }

                    $stmt_check_id = $pdo->prepare("SELECT id FROM bots WHERE id = ?");
                    $stmt_check_id->execute([$bot_id]);
                    if ($stmt_check_id->fetch()) {
                         throw new Exception("Bot dengan ID Telegram {$bot_id} ini sudah terdaftar.", 23000);
                    }

                    $stmt = $pdo->prepare("INSERT INTO bots (id, first_name, username, token) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$bot_id, $first_name, $username, $token]);

                    $success = "Bot '{$first_name}' (@{$username}) berhasil ditambahkan!";

                } else {
                    throw new Exception("Token tidak valid atau gagal menghubungi API Telegram. " . ($bot_info['description'] ?? ''));
                }
            } catch (Exception $e) {
                if ($e->getCode() == 23000) {
                    $error = "Error: " . $e->getMessage();
                } else {
                    $error = "Gagal menambahkan bot: " . $e->getMessage();
                }
            }
        }

        if ($error) {
            $_SESSION['flash_error'] = $error;
        }
        if ($success) {
            $_SESSION['flash_success'] = $success;
        }

        header('Location: /xoradmin/bots');
        exit();
    }

    /**
     * Menampilkan halaman edit bot.
     *
     * @purpose Menampilkan halaman untuk mengedit pengaturan bot tertentu.
     *
     * @return void
     */
    public function edit(): void
    {
        try {
            if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
                header("Location: /xoradmin/bots");
                exit;
            }
            $bot_id = (int)$_GET['id'];
            $pdo = \get_db_connection();

            $stmt = $pdo->prepare("SELECT id, first_name, username, token, created_at FROM bots WHERE id = ?");
            $stmt->execute([$bot_id]);
            $bot = $stmt->fetch();

            if (!$bot) {
                header("Location: /xoradmin/bots");
                exit;
            }

            $stmt_settings = $pdo->prepare("SELECT setting_key, setting_value FROM bot_settings WHERE bot_id = ?");
            $stmt_settings->execute([$bot_id]);
            $bot_settings_raw = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);

            $settings = [
                'save_text_messages'    => $bot_settings_raw['save_text_messages'] ?? '1',
                'save_media_messages'   => $bot_settings_raw['save_media_messages'] ?? '1',
                'save_callback_queries' => $bot_settings_raw['save_callback_queries'] ?? '0',
                'save_edited_messages'  => $bot_settings_raw['save_edited_messages'] ?? '0',
            ];

            $status_message = $_SESSION['flash_status'] ?? null;
            unset($_SESSION['flash_status']);

            $this->view('admin/bots/edit', [
                'page_title' => 'Edit Bot: ' . htmlspecialchars($bot['first_name']),
                'bot' => $bot,
                'settings' => $settings,
                'status_message' => $status_message,
                'available_features' => $this->available_features
            ], 'admin_layout');
        } catch (Exception $e) {
            \app_log('Error in BotController/edit: ' . $e->getMessage(), 'error');
            $this->view('admin/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading the bot edit page.'
            ], 'admin_layout');
        }
    }

    /**
     * Memperbarui pengaturan bot.
     *
     * @purpose Menyimpan perubahan pengaturan yang dibuat di halaman edit.
     *
     * @return void
     */
    public function updateSettings(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['bot_id'])) {
            header('Location: /xoradmin/bots');
            exit();
        }

        $bot_id = (int)$_POST['bot_id'];
        $submitted_settings = $_POST['settings'] ?? [];
        $assigned_feature = $_POST['assigned_feature'] ?? null;
        $pdo = \get_db_connection();

        // Define all possible settings to ensure we process all of them
        $all_setting_keys = [
            'save_text_messages',
            'save_media_messages',
            'save_callback_queries',
            'save_edited_messages'
        ];

        try {
            $pdo->beginTransaction();

            // Handle bot_settings
            foreach ($all_setting_keys as $key) {
                $value = isset($submitted_settings[$key]) ? '1' : '0';
                $stmt = $pdo->prepare(
                    "INSERT INTO bot_settings (bot_id, setting_key, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
                );
                $stmt->execute([$bot_id, $key, $value]);
            }

            // Handle assigned_feature
            if (array_key_exists($assigned_feature, $this->available_features)) {
                $stmt_feature = $pdo->prepare("UPDATE bots SET assigned_feature = ? WHERE id = ?");
                $stmt_feature->execute([$assigned_feature, $bot_id]);
            } else {
                // If the value is empty or invalid, set it to NULL
                $stmt_feature = $pdo->prepare("UPDATE bots SET assigned_feature = NULL WHERE id = ?");
                $stmt_feature->execute([$bot_id]);
            }

            $pdo->commit();
            $status_message = "Pengaturan berhasil disimpan.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $status_message = "Gagal menyimpan pengaturan: " . $e->getMessage();
        }

        $_SESSION['flash_status'] = $status_message;

        header("Location: /xoradmin/bots/edit?id=" . $bot_id);
        exit();
    }

    /**
     * Mendapatkan instance bot dan API.
     *
     * @param int $bot_id
     * @return TelegramAPI
     */
    private function getBotAndApi(int $bot_id): TelegramAPI
    {
        try {
            if (!$bot_id) {
                $this->jsonResponse(['error' => 'ID Bot tidak valid.'], 400);
            }
            $pdo = \get_db_connection();
            $stmt = $pdo->prepare("SELECT token FROM bots WHERE id = ?");
            $stmt->execute([$bot_id]);
            $bot = $stmt->fetch();
            if (!$bot) {
                $this->jsonResponse(['error' => "Bot dengan ID {$bot_id} tidak ditemukan."], 404);
            }
            return new TelegramAPI($bot['token']);
        } catch (Exception $e) {
            \app_log('Error in BotController/getBotAndApi: ' . $e->getMessage(), 'error');
            $this->jsonResponse(['error' => 'An internal error occurred.'], 500);
        }
    }

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
            $bot_id = (int)($_POST['bot_id'] ?? 0);
            $telegram = $this->getBotAndApi($bot_id);
            $webhook_url = $this->generateWebhookUrl($bot_id);
            $result = $telegram->setWebhook($webhook_url);
            $this->jsonResponse($result);
        } catch (Exception $e) {
            \app_log('Error in BotController/setWebhook: ' . $e->getMessage(), 'error');
            $this->jsonResponse(['error' => 'An internal error occurred.'], 500);
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
            $bot_id = (int)($_POST['bot_id'] ?? 0);
            $telegram = $this->getBotAndApi($bot_id);
            $result = $telegram->getWebhookInfo();
            $this->jsonResponse($result);
        } catch (Exception $e) {
            \app_log('Error in BotController/getWebhookInfo: ' . $e->getMessage(), 'error');
            $this->jsonResponse(['error' => 'An internal error occurred.'], 500);
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
            $bot_id = (int)($_POST['bot_id'] ?? 0);
            $telegram = $this->getBotAndApi($bot_id);
            $result = $telegram->deleteWebhook();
            $this->jsonResponse($result);
        } catch (Exception $e) {
            \app_log('Error in BotController/deleteWebhook: ' . $e->getMessage(), 'error');
            $this->jsonResponse(['error' => 'An internal error occurred.'], 500);
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
            $bot_id = (int)($_POST['bot_id'] ?? 0);
            $telegram = $this->getBotAndApi($bot_id);
            $bot_info = $telegram->getMe();

            if (!isset($bot_info['ok']) || !$bot_info['ok']) {
                $this->jsonResponse(['error' => "Gagal mendapatkan info dari Telegram: " . ($bot_info['description'] ?? 'Error')], 500);
            }

            $bot_result = $bot_info['result'];
            if ($bot_result['id'] != $bot_id) {
                $this->jsonResponse(['error' => "Token tidak cocok dengan ID bot."], 400);
            }

            $first_name = $bot_result['first_name'];
            $username = $bot_result['username'] ?? null;

            $pdo = \get_db_connection();
            $stmt_update = $pdo->prepare("UPDATE bots SET first_name = ?, username = ? WHERE id = ?");
            $stmt_update->execute([$first_name, $username, $bot_id]);

            $this->jsonResponse([
                'success' => true,
                'data' => ['first_name' => $first_name, 'username' => $username]
            ]);
        } catch (Exception $e) {
            \app_log('Error in BotController/getMe: ' . $e->getMessage(), 'error');
            $this->jsonResponse(['error' => 'An internal error occurred.'], 500);
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
            // This is a bit tricky as it's a request from the browser to the server itself.
            // The original script was making a request to webhook.php. We will do the same.
            $bot_id = (int)($_POST['bot_id'] ?? 0);
            if (!$bot_id) {
                $this->jsonResponse(['error' => 'ID Bot tidak valid.'], 400);
                return;
            }
            $webhook_url = $this->generateWebhookUrl($bot_id);

            // Using cURL to make the request from the server to itself
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
            \app_log('Error in BotController/testWebhook: ' . $e->getMessage(), 'error');
            $this->jsonResponse(['error' => 'An internal error occurred.'], 500);
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
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domain = $_SERVER['HTTP_HOST'];
        // Using dirname($_SERVER['SCRIPT_NAME']) is more reliable for finding the application's base path
        // and avoids the brittle str_replace.
        $base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        return $protocol . $domain . $base_path . '/webhook.php?id=' . $bot_id;
    }
}