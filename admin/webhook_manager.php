<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/TelegramAPI.php';

// Fungsi untuk mengirim respons JSON dan keluar
function json_response($status, $data = null) {
    echo json_encode(['status' => $status, 'data' => $data]);
    exit;
}

// 1. Validasi Input
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || !isset($_POST['bot_id'])) {
    json_response('error', 'Permintaan tidak valid.');
}

$action = $_POST['action'];
$bot_id = filter_var($_POST['bot_id'], FILTER_VALIDATE_INT); // Ini adalah ID dari database

if (!$bot_id) {
    json_response('error', 'ID Bot internal tidak valid.');
}

// 2. Dapatkan Token Bot dari Database
$pdo = get_db_connection();
if (!$pdo) {
    json_response('error', 'Koneksi database gagal.');
}

$stmt = $pdo->prepare("SELECT token FROM bots WHERE id = ?");
$stmt->execute([$bot_id]);
$bot = $stmt->fetch();

if (!$bot) {
    json_response('error', "Bot dengan ID internal {$bot_id} tidak ditemukan.");
}
$bot_token = $bot['token'];

// 3. Inisialisasi Telegram API
$telegram = new TelegramAPI($bot_token);
$result = null;

// 4. Lakukan Aksi Berdasarkan 'action'
try {
    switch ($action) {
        case 'set':
            // Ekstrak ID bot numerik dari token
            $telegram_bot_id = explode(':', $bot_token)[0];
            if (!is_numeric($telegram_bot_id)) {
                json_response('error', 'Format token tidak valid, tidak bisa mengekstrak ID bot.');
            }

            // Buat URL webhook secara dinamis menggunakan ID bot dari token
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $domain = $_SERVER['HTTP_HOST'];
            // Buat path relatif yang benar dari /admin/ ke /webhook.php
            $webhook_path = str_replace('/admin', '', dirname($_SERVER['PHP_SELF'])) . '/webhook.php';
            $webhook_url = $protocol . $domain . $webhook_path . '?id=' . $telegram_bot_id;

            $result = $telegram->setWebhook($webhook_url);
            break;

        case 'check':
            $result = $telegram->getWebhookInfo();
            break;

        case 'delete':
            $result = $telegram->deleteWebhook();
            break;

        default:
            json_response('error', 'Aksi tidak dikenal.');
    }

    if ($result === false) {
        throw new Exception("Gagal berkomunikasi dengan API Telegram.");
    }

    json_response('success', $result);

} catch (Exception $e) {
    json_response('error', $e->getMessage());
}
