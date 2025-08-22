<?php
/**
 * Handler untuk aksi penghapusan pesan massal dari halaman chat.php.
 */
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/TelegramAPI.php';

// Cek apakah metode request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$pdo = get_db_connection();
if (!$pdo) {
    die("Koneksi database gagal.");
}

// Ambil data dari form
$message_ids = $_POST['message_ids'] ?? [];
$action = $_POST['action'] ?? '';
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$bot_id = isset($_POST['bot_id']) ? (int)$_POST['bot_id'] : 0;
$redirect_url = "chat.php?user_id=$user_id&bot_id=$bot_id";

// Validasi input
if (empty($message_ids) || !is_array($message_ids) || empty($action) || !$user_id || !$bot_id) {
    // Seharusnya ada pesan error, tapi untuk sekarang redirect saja
    header("Location: $redirect_url");
    exit;
}

// Ambil info bot untuk inisialisasi API
$stmt_bot = $pdo->prepare("SELECT token FROM bots WHERE id = ?");
$stmt_bot->execute([$bot_id]);
$bot_info = $stmt_bot->fetch();

if (!$bot_info) {
    die("Bot tidak ditemukan.");
}

$telegram_api = new TelegramAPI($bot_info['token']);
$success_count = 0;
$error_count = 0;

// Fetch message details in one go
$placeholders = implode(',', array_fill(0, count($message_ids), '?'));
$sql = "SELECT id, telegram_message_id, chat_id FROM messages WHERE id IN ($placeholders)";
$stmt = $pdo->prepare($sql);
$stmt->execute($message_ids);
$messages_to_delete = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Fetch as [id => [telegram_message_id, chat_id]] is not standard, let's fetch assoc

$stmt->execute($message_ids);
$messages_to_delete_map = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $msg) {
    $messages_to_delete_map[$msg['id']] = $msg;
}


foreach ($message_ids as $msg_id) {
    $msg_id = (int)$msg_id;
    $message = $messages_to_delete_map[$msg_id] ?? null;

    if (!$message) {
        $error_count++;
        continue;
    }

    $telegram_message_id = $message['telegram_message_id'];
    $chat_id = $message['chat_id'];
    $deleted_from_telegram = false;
    $deleted_from_db = false;

    try {
        // Hapus dari Telegram
        if (($action === 'delete_telegram' || $action === 'delete_both') && $telegram_message_id && $chat_id) {
            $result = $telegram_api->deleteMessage($chat_id, $telegram_message_id);
            if ($result['ok']) {
                $deleted_from_telegram = true;
            }
        }

        // Hapus dari Database
        if ($action === 'delete_db' || $action === 'delete_both') {
            $stmt_delete = $pdo->prepare("DELETE FROM messages WHERE id = ?");
            if ($stmt_delete->execute([$msg_id])) {
                $deleted_from_db = true;
            }
        }

        // Cek keberhasilan
        if (($action === 'delete_db' && $deleted_from_db) ||
            ($action === 'delete_telegram' && $deleted_from_telegram) ||
            ($action === 'delete_both' && $deleted_from_telegram && $deleted_from_db)) {
            $success_count++;
        } else {
            $error_count++;
        }

    } catch (Exception $e) {
        // Log error jika perlu
        $error_count++;
    }
}


// Redirect kembali dengan status
// (Implementasi pesan status yang lebih baik bisa menggunakan session flash messages)
header("Location: $redirect_url&delete_success=$success_count&delete_error=$error_count");
exit;
?>
