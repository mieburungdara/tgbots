<?php

require_once __DIR__ . '/../BaseController.php';
require_once __DIR__ . '/../../../core/TelegramAPI.php';

class BotController extends BaseController {

    public function index() {
        $pdo = get_db_connection();
        $bots = $pdo->query("SELECT id, first_name, username, created_at FROM bots ORDER BY created_at DESC")->fetchAll();

        // Start session if not already started to handle flash messages
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

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
    }

    public function store() {
        $pdo = get_db_connection();
        $error = null;
        $success = null;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/bots');
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

        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if ($error) {
            $_SESSION['flash_error'] = $error;
        }
        if ($success) {
            $_SESSION['flash_success'] = $success;
        }

        header('Location: /admin/bots');
        exit();
    }

    public function edit() {
        if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
            header("Location: /admin/bots");
            exit;
        }
        $bot_id = (int)$_GET['id'];
        $pdo = get_db_connection();

        $stmt = $pdo->prepare("SELECT id, first_name, username, token, created_at FROM bots WHERE id = ?");
        $stmt->execute([$bot_id]);
        $bot = $stmt->fetch();

        if (!$bot) {
            header("Location: /admin/bots");
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

        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $status_message = $_SESSION['flash_status'] ?? null;
        unset($_SESSION['flash_status']);

        $this->view('admin/bots/edit', [
            'page_title' => 'Edit Bot: ' . htmlspecialchars($bot['first_name']),
            'bot' => $bot,
            'settings' => $settings,
            'status_message' => $status_message
        ], 'admin_layout');
    }

    public function updateSettings() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['bot_id'])) {
            header('Location: /admin/bots');
            exit();
        }

        $bot_id = (int)$_POST['bot_id'];
        $submitted_settings = $_POST['settings'] ?? [];
        $pdo = get_db_connection();

        // Define all possible settings to ensure we process all of them
        $all_setting_keys = [
            'save_text_messages',
            'save_media_messages',
            'save_callback_queries',
            'save_edited_messages'
        ];

        try {
            $pdo->beginTransaction();

            foreach ($all_setting_keys as $key) {
                // If a checkbox is checked, its value is '1'. If not, it's not sent.
                $value = isset($submitted_settings[$key]) ? '1' : '0';

                $stmt = $pdo->prepare(
                    "INSERT INTO bot_settings (bot_id, setting_key, setting_value)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
                );
                $stmt->execute([$bot_id, $key, $value]);
            }

            $pdo->commit();
            $status_message = "Pengaturan berhasil disimpan.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $status_message = "Gagal menyimpan pengaturan: " . $e->getMessage();
        }

        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash_status'] = $status_message;

        header("Location: /admin/bots/edit?id=" . $bot_id);
        exit();
    }
}
