<?php

namespace TGBot\Controllers\Admin;

use Exception;
use PDO;
use TGBot\Controllers\AppController;
use TGBot\TelegramAPI;

class XorAdminController extends AppController
{
    private $pdo;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Define BASE_PATH if it's not already defined
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', realpath(__DIR__ . '/../../../'));
        }
    }

    private function isAuthenticated()
    {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    }

    private function connectDb()
    {
        if ($this->pdo === null) {
            $this->pdo = \get_db_connection();
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
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
            } else {
                // The main login flow is handled by Auth/LoginController via the bot.
                // If a non-admin reaches this page, redirect them away.
                header("Location: /");
            }
            exit;
        }
        // Connect to DB only if authenticated.
        $this->connectDb();
    }

    /**
     * Handles the main view and POST requests for the page itself.
     */
    public function index()
    {
        $this->requireAuth();

        try {
            $data = [
                'page_title' => 'XOR Admin Panel',
                'active_tab' => $_GET['action'] ?? 'dashboard', // Default to dashboard
                'bots' => [],
                'users_with_roles' => [],
                'bot' => null,
                'settings' => [],
                'status_message' => $_SESSION['status_message'] ?? null,
                'db_message' => $_SESSION['db_message'] ?? '',
                'db_error' => $_SESSION['db_error'] ?? '',
            ];
            unset($_SESSION['status_message'], $_SESSION['db_message'], $_SESSION['db_error']);

            // Fetch data based on the active tab
            switch ($data['active_tab']) {
                case 'dashboard':
                    $dashboard_data = $this->getDashboardData();
                    $data = array_merge($data, $dashboard_data);
                    break;

                case 'bots':
                    // Data for this tab is fetched in the common data section below
                    break;

                case 'check_schema':
                    $schema_data = $this->getSchemaCheckReport();
                    $data = array_merge($data, $schema_data);
                    break;

                case 'edit_bot':
                    if (isset($_GET['id'])) {
                        $bot_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
                        $stmt = $this->pdo->prepare("SELECT id, first_name, username, token, created_at FROM bots WHERE id = ?");
                        $stmt->execute([$bot_id]);
                        $data['bot'] = $stmt->fetch();
                        if (!$data['bot']) {
                            header("Location: /xoradmin?action=bots");
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
                    } else {
                        header("Location: /xoradmin?action=bots");
                        exit;
                    }
                    break;

                case 'users':
                    $users_data = $this->getUsersManagementData();
                    $data = array_merge($data, $users_data);
                    break;

                case 'balance':
                    $balance_data = $this->getBalanceManagementData();
                    $data = array_merge($data, $balance_data);
                    break;

                case 'roles':
                    $sql = "
                        SELECT u.id, u.first_name, u.last_name, u.username, GROUP_CONCAT(r.name SEPARATOR ', ') as roles
                        FROM users u
                        LEFT JOIN user_roles ur ON u.id = ur.user_id
                        LEFT JOIN roles r ON ur.role_id = r.id
                        GROUP BY u.id
                        ORDER BY u.first_name, u.last_name
                    ";
                    $data['users_with_roles'] = $this->pdo->query($sql)->fetchAll();
                    break;

                case 'logs':
                    $log_type = $_GET['type'] ?? 'app';
                    $log_data = $this->getLogsData($log_type);
                    $data = array_merge($data, $log_data);
                    break;

                case 'debug_feed':
                    $debug_data = $this->getDebugFeedData();
                    $data = array_merge($data, $debug_data);
                    break;

                case 'content':
                    $content_data = $this->getContentManagementData();
                    $data = array_merge($data, $content_data);
                    break;

                case 'storage_channels':
                    $storage_channel_data = $this->getStorageChannelData();
                    $data = array_merge($data, $storage_channel_data);
                    unset($_SESSION['flash_message']);
                    break;

                case 'feature_channels':
                    $feature_channel_data = $this->getFeatureChannelData();
                    $data = array_merge($data, $feature_channel_data);
                    break;

                case 'analytics':
                    $analytics_data = $this->getAnalyticsData();
                    $data = array_merge($data, $analytics_data);
                    break;

                case 'api_test':
                    header('Location: /xoradmin/api_test_page'); // Temporary new route
                    exit();

                case 'db_reset':
                    // No data to fetch, just shows the view
                    break;
            }

            // Common data needed for multiple tabs, like the bot list for the sidebar
            $data['bots'] = $this->pdo->query("SELECT id, first_name, username, created_at FROM bots ORDER BY created_at DESC")->fetchAll();

            $this->view('admin/xoradmin/index', $data);
        } catch (Exception $e) {
            \app_log('Error in XorAdminController/index: ' . $e->getMessage(), 'error');
            $this->view('admin/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading the XOR admin page.'
            ], 'admin_layout');
        }
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

    public function adjustBalance()
    {
        $this->requireAuth();

        $user_id = (int)($_POST['user_id'] ?? 0);
        $amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
        $description = trim($_POST['description'] ?? '');
        $action = $_POST['action'];

        $admin_id = $_SESSION['user_id'] ?? null;
        if (!$admin_id) {
            $_SESSION['flash_message'] = "Sesi admin tidak valid atau telah berakhir. Silakan login kembali.";
            $_SESSION['flash_message_type'] = 'danger';
            header("Location: /xoradmin?action=balance");
            exit;
        }

        if ($user_id && $amount > 0) {
            $transaction_amount = ($action === 'add_balance') ? $amount : -$amount;
            $this->pdo->beginTransaction();
            try {
                $stmt_update_user = $this->pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                $stmt_update_user->execute([$transaction_amount, $user_id]);

                $stmt_insert_trans = $this->pdo->prepare("INSERT INTO balance_transactions (user_id, amount, type, description, admin_telegram_id) VALUES (?, ?, ?, ?, ?)");
                $stmt_insert_trans->execute([$user_id, $transaction_amount, 'admin_adjustment', $description, $admin_id]);

                $this->pdo->commit();
                $_SESSION['flash_message'] = "Saldo pengguna berhasil diperbarui.";
                $_SESSION['flash_message_type'] = 'success';
            } catch (Exception $e) {
                $this->pdo->rollBack();
                $_SESSION['flash_message'] = "Terjadi kesalahan: " . $e->getMessage();
                $_SESSION['flash_message_type'] = 'danger';
            }
        } else {
            $_SESSION['flash_message'] = "Input tidak valid.";
            $_SESSION['flash_message_type'] = 'danger';
        }

        $redirect_url = "/xoradmin?action=balance&" . http_build_query($_GET);
        header("Location: " . $redirect_url);
        exit;
    }

    public function migrate()
    {
        $this->requireAuth(true); // Use API auth check
        header('Content-Type: text/plain; charset=utf-8');

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Metode permintaan harus POST.");
            }

            ensure_migrations_table_exists($this->pdo);
            $executed_migrations = $this->pdo->query("SELECT migration_file FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
            $migration_files_path = BASE_PATH . '/migrations/';
            $all_migration_files = glob($migration_files_path . '*.{sql,php}', GLOB_BRACE);

            $migrations_to_run = [];
            foreach ($all_migration_files as $file_path) {
                $file_name = basename($file_path);
                if (!in_array($file_name, $executed_migrations)) {
                    $migrations_to_run[] = $file_name;
                }
            }
            sort($migrations_to_run);

            if (empty($migrations_to_run)) {
                echo "Database sudah paling baru. Tidak ada migrasi yang perlu dijalankan.";
            } else {
                echo "Memulai proses migrasi...\n\n";
                foreach ($migrations_to_run as $migration_file) {
                    echo "==================================================\n";
                    echo "Menjalankan migrasi: {$migration_file}\n";
                    echo "==================================================\n";

                    $file_path = $migration_files_path . $migration_file;
                    $extension = pathinfo($file_path, PATHINFO_EXTENSION);

                    try {
                        if ($extension === 'sql') {
                            $sql = file_get_contents($file_path);
                            $this->pdo->exec($sql);
                            echo "Skrip SQL berhasil dieksekusi.\n";
                        } elseif ($extension === 'php') {
                            require $file_path; // Output dari skrip ini akan langsung di-echo
                        }

                        $stmt = $this->pdo->prepare("INSERT INTO migrations (migration_file) VALUES (?)");
                        $stmt->execute([$migration_file]);
                        echo "\nStatus: SUKSES\n\n";

                    } catch (Throwable $e) {
                        throw new Exception("Gagal pada migrasi: {$migration_file}. Pesan Error: " . $e->getMessage(), 0, $e);
                    }
                }
                echo "Semua migrasi berhasil dijalankan.";
            }
        } catch (Throwable $e) {
            if (http_response_code() < 400) {
                http_response_code(500);
            }
            echo "Error Kritis: " . $e->getMessage() . "\n\nStack Trace:\n" . $e->getTraceAsString();
        }
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
            $user_id = isset($_POST['telegram_id']) ? (int)$_POST['telegram_id'] : (isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0);
            $channel_id = filter_input(INPUT_POST, 'channel_id', FILTER_VALIDATE_INT);
            $bot_id = filter_input(INPUT_POST, 'bot_id', FILTER_VALIDATE_INT);

            if ($action === 'get_storage_channel_bots' && $channel_id) {
                $repo = new \TGBot\Database\PrivateChannelBotRepository($this->pdo);
                $channelRepo = new \TGBot\Database\PrivateChannelRepository($this->pdo);
                $channel = $channelRepo->findByTelegramId($channel_id);
                if (!$channel) throw new Exception("Channel tidak ditemukan.");
                $bots = $repo->getBotsForChannel($channel['id']);
                $response = ['status' => 'success', 'bots' => $bots];
            }
            elseif ($action === 'add_bot_to_storage_channel' && $channel_id && $bot_id) {
                $repo = new \TGBot\Database\PrivateChannelBotRepository($this->pdo);
                $channelRepo = new \TGBot\Database\PrivateChannelRepository($this->pdo);
                $channel = $channelRepo->findByTelegramId($channel_id);
                if (!$channel) throw new Exception("Channel tidak ditemukan.");
                if ($repo->addBotToChannel($channel['id'], $bot_id)) {
                     $response = ['status' => 'success', 'message' => 'Bot berhasil ditambahkan.'];
                } else {
                    throw new Exception("Gagal menambahkan bot. Mungkin sudah ada.");
                }
            }
            elseif ($action === 'remove_bot_from_storage_channel' && $channel_id && $bot_id) {
                 $repo = new \TGBot\Database\PrivateChannelBotRepository($this->pdo);
                 $channelRepo = new \TGBot\Database\PrivateChannelRepository($this->pdo);
                 $channel = $channelRepo->findByTelegramId($channel_id);
                 if (!$channel) throw new Exception("Channel tidak ditemukan.");
                 if ($repo->removeBotFromChannel($channel['id'], $bot_id)) {
                     $response = ['status' => 'success', 'message' => 'Bot berhasil dihapus.'];
                 } else {
                     throw new Exception("Gagal menghapus bot.");
                 }
            }
            elseif ($action === 'verify_bot_in_storage_channel' && $channel_id && $bot_id) {
                $botRepo = new \TGBot\Database\BotRepository($this->pdo);
                $channelRepo = new \TGBot\Database\PrivateChannelRepository($this->pdo);
                $pcBotRepo = new \TGBot\Database\PrivateChannelBotRepository($this->pdo);

                $bot = $botRepo->findBotByTelegramId($bot_id);
                if (!$bot) throw new Exception("Bot tidak ditemukan.");
                $channel = $channelRepo->findByTelegramId($channel_id);
                if (!$channel) throw new Exception("Channel tidak ditemukan.");

                $telegram_api = new \TGBot\TelegramAPI($bot['token']);
                $member_info = $telegram_api->getChatMember($channel_id, $bot_id);

                if (!($member_info['ok'] ?? false)) throw new Exception("Gagal memeriksa status bot: " . ($member_info['description'] ?? ''));

                $status = $member_info['result']['status'];
                if (in_array($status, ['creator', 'administrator'])) {
                    if (!$pcBotRepo->isBotInChannel($channel['id'], $bot_id)) {
                        $pcBotRepo->addBotToChannel($channel['id'], $bot_id);
                    }
                    $pcBotRepo->verifyBotInChannel($channel['id'], $bot_id);
                    $response = ['status' => 'success', 'message' => "Verifikasi berhasil! Bot adalah '{$status}'."];
                } else {
                    $response = ['status' => 'error', 'message' => "Verifikasi Gagal. Bot bukan admin (status: {$status})."];
                }
            }
            if ($action === 'get_balance_log' && $user_id) {
                $stmt = $this->pdo->prepare("
                    SELECT bt.amount, bt.type, bt.description, bt.created_at, bt.admin_telegram_id, a.first_name AS admin_name
                    FROM balance_transactions bt
                    LEFT JOIN users a ON bt.admin_telegram_id = a.id
                    WHERE bt.user_id = ? ORDER BY bt.created_at DESC
                ");
                $stmt->execute([$user_id]);
                $response = ['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            } elseif ($action === 'get_sales_log' && $user_id) {
                $stmt = $this->pdo->prepare(
                    "SELECT s.price, s.purchased_at, mp.description as package_title, u_buyer.first_name as buyer_name
                     FROM sales s
                     JOIN media_packages mp ON s.package_id = mp.id
                     JOIN users u_buyer ON s.buyer_user_id = u_buyer.id
                     WHERE s.seller_user_id = ? ORDER BY s.purchased_at DESC"
                );
                $stmt->execute([$user_id]);
                $response = ['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            } elseif ($action === 'get_purchases_log' && $user_id) {
                $stmt = $this->pdo->prepare(
                    "SELECT s.price, s.purchased_at, mp.description as package_title
                     FROM sales s
                     JOIN media_packages mp ON s.package_id = mp.id
                     WHERE s.buyer_user_id = ? ORDER BY s.purchased_at DESC"
                );
                $stmt->execute([$user_id]);
                $response = ['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            } elseif ($action === 'make_admin' && $user_id) {
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
            } elseif ($action === 'forward_media' && isset($_POST['group_id']) && isset($_POST['bot_id'])) {
                $group_id = $_POST['group_id'];
                $bot_id = filter_input(INPUT_POST, 'bot_id', FILTER_VALIDATE_INT);

                $bot_stmt = $this->pdo->prepare("SELECT token FROM bots WHERE id = ?");
                $bot_stmt->execute([$bot_id]);
                $bot_token = $bot_stmt->fetchColumn();
                if (!$bot_token) throw new Exception("Bot tidak ditemukan.");

                $admins_stmt = $this->pdo->query("SELECT u.id FROM users u JOIN user_roles ur ON u.id = ur.user_id JOIN roles r ON ur.role_id = r.id WHERE r.name = 'Admin'");
                $admin_ids = $admins_stmt->fetchAll(PDO::FETCH_COLUMN);
                if (empty($admin_ids)) throw new Exception("Tidak ada admin yang ditemukan.");

                $is_single = strpos($group_id, 'single_') === 0;
                $db_id = $is_single ? (int)substr($group_id, 7) : $group_id;

                $base_sql = "SELECT mf.chat_id, mf.message_id, mf.caption, mf.created_at, u.first_name, u.username, u.id as telegram_id FROM media_files mf LEFT JOIN users u ON mf.user_id = u.id";
                $sql = $is_single ? $base_sql . " WHERE mf.id = ?" : $base_sql . " WHERE mf.media_group_id = ? ORDER BY mf.id ASC";

                $media_stmt = $this->pdo->prepare($sql);
                $media_stmt->execute([$db_id]);
                $media_files = $media_stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($media_files)) throw new Exception("File media tidak ditemukan.");

                $sender_info = $media_files[0];
                $info_caption = "--- ℹ️ Info Media ---\nPengirim: {$sender_info['first_name']}\nWaktu Kirim: {$sender_info['created_at']}";

                $api = new \TGBot\TelegramAPI($bot_token);
                $success_count = 0;
                $from_chat_id = $media_files[0]['chat_id'];

                foreach ($admin_ids as $admin_chat_id) {
                    $message_ids = array_column($media_files, 'message_id');
                    $result = $api->copyMessages($admin_chat_id, $from_chat_id, json_encode($message_ids));
                    if ($result && ($result['ok'] ?? false)) $success_count++;
                }

                $response = ['status' => 'success', 'message' => "Media berhasil diteruskan ke {$success_count} dari " . count($admin_ids) . " admin."];

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
    private function getDashboardData()
    {
        $selected_bot_id = isset($_GET['bot_id']) ? (int)$_GET['bot_id'] : null;
        $search_user = trim($_GET['search_user'] ?? '');

        $conversations = [];
        $channel_chats = [];
        $bot_exists = false;

        if ($selected_bot_id) {
            $stmt = $this->pdo->prepare("SELECT 1 FROM bots WHERE id = ?");
            $stmt->execute([$selected_bot_id]);
            $bot_exists = $stmt->fetchColumn();

            if ($bot_exists) {
                $params = [$selected_bot_id];
                $user_where_clause = '';
                if (!empty($search_user)) {
                    $user_where_clause = "AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.id = ?)";
                    $params = array_merge($params, ["%$search_user%", "%$search_user%", "%$search_user%", $search_user]);
                }

                $sql_users = "
                    SELECT u.id as telegram_id, u.first_name, u.username,
                           (SELECT text FROM messages m WHERE m.user_id = u.id AND m.bot_id = r.bot_id ORDER BY m.id DESC LIMIT 1) as last_message,
                           (SELECT telegram_timestamp FROM messages m WHERE m.user_id = u.id AND m.bot_id = r.bot_id ORDER BY m.id DESC LIMIT 1) as last_message_time
                    FROM users u
                    JOIN rel_user_bot r ON u.id = r.user_id
                    WHERE r.bot_id = ? {$user_where_clause}
                    ORDER BY last_message_time DESC";

                $stmt_users = $this->pdo->prepare($sql_users);
                $stmt_users->execute($params);
                $conversations = $stmt_users->fetchAll();

                if (empty($search_user)) {
                    $stmt_channels = $this->pdo->prepare(
                        "SELECT DISTINCT m.chat_id,
                            (SELECT raw_data FROM messages WHERE chat_id = m.chat_id AND bot_id = m.bot_id ORDER BY id DESC LIMIT 1) as last_message_raw,
                            (SELECT text FROM messages WHERE chat_id = m.chat_id AND bot_id = m.bot_id ORDER BY id DESC LIMIT 1) as last_message,
                            (SELECT telegram_timestamp FROM messages WHERE chat_id = m.chat_id AND bot_id = m.bot_id ORDER BY id DESC LIMIT 1) as last_message_time
                        FROM messages m
                        WHERE m.bot_id = ? AND m.chat_id < 0 ORDER BY last_message_time DESC"
                    );
                    $stmt_channels->execute([$selected_bot_id]);
                    $channel_chats = $stmt_channels->fetchAll();
                }
            }
        }

        return [
            'selected_bot_id' => $selected_bot_id,
            'search_user' => $search_user,
            'conversations' => $conversations,
            'channel_chats' => $channel_chats,
            'bot_exists' => $bot_exists
        ];
    }
    private function getSchemaCheckReport()
    {
        try {
            $sql_file_path = BASE_PATH . '/updated_schema.sql';
            if (!file_exists($sql_file_path)) {
                throw new Exception("File skema `updated_schema.sql` tidak ditemukan.");
            }
            $sql_content = file_get_contents($sql_file_path);

            $file_schema = $this->parseSchemaFromFile($sql_content);
            $live_schema = $this->getLiveSchema($this->pdo);
            $report = $this->compareSchemas($file_schema, $live_schema);

            return ['report' => $report, 'error' => null];

        } catch (Exception $e) {
            return ['report' => [], 'error' => 'Gagal memeriksa skema: ' . $e->getMessage()];
        }
    }

    /**
     * Mengurai skema database dari file SQL.
     */
    private function parseSchemaFromFile(string $sql_content): array
    {
        $schema = [];
        $lines = explode("\n", $sql_content);

        $in_create_table = false;
        $current_table_name = null;
        $current_table_lines = [];

        foreach ($lines as $line) {
            if (!$in_create_table) {
                // Memulai blok CREATE TABLE baru
                if (preg_match('/^CREATE TABLE(?: IF NOT EXISTS)?\s*`?(\w+)`?/i', $line, $matches)) {
                    $in_create_table = true;
                    $current_table_name = $matches[1];
                    $current_table_lines = [$line];
                }
            } else {
                $current_table_lines[] = $line;
                // Akhir dari blok CREATE TABLE, diidentifikasi dengan baris yang diakhiri titik koma
                if (str_ends_with(trim($line), ';')) {
                    $in_create_table = false;
                    $full_query = implode("\n", $current_table_lines);

                    $schema[$current_table_name] = [
                        'columns' => [],
                        'full_query' => $full_query, // Menggunakan blok yang ditangkap secara langsung
                    ];

                    // Menggunakan regex yang kuat untuk menemukan konten antara tanda kurung pertama dan terakhir sebelum ENGINE
                    if (preg_match('/\((.*)\)\s*ENGINE=/si', $full_query, $content_match)) {
                        $content = $content_match[1];
                        $column_lines = explode("\n", $content);

                        foreach ($column_lines as $col_line) {
                            $col_line = trim($col_line, " ,\r\n");
                            if (empty($col_line) || preg_match('/^(PRIMARY|UNIQUE|KEY|INDEX|CONSTRAINT|FOREIGN)/i', $col_line)) {
                                continue;
                            }
                            if (preg_match('/^`?(\w+)`?\s+(.*)/', $col_line, $col_match)) {
                                $column_name = $col_match[1];
                                $schema[$current_table_name]['columns'][$column_name] = $col_match[2];
                            }
                        }
                    }

                    $current_table_name = null;
                    $current_table_lines = [];
                }
            }
        }
        return $schema;
    }

    /**
     * Mendapatkan skema database yang sedang berjalan.
     */
    private function getLiveSchema(PDO $pdo): array
    {
        $schema = [];
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $schema[$table] = ['columns' => []];
            $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $schema[$table]['full_query'] = $row['Create Table'] ?? '';

            $columns_result = $pdo->query("SHOW FULL COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns_result as $col) {
                $definition = $col['Type'];
                if (isset($col['Collation']) && $col['Collation'] !== 'NULL') {
                    $definition .= ' CHARACTER SET ' . explode('_', $col['Collation'])[0] . ' COLLATE ' . $col['Collation'];
                }
                if ($col['Null'] === 'NO') {
                    $definition .= ' NOT NULL';
                }
                if ($col['Default'] !== null) {
                    $definition .= " DEFAULT '" . $col['Default'] . "'";
                } else if ($col['Null'] === 'YES') {
                    $definition .= ' DEFAULT NULL';
                }
                if (!empty($col['Comment'])) {
                    $definition .= " COMMENT '" . addslashes($col['Comment']) . "'";
                }
                $schema[$table]['columns'][$col['Field']] = $definition;
            }
        }
        return $schema;
    }

    /**
     * Membandingkan skema dari file dengan skema database yang sedang berjalan.
     */
    private function compareSchemas(array $file_schema, array $live_schema): array
    {
        $report = [
            'missing_tables' => [],
            'extra_tables' => [],
            'column_differences' => [],
        ];

        // Periksa tabel yang hilang dan kolom yang dimodifikasi
        foreach ($file_schema as $table_name => $table_data) {
            if (!isset($live_schema[$table_name])) {
                $report['missing_tables'][] = [
                    'name' => $table_name,
                    'query' => $table_data['full_query']
                ];
            } else {
                $diffs = ['missing' => [], 'extra' => [], 'modified' => []];
                $live_columns = $live_schema[$table_name]['columns'];

                // Periksa kolom yang hilang dan dimodifikasi
                foreach ($table_data['columns'] as $column_name => $file_definition) {
                    if (!isset($live_columns[$column_name])) {
                        $diffs['missing'][] = [
                            'name' => $column_name,
                            'query' => "ALTER TABLE `{$table_name}` ADD COLUMN `{$column_name}` {$file_definition};"
                        ];
                    } else {
                        // Normalisasi definisi untuk perbandingan
                        $norm_file_def = str_replace([' ', '`'], '', strtolower($file_definition));
                        $norm_live_def = str_replace([' ', '`'], '', strtolower($live_columns[$column_name]));
                        if ($norm_file_def !== $norm_live_def) {
                             $diffs['modified'][] = [
                                'name' => $column_name,
                                'file_def' => $file_definition,
                                'live_def' => $live_columns[$column_name],
                                'query' => "ALTER TABLE `{$table_name}` MODIFY COLUMN `{$column_name}` {$file_definition};"
                            ];
                        }
                    }
                }

                // Periksa kolom tambahan
                foreach ($live_columns as $column_name => $live_definition) {
                    if (!isset($table_data['columns'][$column_name])) {
                        $diffs['extra'][] = [
                            'name' => $column_name,
                            'query' => "ALTER TABLE `{$table_name}` DROP COLUMN `{$column_name}`;"
                        ];
                    }
                }

                if (!empty($diffs['missing']) || !empty($diffs['extra']) || !empty($diffs['modified'])) {
                    $report['column_differences'][$table_name] = $diffs;
                }
            }
        }

        // Periksa tabel tambahan
        foreach ($live_schema as $table_name => $table_data) {
            if (!isset($file_schema[$table_name])) {
                $report['extra_tables'][] = [
                    'name' => $table_name,
                    'query' => "DROP TABLE `{$table_name}`;"
                ];
            }
        }

        return $report;
    }
    private function getUsersManagementData()
    {
        // --- Logika Pencarian ---
        $search_term = $_GET['search'] ?? '';
        $where_clause = '';
        $params = [];
        if (!empty($search_term)) {
            $where_clause = "WHERE u.id = :search_id OR u.first_name LIKE :like_fn OR u.last_name LIKE :like_ln OR u.username LIKE :like_un";
            $params = [
                ':search_id' => $search_term,
                ':like_fn' => "%$search_term%",
                ':like_ln' => "%$search_term%",
                ':like_un' => "%$search_term%"
            ];
        }

        // --- Logika Pengurutan ---
        $sort_columns = ['id', 'first_name', 'username', 'status', 'roles'];
        $sort_by = in_array($_GET['sort'] ?? '', $sort_columns) ? $_GET['sort'] : 'id';
        $order = strtolower($_GET['order'] ?? '') === 'asc' ? 'ASC' : 'DESC';
        $order_by_column = $sort_by === 'roles' ? 'roles' : "u.{$sort_by}";
        $order_by_clause = "ORDER BY {$order_by_column} {$order}";

        // --- Logika Pagination ---
        $page = (int)($_GET['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;

        // Query untuk menghitung total pengguna
        $count_sql = "SELECT COUNT(*) FROM users u {$where_clause}";
        $count_stmt = $this->pdo->prepare($count_sql);
        $count_stmt->execute($params);
        $total_users = $count_stmt->fetchColumn();
        $total_pages = ceil($total_users / $limit);

        // --- Ambil data pengguna ---
        $sql = "
            SELECT u.id, u.first_name, u.last_name, u.username, u.status, GROUP_CONCAT(r.name SEPARATOR ', ') as roles
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            {$where_clause}
            GROUP BY u.id
            {$order_by_clause}
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $users = $stmt->fetchAll();

        return [
            'users' => $users,
            'total_users' => $total_users,
            'total_pages' => $total_pages,
            'page' => $page,
            'search_term' => $search_term,
            'sort_by' => $sort_by,
            'order' => $order,
        ];
    }
    private function getBalanceManagementData()
    {
        $search_term = $_GET['search'] ?? '';
        $page = (int)($_GET['page'] ?? 1);
        $sort_by = $_GET['sort'] ?? 'id';
        $order = strtolower($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $limit = 50;

        $allowed_sort_columns = ['id', 'first_name', 'username', 'balance', 'total_income', 'total_spending'];
        if (!in_array($sort_by, $allowed_sort_columns)) {
            $sort_by = 'id';
        }

        $where_clause = '';
        $params = [];
        if (!empty($search_term)) {
            $where_clause = "WHERE u.first_name LIKE :search1 OR u.last_name LIKE :search2 OR u.username LIKE :search3";
            $params = [':search1' => "%$search_term%", ':search2' => "%$search_term%", ':search3' => "%$search_term%"];
        }

        $count_sql = "SELECT COUNT(*) FROM users u {$where_clause}";
        $count_stmt = $this->pdo->prepare($count_sql);
        $count_stmt->execute($params);
        $total_users = $count_stmt->fetchColumn();
        $total_pages = ceil($total_users / $limit);
        $offset = ($page - 1) * $limit;

        $sql = "
            SELECT
                u.id as telegram_id, u.first_name, u.last_name, u.username, u.balance,
                (SELECT SUM(price) FROM sales WHERE seller_user_id = u.id) as total_income,
                (SELECT SUM(price) FROM sales WHERE buyer_user_id = u.id) as total_spending
            FROM users u
            {$where_clause}
            ORDER BY {$sort_by} {$order}
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $users_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'users_data' => $users_data,
            'total_pages' => $total_pages,
            'page' => $page,
            'sort_by' => $sort_by,
            'order' => $order,
            'search_term' => $search_term,
        ];
    }
    public function clearAppLogs()
    {
        $this->requireAuth();
        try {
            $this->pdo->query("TRUNCATE TABLE app_logs");
            \app_log("Tabel app_logs dibersihkan oleh admin.", 'system');
            $_SESSION['flash_message'] = "Semua log aplikasi berhasil dibersihkan.";
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = "Gagal membersihkan log: " . $e->getMessage();
        }
        header("Location: /xoradmin?action=logs&type=app");
        exit;
    }

    private function getLogsData($log_type = 'app')
    {
        $data = ['log_type' => $log_type];
        switch ($log_type) {
            case 'app':
                $items_per_page = 50;
                $stmt_levels = $this->pdo->query("SELECT DISTINCT level FROM app_logs ORDER BY level ASC");
                $data['log_levels'] = $stmt_levels->fetchAll(PDO::FETCH_COLUMN);
                $selected_level = isset($_GET['level']) && in_array($_GET['level'], $data['log_levels']) ? $_GET['level'] : 'all';
                $count_sql = "SELECT COUNT(*) FROM app_logs" . ($selected_level !== 'all' ? " WHERE level = :level" : "");
                $count_stmt = $this->pdo->prepare($count_sql);
                if ($selected_level !== 'all') $count_stmt->bindParam(':level', $selected_level, PDO::PARAM_STR);
                $count_stmt->execute();
                $total_items = $count_stmt->fetchColumn();
                $data['total_pages'] = ceil($total_items / $items_per_page);
                $data['current_page'] = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $offset = ($data['current_page'] - 1) * $items_per_page;
                $sql = "SELECT * FROM app_logs" . ($selected_level !== 'all' ? " WHERE level = :level" : "") . " ORDER BY id DESC LIMIT :limit OFFSET :offset";
                $stmt = $this->pdo->prepare($sql);
                if ($selected_level !== 'all') $stmt->bindParam(':level', $selected_level, PDO::PARAM_STR);
                $stmt->bindParam(':limit', $items_per_page, PDO::PARAM_INT);
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $data['logs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $data['selected_level'] = $selected_level;
                break;

            case 'media':
                // This logic needs TelegramErrorLogRepository
                $sql = "
                    SELECT mf.id, mf.type, mf.file_name, mf.caption, mf.file_size, mf.media_group_id, mf.created_at,
                           u.first_name as user_first_name, u.username as user_username,
                           b.first_name as bot_name, b.id as bot_id
                    FROM media_files mf
                    LEFT JOIN users u ON mf.user_id = u.id
                    LEFT JOIN messages m ON mf.message_id = m.telegram_message_id
                    LEFT JOIN bots b ON m.bot_id = b.id
                    WHERE b.id IS NOT NULL
                    ORDER BY mf.created_at DESC
                    LIMIT 100;
                ";
                $media_logs_flat = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
                $grouped_logs = [];
                foreach ($media_logs_flat as $log) {
                    $group_key = $log['media_group_id'] ?? 'single_' . $log['id'];
                    if (!isset($grouped_logs[$group_key])) {
                        $grouped_logs[$group_key] = ['items' => [], 'group_info' => [
                            'user' => $log['user_first_name'] . ($log['user_username'] ? ' (@' . $log['user_username'] . ')' : ''),
                            'bot' => $log['bot_name'], 'time' => $log['created_at']
                        ]];
                    }
                    $grouped_logs[$group_key]['items'][] = $log;
                }
                $data['grouped_logs'] = $grouped_logs;
                break;

            case 'telegram':
                $logRepo = new \TGBot\Database\TelegramErrorLogRepository($this->pdo);
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = 25;
                $total_records = $logRepo->countAll();
                $data['total_pages'] = ceil($total_records / $limit);
                $offset = ($page - 1) * $limit;
                $data['logs'] = $logRepo->findAll($limit, $offset);
                $data['page'] = $page;
                break;
        }
        return $data;
    }
    private function getDebugFeedData()
    {
        $raw_update_repo = new \TGBot\Database\RawUpdateRepository($this->pdo);
        $items_per_page = 25;
        $total_items = $raw_update_repo->countAll();
        $total_pages = ceil($total_items / $items_per_page);
        $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $offset = ($current_page - 1) * $items_per_page;
        $updates = $raw_update_repo->findAll($items_per_page, $offset);
        return [
            'updates' => $updates,
            'pagination' => [
                'current_page' => $current_page,
                'total_pages' => $total_pages
            ]
        ];
    }
    public function hardDeletePackage()
    {
        $this->requireAuth();
        $package_id_to_delete = filter_input(INPUT_POST, 'package_id', FILTER_VALIDATE_INT);

        if ($package_id_to_delete) {
            try {
                $packageRepo = new \TGBot\Database\MediaPackageRepository($this->pdo);
                $package_info = $packageRepo->find($package_id_to_delete);
                if ($package_info && isset($package_info['bot_id'])) {
                    $stmt_bot = $this->pdo->prepare("SELECT token FROM bots WHERE id = ?");
                    $stmt_bot->execute([$package_info['bot_id']]);
                    $bot_token = $stmt_bot->fetchColumn();
                    if ($bot_token) {
                        $telegram_api = new \TGBot\TelegramAPI($bot_token);
                        $files_to_delete = $packageRepo->hardDeletePackage($package_id_to_delete);
                        foreach ($files_to_delete as $file) {
                            if ($file['storage_channel_id'] && $file['storage_message_id']) {
                                $telegram_api->deleteMessage($file['storage_channel_id'], $file['storage_message_id']);
                            }
                        }
                    }
                } else {
                     $packageRepo->hardDeletePackage($package_id_to_delete);
                }
                $_SESSION['flash_message'] = "Paket #{$package_id_to_delete} berhasil dihapus permanen.";
            } catch (Exception $e) {
                $_SESSION['flash_message'] = "Error: " . $e->getMessage();
            }
        }
        header("Location: /xoradmin?action=content");
        exit;
    }

    public function storeStorageChannel()
    {
        $this->requireAuth();
        try {
            $channel_id = filter_input(INPUT_POST, 'channel_id', FILTER_VALIDATE_INT);
            $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
            if ($channel_id && $name) {
                $repo = new \TGBot\Database\PrivateChannelRepository($this->pdo);
                if ($repo->addChannel($channel_id, $name)) {
                    $_SESSION['flash_message'] = "Channel '{$name}' berhasil ditambahkan.";
                } else {
                    $_SESSION['flash_message'] = "Gagal menambahkan channel. Mungkin ID sudah ada.";
                }
            } else {
                $_SESSION['flash_message'] = "Data channel tidak valid.";
            }
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "Error: " . $e->getMessage();
        }
        header("Location: /xoradmin?action=storage_channels");
        exit;
    }

    public function createFeatureChannel()
    {
        $this->requireAuth();
        $botRepo = new \TGBot\Database\BotRepository($this->pdo);
        $bots = $botRepo->getAllBots();
        // This is a view action, it needs to be handled differently in xoradmin
        // For now, I'll just prepare the data. The view logic will be complex.
        $this->view('admin/feature_channels/form', [
            'page_title' => 'Tambah Konfigurasi Channel',
            'bots' => $bots,
            'config' => [],
            'action' => '/xoradmin/feature-channels/store'
        ]);
    }

    public function storeFeatureChannel()
    {
        $this->requireAuth();
        try {
            $repo = new \TGBot\Database\FeatureChannelRepository($this->pdo);
            $repo->create($_POST);
            $_SESSION['flash_message'] = 'Konfigurasi channel berhasil dibuat.';
        } catch (Exception $e) {
            $_SESSION['flash_message'] = 'Gagal membuat konfigurasi: ' . $e->getMessage();
        }
        header('Location: /xoradmin?action=feature_channels');
        exit();
    }

    public function editFeatureChannel()
    {
        $this->requireAuth();
        try {
            $id = (int)($_GET['id'] ?? 0);
            $repo = new \TGBot\Database\FeatureChannelRepository($this->pdo);
            $config = $repo->find($id);
            if (!$config) throw new Exception("Konfigurasi tidak ditemukan.");
            $botRepo = new \TGBot\Database\BotRepository($this->pdo);
            $bots = $botRepo->getAllBots();
            // This is a view action, needs to be handled differently in xoradmin
            $this->view('admin/feature_channels/form', [
                'page_title' => 'Edit Konfigurasi Channel',
                'bots' => $bots,
                'config' => $config,
                'action' => '/xoradmin/feature-channels/update?id=' . $id
            ]);
        } catch (Exception $e) {
            $_SESSION['flash_message'] = $e->getMessage();
            header('Location: /xoradmin?action=feature_channels');
            exit();
        }
    }

    public function updateFeatureChannel()
    {
        $this->requireAuth();
        try {
            $id = (int)($_GET['id'] ?? 0);
            $repo = new \TGBot\Database\FeatureChannelRepository($this->pdo);
            $repo->update($id, $_POST);
            $_SESSION['flash_message'] = 'Konfigurasi channel berhasil diperbarui.';
        } catch (Exception $e) {
            $_SESSION['flash_message'] = 'Gagal memperbarui konfigurasi: ' . $e->getMessage();
        }
        header('Location: /xoradmin?action=feature_channels');
        exit();
    }

    public function destroyFeatureChannel()
    {
        $this->requireAuth();
        try {
            $id = (int)($_POST['id'] ?? 0);
            $repo = new \TGBot\Database\FeatureChannelRepository($this->pdo);
            $repo->delete($id);
            $_SESSION['flash_message'] = 'Konfigurasi channel berhasil dihapus.';
        } catch (Exception $e) {
            $_SESSION['flash_message'] = 'Gagal menghapus konfigurasi: ' . $e->getMessage();
        }
        header('Location: /xoradmin?action=feature_channels');
        exit();
    }

    public function updateStorageChannel()
    {
        $this->requireAuth();
        try {
            $channel_id = filter_input(INPUT_POST, 'channel_id', FILTER_VALIDATE_INT);
            $new_name = filter_input(INPUT_POST, 'new_name', FILTER_SANITIZE_STRING);
            $new_channel_id = filter_input(INPUT_POST, 'new_channel_id', FILTER_VALIDATE_INT);
            if ($channel_id && $new_name && $new_channel_id) {
                $repo = new \TGBot\Database\PrivateChannelRepository($this->pdo);
                if ($repo->updateChannel($channel_id, $new_name, $new_channel_id)) {
                    $_SESSION['flash_message'] = "Channel berhasil diperbarui.";
                } else {
                    $_SESSION['flash_message'] = "Gagal memperbarui channel.";
                }
            } else {
                $_SESSION['flash_message'] = "Data untuk pembaruan channel tidak valid.";
            }
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "Error: " . $e->getMessage();
        }
        header("Location: /xoradmin?action=storage_channels");
        exit;
    }

    public function destroyStorageChannel()
    {
        $this->requireAuth();
        try {
            $channel_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($channel_id) {
                $repo = new \TGBot\Database\PrivateChannelRepository($this->pdo);
                if ($repo->deleteChannel($channel_id)) {
                    $_SESSION['flash_message'] = "Channel berhasil dihapus.";
                } else {
                    $_SESSION['flash_message'] = "Gagal menghapus channel.";
                }
            } else {
                $_SESSION['flash_message'] = "ID Channel tidak valid untuk penghapusan.";
            }
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "Error: " . $e->getMessage();
        }
        header("Location: /xoradmin?action=storage_channels");
        exit;
    }

    private function getContentManagementData()
    {
        $packageRepo = new \TGBot\Database\MediaPackageRepository($this->pdo);
        $packages = $packageRepo->findAll();
        return ['packages' => $packages];
    }
    private function getStorageChannelData()
    {
        $channelRepo = new \TGBot\Database\PrivateChannelRepository($this->pdo);
        $botRepo = new \TGBot\Database\BotRepository($this->pdo);
        return [
            'private_channels' => $channelRepo->getAllChannels(),
            'all_bots' => $botRepo->getAllBots(),
            'message' => $_SESSION['flash_message'] ?? null
        ];
    }
    private function getFeatureChannelData()
    {
        $repo = new \TGBot\Database\FeatureChannelRepository($this->pdo);
        return ['configs' => $repo->findAll()];
    }
    private function getAnalyticsData()
    {
        $analyticsRepo = new \TGBot\Database\AnalyticsRepository($this->pdo);
        $summary = $analyticsRepo->getGlobalSummary();
        $sales_by_day = $analyticsRepo->getSalesByDay(null, 30);
        $top_packages = $analyticsRepo->getTopSellingPackages(5);

        $chart_labels = [];
        $chart_data = [];
        foreach ($sales_by_day as $day) {
            $chart_labels[] = date("d M", strtotime($day['sales_date']));
            $chart_data[] = $day['daily_revenue'];
        }

        return [
            'summary' => $summary,
            'chart_labels' => $chart_labels,
            'chart_data' => $chart_data,
            'top_packages' => $top_packages
        ];
    }
}
