<?php

require_once __DIR__ . '/../AppController.php';
require_once __DIR__ . '/../../../core/TelegramAPI.php';

class XorAdminController extends AppController
{
    private $pdo;
    private $correct_password;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Define BASE_PATH if it's not already defined
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', realpath(__DIR__ . '/../../../'));
        }

        if (file_exists(BASE_PATH . '/config.php')) {
            require_once BASE_PATH . '/config.php';
        }
        $this->correct_password = defined('XOR_ADMIN_PASSWORD') ? XOR_ADMIN_PASSWORD : 'sup3r4dmin';
    }

    private function isAuthenticated()
    {
        return isset($_SESSION['xor_is_authenticated']) && $_SESSION['xor_is_authenticated'];
    }

    private function connectDb()
    {
        if ($this->pdo === null) {
            $this->pdo = get_db_connection();
        }
    }

    /**
     * Memeriksa apakah pengguna terotentikasi. Jika tidak, hentikan eksekusi.
     * @param bool $is_api Jika true, kembalikan response JSON. Jika false, redirect.
     */
    private function requireAuth($is_api = false)
    {
        if (!$this->isAuthenticated()) {
            if ($is_api) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Sesi tidak valid.']);
            } else {
                header("Location: /xoradmin");
            }
            exit;
        }
    }

    /**
     * Handles the main view and POST requests for the page itself.
     */
    public function index()
    {
        try {
            $data = [
                'page_title' => 'XOR Admin Panel',
                'is_authenticated' => $this->isAuthenticated(),
                'error' => '',
                'active_tab' => $_GET['action'] ?? 'bots',
                'bots' => [],
                'users_with_roles' => [],
                'bot' => null,
                'settings' => [],
                'status_message' => null,
                'db_message' => '',
                'db_error' => '',
            ];

            if (!$this->isAuthenticated()) {
                return $this->view('admin/xoradmin/login', $data);
            }

            $this->connectDb();

            if (isset($_GET['action']) && $_GET['action'] === 'edit_bot' && isset($_GET['id'])) {
                $bot_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
                $stmt = $this->pdo->prepare("SELECT id, first_name, username, token, created_at FROM bots WHERE id = ?");
                $stmt->execute([$bot_id]);
                $data['bot'] = $stmt->fetch();
                if (!$data['bot']) {
                    header("Location: /xoradmin");
                    exit;
                }
                $stmt_settings = $this->pdo->prepare("SELECT setting_key, setting_value FROM bot_settings WHERE bot_id = ?");
                $stmt_settings->execute([$bot_id]);
                $bot_settings_raw = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
                $data['settings'] = [
                    'save_text_messages'    => $bot_settings_raw['save_text_messages'] ?? '1',
                    'save_media_messages'   => $bot_settings_raw['save_media_messages'] ?? '1',
                    'save_callback_queries' => $bot_settings_raw['save_callback_queries'] ?? '0',
                    'save_edited_messages'  => $bot_settings_raw['save_edited_messages'] ?? '0',
                ];
                $data['status_message'] = $_SESSION['status_message'] ?? null;
                unset($_SESSION['status_message']);
            }

            $data['bots'] = $this->pdo->query("SELECT id, first_name, username, created_at FROM bots ORDER BY created_at DESC")->fetchAll();

            if ($data['active_tab'] === 'roles') {
                 $sql = "
                    SELECT u.id, u.first_name, u.last_name, u.username, GROUP_CONCAT(r.name SEPARATOR ', ') as roles
                    FROM users u
                    LEFT JOIN user_roles ur ON u.id = ur.user_id
                    LEFT JOIN roles r ON ur.role_id = r.id
                    GROUP BY u.id
                    ORDER BY u.first_name, u.last_name
                ";
                $data['users_with_roles'] = $this->pdo->query($sql)->fetchAll();
            }

            $this->view('admin/xoradmin/index', $data);
        } catch (Exception $e) {
            app_log('Error in XorAdminController/index: ' . $e->getMessage(), 'error');
            $this->view('admin/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading the XOR admin page.'
            ], 'admin_layout');
        }
    }

    public function login()
    {
        try {
            if (isset($_POST['password']) && is_string($_POST['password']) && !empty($this->correct_password) && hash_equals($this->correct_password, $_POST['password'])) {
                $_SESSION['xor_is_authenticated'] = true;
            } else {
                $_SESSION['xor_error'] = "Password salah!";
                unset($_SESSION['xor_is_authenticated']);
            }
        } catch (Exception $e) {
            app_log('Error in XorAdminController/login: ' . $e->getMessage(), 'error');
            $_SESSION['xor_error'] = "An internal error occurred.";
        }
        header("Location: /xoradmin");
        exit;
    }

    public function logout()
    {
        try {
            unset($_SESSION['xor_is_authenticated']);
        } catch (Exception $e) {
            app_log('Error in XorAdminController/logout: ' . $e->getMessage(), 'error');
        }
        header("Location: /xoradmin");
        exit;
    }

    public function addBot()
    {
        $this->requireAuth();
        $this->connectDb();

        $token = trim($_POST['token']);
        if (empty($token)) {
            $_SESSION['bot_error'] = "Token tidak boleh kosong.";
        } else {
            try {
                $telegram_api = new TelegramAPI($token);
                $bot_info = $telegram_api->getMe();
                if (isset($bot_info['ok']) && $bot_info['ok'] === true) {
                    $bot_result = $bot_info['result'];
                    $stmt_check = $this->pdo->prepare("SELECT id FROM bots WHERE id = ?");
                    $stmt_check->execute([$bot_result['id']]);
                    if ($stmt_check->fetch()) throw new Exception("Bot dengan ID ini sudah ada.", 23000);

                    $stmt = $this->pdo->prepare("INSERT INTO bots (id, first_name, username, token) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$bot_result['id'], $bot_result['first_name'], $bot_result['username'] ?? null, $token]);
                    $_SESSION['bot_message'] = "Bot '{$bot_result['first_name']}' berhasil ditambahkan!";
                } else {
                    throw new Exception("Token tidak valid. " . ($bot_info['description'] ?? ''));
                }
            } catch (Exception $e) {
                $_SESSION['bot_error'] = "Gagal: " . $e->getMessage();
            }
        }
        header("Location: /xoradmin");
        exit;
    }

    public function saveBotSettings()
    {
        $this->requireAuth();
        $this->connectDb();

        $bot_id = filter_input(INPUT_POST, 'bot_id', FILTER_VALIDATE_INT);
        if ($bot_id) {
            try {
                $settings_from_form = $_POST['settings'] ?? [];
                $allowed_keys = ['save_text_messages', 'save_media_messages', 'save_callback_queries', 'save_edited_messages'];
                $this->pdo->beginTransaction();
                foreach ($allowed_keys as $key) {
                    $value = isset($settings_from_form[$key]) ? '1' : '0';
                    $sql = "INSERT INTO bot_settings (bot_id, setting_key, setting_value) VALUES (:bot_id, :key, :val) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([':bot_id' => $bot_id, ':key' => $key, ':val' => $value]);
                }
                $this->pdo->commit();
                $_SESSION['status_message'] = 'Pengaturan berhasil disimpan.';
            } catch (Exception $e) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                $_SESSION['status_message'] = 'Terjadi error saat menyimpan pengaturan.';
            }
            header("Location: /xoradmin?action=edit_bot&id=" . $bot_id);
            exit;
        }
        header("Location: /xoradmin");
        exit;
    }

    public function resetDb()
    {
        $this->requireAuth();
        $this->connectDb();

        try {
            $db_message = "Berhasil terhubung ke database.<br>";
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0;');
            $tables = $this->pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            if (empty($tables)) {
                $db_message .= "Tidak ada tabel untuk dihapus.<br>";
            } else {
                foreach ($tables as $table) $this->pdo->exec("DROP TABLE IF EXISTS `$table`");
                $db_message .= "<b>Semua tabel berhasil dihapus.</b><br>";
            }
            $sql_schema_file = BASE_PATH . '/updated_schema.sql';
            if (!file_exists($sql_schema_file)) throw new Exception("File skema tidak ditemukan.");
            $this->pdo->exec(file_get_contents($sql_schema_file));
            $db_message .= "<b>Skema database berhasil dibuat ulang.</b><br>";
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1;');
            $_SESSION['db_message'] = $db_message;
        } catch (Exception $e) {
            $_SESSION['db_error'] = "Terjadi kesalahan: " . $e->getMessage();
        }
        header("Location: /xoradmin?action=db_reset");
        exit;
    }

    /**
     * Handles all AJAX requests.
     */
    public function api()
    {
        $this->requireAuth(true);
        $this->connectDb();

        $response = ['status' => 'error', 'message' => 'Aksi tidak diketahui.'];
        $action = $_POST['action'] ?? null;

        try {
            if ($action === 'make_admin' && isset($_POST['user_id'])) {
                $user_id = (int)$_POST['user_id'];
                $stmt_role = $this->pdo->prepare("SELECT id FROM roles WHERE name = 'Admin' LIMIT 1");
                $stmt_role->execute();
                $admin_role_id = $stmt_role->fetchColumn();
                if (!$admin_role_id) {
                    $this->pdo->exec("INSERT INTO roles (name) VALUES ('Admin')");
                    $admin_role_id = $this->pdo->lastInsertId();
                }
                $stmt_grant = $this->pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)");
                $stmt_grant->execute([$user_id, $admin_role_id]);
                $response = ['status' => 'success', 'message' => 'Pengguna berhasil dijadikan admin.'];
            } elseif (isset($_POST['bot_id']) && in_array($action, ['get-me', 'set-webhook', 'check-webhook', 'delete-webhook'])) {
                $bot_id = (int)$_POST['bot_id'];
                $stmt_token = $this->pdo->prepare("SELECT token FROM bots WHERE id = ?");
                $stmt_token->execute([$bot_id]);
                $token = $stmt_token->fetchColumn();
                if (!$token) throw new Exception("Bot tidak ditemukan.");

                $api = new TelegramAPI($token);
                $result = null;

                if ($action === 'set-webhook') {
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                    $webhook_url = $protocol . $_SERVER['HTTP_HOST'] . '/webhook/' . $bot_id;
                    $result = $api->setWebhook($webhook_url);
                } elseif ($action === 'check-webhook') {
                    $result = $api->getWebhookInfo();
                } elseif ($action === 'delete-webhook') {
                    $result = $api->deleteWebhook();
                } elseif ($action === 'get-me') {
                    $bot_info = $api->getMe();
                    if (!isset($bot_info['ok']) || !$bot_info['ok']) throw new Exception("Gagal getMe: " . ($bot_info['description'] ?? ''));
                    $stmt_update = $this->pdo->prepare("UPDATE bots SET first_name = ?, username = ? WHERE id = ?");
                    $stmt_update->execute([$bot_info['result']['first_name'], $bot_info['result']['username'] ?? null, $bot_id]);
                    $response = ['status' => 'success', 'message' => 'Info bot diperbarui.', 'data' => $bot_info['result']];
                    echo json_encode($response);
                    exit;
                }
                $response = ['status' => 'success', 'message' => 'Aksi berhasil.', 'data' => $result];
            }
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        echo json_encode($response);
        exit;
    }
}
