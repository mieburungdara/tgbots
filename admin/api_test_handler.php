<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/TelegramAPI.php';

$pdo = get_db_connection();
if (!$pdo) {
    echo json_encode(['error' => 'Koneksi database gagal.']);
    exit;
}

$action = $_GET['action'] ?? (json_decode(file_get_contents('php://input'), true)['action'] ?? null);

try {
    switch ($action) {
        case 'get_methods':
            handle_get_methods();
            break;
        case 'run_method':
            $data = json_decode(file_get_contents('php://input'), true);
            handle_run_method($pdo, $data);
            break;
        case 'get_history':
            handle_get_history($pdo, $_GET);
            break;
        default:
            throw new Exception('Aksi tidak valid.');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

function get_special_param_structures() {
    return [
        'parse_mode' => [
            'type' => 'dropdown',
            'choices' => ['HTML', 'Markdown', 'MarkdownV2']
        ],
        'reply_parameters' => [
            'type' => 'object',
            'properties' => [
                'message_id' => ['type' => 'number', 'isOptional' => true],
                'chat_id' => ['type' => 'text', 'isOptional' => true],
                'allow_sending_without_reply' => ['type' => 'boolean', 'isOptional' => true],
                'quote' => ['type' => 'text', 'isOptional' => true],
                // Note: quote_parse_mode could also be a dropdown. For simplicity, we'll keep it text for now.
                'quote_parse_mode' => ['type' => 'text', 'isOptional' => true],
            ]
        ],
        // For simplicity, complex types like markups are treated as a single JSON textarea.
        // A more advanced implementation could build UIs for these as well.
        'reply_markup' => ['type' => 'json'],
        'media' => ['type' => 'json'],
        'results' => ['type' => 'json'],
        'entities' => ['type' => 'json'],
        'message_ids' => ['type' => 'json'],
    ];
}


function handle_get_methods() {
    $reflection = new ReflectionClass('TelegramAPI');
    $public_methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

    $excluded_methods = ['__construct', '__call', 'escapeMarkdown', 'getBotId', 'sendLongMessage'];
    $special_params = get_special_param_structures();
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
                // This is a special parameter, merge its structure
                $param_info = array_merge($param_info, $special_params[$paramName]);
            } else {
                // Default to a simple text input
                $param_info['type'] = 'text';
            }
            $params_data[$paramName] = $param_info;
        }
        $methods_data[$methodName] = ['parameters' => $params_data];
    }
    ksort($methods_data);
    echo json_encode($methods_data);
}

function handle_run_method($pdo, $data) {
    $bot_id = $data['bot_id'] ?? null;
    $method = $data['method'] ?? null;
    $params = $data['params'] ?? [];

    if (!$bot_id || !$method) {
        throw new Exception("Bot ID dan metode diperlukan.");
    }

    $stmt = $pdo->prepare("SELECT token FROM bots WHERE id = ?");
    $stmt->execute([$bot_id]);
    $bot_token = $stmt->fetchColumn();

    if (!$bot_token) {
        throw new Exception("Bot tidak ditemukan.");
    }

    $telegram_api = new TelegramAPI($bot_token, $pdo, $bot_id);

    // Process params: remove empty strings, and for objects, filter empty properties
    $clean_params = [];
    foreach ($params as $key => $value) {
        if (is_array($value)) {
            $clean_object = array_filter($value, fn($v) => $v !== '' && $v !== null);
            if (!empty($clean_object)) {
                $clean_params[$key] = $clean_object;
            }
        } elseif ($value !== '' && $value !== null) {
            $clean_params[$key] = $value;
        }
    }

    // Re-create the final argument list in the correct order
    $reflection = new ReflectionMethod('TelegramAPI', $method);
    $final_args = [];
    foreach ($reflection->getParameters() as $param) {
        $paramName = $param->getName();
        if (isset($clean_params[$paramName])) {
            $final_args[] = $clean_params[$paramName];
        } else {
            // This will fail for non-optional params, which is expected.
            // For optional ones, it will pass `null` implicitly.
            if (!$param->isOptional()) {
                 throw new Exception("Parameter wajib '{$paramName}' tidak ada.");
            }
        }
    }

    $api_response = $telegram_api->$method(...$final_args);

    // Simpan ke log
    $log_stmt = $pdo->prepare(
        "INSERT INTO api_request_logs (bot_id, method, request_payload, response_payload) VALUES (?, ?, ?, ?)"
    );
    $log_stmt->execute([
        $bot_id,
        $method,
        json_encode($clean_params),
        json_encode($api_response)
    ]);

    echo json_encode(['success' => true, 'api_response' => $api_response]);
}

function handle_get_history($pdo, $query) {
    $bot_id = $query['bot_id'] ?? null;
    if (!$bot_id) {
        throw new Exception("Bot ID diperlukan.");
    }

    $page = isset($query['page']) ? (int)$query['page'] : 1;
    $items_per_page = 10;
    $offset = ($page - 1) * $items_per_page;

    // Hitung total entri
    $total_stmt = $pdo->prepare("SELECT COUNT(*) FROM api_request_logs WHERE bot_id = ?");
    $total_stmt->execute([$bot_id]);
    $total_items = $total_stmt->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);

    // Ambil data untuk halaman saat ini
    $stmt = $pdo->prepare(
        "SELECT * FROM api_request_logs WHERE bot_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?"
    );
    $stmt->execute([$bot_id, $items_per_page, $offset]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'history' => $history,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => (int)$total_pages,
            'total_items' => (int)$total_items
        ]
    ]);
}
?>
