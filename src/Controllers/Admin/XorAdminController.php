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
}
