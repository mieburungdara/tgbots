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

function handle_get_methods() {
    $reflection = new ReflectionClass('TelegramAPI');
    $public_methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

    $excluded_methods = ['__construct', '__call', 'escapeMarkdown', 'getBotId', 'sendLongMessage'];
    $methods_data = [];

    foreach ($public_methods as $method) {
        $methodName = $method->getName();
        if (in_array($methodName, $excluded_methods) || strpos($methodName, 'handle') === 0 || strpos($methodName, 'apiRequest') === 0) {
            continue;
        }

        $params = [];
        foreach ($method->getParameters() as $param) {
            $params[$param->getName()] = [
                'isOptional' => $param->isOptional(),
            ];
        }
        $methods_data[$methodName] = ['parameters' => $params];
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
    $clean_params = array_filter($params, fn($val) => $val !== '' && $val !== null);

    $api_response = call_user_func_array([$telegram_api, $method], $clean_params);

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
