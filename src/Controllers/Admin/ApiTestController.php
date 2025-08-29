<?php

require_once __DIR__ . '/../BaseController.php';
require_once __DIR__ . '/../../../core/TelegramAPI.php';

class ApiTestController extends BaseController
{
    private $pdo;

    public function __construct()
    {
        parent::__construct();
        $this->pdo = get_db_connection();
    }

    /**
     * Menampilkan halaman utama untuk Tes API.
     */
    public function index()
    {
        $bots = $this->pdo->query("SELECT id, first_name, token FROM bots ORDER BY first_name")->fetchAll();

        $this->view('admin/api_test/index', [
            'page_title' => 'Tes API Langsung',
            'bots' => $bots
        ], 'admin_layout');
    }

    /**
     * Menangani permintaan AJAX untuk metode API.
     * Bertindak sebagai router untuk berbagai aksi.
     */
    public function handle()
    {
        header('Content-Type: application/json');
        $action = $_GET['action'] ?? (json_decode(file_get_contents('php://input'), true)['action'] ?? null);

        try {
            switch ($action) {
                case 'get_methods':
                    $this->getMethods();
                    break;
                case 'run_method':
                    $data = json_decode(file_get_contents('php://input'), true);
                    $this->runMethod($data);
                    break;
                case 'get_history':
                    $this->getHistory($_GET);
                    break;
                default:
                    throw new Exception('Aksi tidak valid.');
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Mengambil daftar metode publik dari kelas TelegramAPI menggunakan Reflection.
     */
    private function getMethods()
    {
        $reflection = new ReflectionClass('TelegramAPI');
        $public_methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        $excluded_methods = ['__construct', '__call', 'escapeMarkdown', 'getBotId', 'sendLongMessage'];
        $special_params = $this->getSpecialParamStructures();
        $methods_data = [];

        foreach ($public_methods as $method) {
            $methodName = $method->getName();
            if (in_array($methodName, $excluded_methods) || strpos($methodName, 'handle') === 0 || strpos($methodName, 'apiRequest') === 0) {
                continue;
            }

            $params_data = [];
            foreach ($method->getParameters() as $param) {
                $paramName = $param->getName();
                $param_info = [
                    'name' => $paramName,
                    'isOptional' => $param->isOptional(),
                ];

                if (isset($special_params[$paramName])) {
                    $param_info = array_merge($param_info, $special_params[$paramName]);
                } else {
                    $param_info['type'] = 'text';
                }
                $params_data[$paramName] = $param_info;
            }
            $methods_data[$methodName] = ['parameters' => $params_data];
        }
        ksort($methods_data);
        echo json_encode($methods_data);
    }

    /**
     * Menjalankan metode API Telegram yang dipilih.
     */
    private function runMethod($data)
    {
        $bot_id = $data['bot_id'] ?? null;
        $method = $data['method'] ?? null;
        $params = $data['params'] ?? [];

        if (!$bot_id || !$method) throw new Exception("Bot ID dan metode diperlukan.");

        $stmt = $this->pdo->prepare("SELECT token FROM bots WHERE id = ?");
        $stmt->execute([$bot_id]);
        $bot_token = $stmt->fetchColumn();

        if (!$bot_token) throw new Exception("Bot tidak ditemukan.");

        $telegram_api = new TelegramAPI($bot_token, $this->pdo, $bot_id);

        $clean_params = [];
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $clean_object = array_filter($value, fn($v) => $v !== '' && $v !== null);
                if (!empty($clean_object)) $clean_params[$key] = $clean_object;
            } elseif ($value !== '' && $value !== null) {
                $clean_params[$key] = $value;
            }
        }

        if (isset($clean_params['message_thread_id']) && is_numeric($clean_params['message_thread_id'])) {
            $clean_params['message_thread_id'] = (int)$clean_params['message_thread_id'];
        }

        $reflection = new ReflectionMethod('TelegramAPI', $method);
        $final_args = [];
        foreach ($reflection->getParameters() as $param) {
            $paramName = $param->getName();
            if (isset($clean_params[$paramName])) {
                $final_args[] = $clean_params[$paramName];
            } else {
                if ($param->isOptional()) {
                    $final_args[] = $param->getDefaultValue();
                } else {
                    throw new Exception("Parameter wajib '{$paramName}' tidak ada.");
                }
            }
        }

        $api_response = $telegram_api->$method(...$final_args);

        $log_stmt = $this->pdo->prepare("INSERT INTO api_request_logs (bot_id, method, request_payload, response_payload) VALUES (?, ?, ?, ?)");
        $log_stmt->execute([$bot_id, $method, json_encode($clean_params), json_encode($api_response)]);

        echo json_encode(['success' => true, 'api_response' => $api_response]);
    }

    /**
     * Mengambil riwayat permintaan API.
     */
    private function getHistory($query)
    {
        $bot_id = $query['bot_id'] ?? null;
        if (!$bot_id) throw new Exception("Bot ID diperlukan.");

        $page = isset($query['page']) ? (int)$query['page'] : 1;
        $items_per_page = 10;
        $offset = ($page - 1) * $items_per_page;

        $total_stmt = $this->pdo->prepare("SELECT COUNT(*) FROM api_request_logs WHERE bot_id = ?");
        $total_stmt->execute([$bot_id]);
        $total_items = $total_stmt->fetchColumn();
        $total_pages = ceil($total_items / $items_per_page);

        $stmt = $this->pdo->prepare("SELECT * FROM api_request_logs WHERE bot_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$bot_id, $items_per_page, $offset]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'history' => $history,
            'pagination' => ['current_page' => $page, 'total_pages' => (int)$total_pages, 'total_items' => (int)$total_items]
        ]);
    }

    /**
     * Helper untuk mendefinisikan struktur parameter kustom.
     */
    private function getSpecialParamStructures()
    {
        return [
            'parse_mode' => ['type' => 'dropdown', 'choices' => ['HTML', 'Markdown', 'MarkdownV2']],
            'reply_parameters' => [
                'type' => 'object',
                'properties' => [
                    'message_id' => ['type' => 'number', 'isOptional' => true],
                    'chat_id' => ['type' => 'text', 'isOptional' => true],
                    'allow_sending_without_reply' => ['type' => 'boolean', 'isOptional' => true],
                    'quote' => ['type' => 'text', 'isOptional' => true],
                    'quote_parse_mode' => ['type' => 'text', 'isOptional' => true],
                ]
            ],
            'reply_markup' => ['type' => 'json'], 'media' => ['type' => 'json'],
            'results' => ['type' => 'json'], 'entities' => ['type' => 'json'],
            'message_ids' => ['type' => 'json'],
        ];
    }
}
