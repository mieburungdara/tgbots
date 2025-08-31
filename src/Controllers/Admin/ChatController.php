<?php

require_once __DIR__ . '/../BaseController.php';
require_once __DIR__ . '/../../../core/TelegramAPI.php';

class ChatController extends BaseController {

    public function index() {
        try {
            $pdo = get_db_connection();
            $telegram_id = isset($_GET['telegram_id']) ? (int)$_GET['telegram_id'] : 0;
            $bot_id = isset($_GET['bot_id']) ? (int)$_GET['bot_id'] : 0;

            if (!$telegram_id || !$bot_id) {
                header("Location: /admin/dashboard"); // Redirect to new dashboard
                exit;
            }

            $stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt_user->execute([$telegram_id]);
            $user_info = $stmt_user->fetch();

            $stmt_bot = $pdo->prepare("SELECT * FROM bots WHERE id = ?");
            $stmt_bot->execute([$bot_id]);
            $bot_info = $stmt_bot->fetch();

            if (!$user_info || !$bot_info) {
                // A proper error view would be better
                die("Pengguna atau bot tidak ditemukan.");
            }

            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = 50;
            $offset = ($page - 1) * $limit;

            $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND bot_id = ?");
            $count_stmt->execute([$telegram_id, $bot_id]);
            $total_messages = $count_stmt->fetchColumn();
            $total_pages = ceil($total_messages / $limit);

            $sql = "SELECT m.id, m.telegram_message_id, m.chat_id, m.text, m.raw_data, m.direction, m.created_at, mf.type as media_type\n                FROM messages m\n                LEFT JOIN media_files mf ON m.id = mf.message_id\n                WHERE m.user_id = ? AND m.bot_id = ?\n                ORDER BY m.id DESC\n                LIMIT ? OFFSET ?";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(1, $telegram_id, PDO::PARAM_STR);
            $stmt->bindValue(2, $bot_id, PDO::PARAM_STR);
            $stmt->bindValue(3, $limit, PDO::PARAM_INT);
            $stmt->bindValue(4, $offset, PDO::PARAM_INT);
            $stmt->execute();
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->view('admin/chat/index', [
                'page_title' => "Chat dengan " . htmlspecialchars($user_info['first_name']),
                'user_info' => $user_info,
                'bot_info' => $bot_info,
                'messages' => $messages,
                'total_messages' => $total_messages,
                'total_pages' => $total_pages,
                'page' => $page
            ], 'admin_layout');
        } catch (Exception $e) {
            app_log('Error in ChatController/index: ' . $e->getMessage(), 'error');
            $this->view('admin/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading the chat page.'
            ], 'admin_layout');
        }
    }

    public function reply() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['reply_message'])) {
                header('Location: /admin/dashboard');
                exit();
            }

            $pdo = get_db_connection();
            $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
            $bot_id = isset($_POST['bot_id']) ? (int)$_POST['bot_id'] : 0;
            $reply_text = trim($_POST['reply_text']);

            if ($user_id && $bot_id && !empty($reply_text)) {
                $stmt_bot = $pdo->prepare("SELECT token FROM bots WHERE id = ?");
                $stmt_bot->execute([$bot_id]);
                $bot_info = $stmt_bot->fetch();

                if ($bot_info) {
                    $telegram_api = new TelegramAPI($bot_info['token'], $pdo, $bot_id);
                    $telegram_api->sendMessage($user_id, $reply_text);
                }
            }

            // Redirect back to the chat page
            header("Location: /admin/chat?telegram_id=$user_id&bot_id=$bot_id");
            exit;
        } catch (Exception $e) {
            app_log('Error in ChatController/reply: ' . $e->getMessage(), 'error');
            // Redirect back with an error message
            $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
            $bot_id = isset($_POST['bot_id']) ? (int)$_POST['bot_id'] : 0;
            $_SESSION['flash_error'] = 'Failed to send reply: ' . $e->getMessage();
            header("Location: /admin/chat?telegram_id=$user_id&bot_id=$bot_id");
            exit;
        }
    }

    public function channel() {
        try {
            $pdo = get_db_connection();
            $chat_id = isset($_GET['chat_id']) ? (int)$_GET['chat_id'] : 0;
            $bot_id = isset($_GET['bot_id']) ? (int)$_GET['bot_id'] : 0;

            if (!$chat_id || !$bot_id) {
                header("Location: /admin/dashboard");
                exit;
            }

            $stmt_bot = $pdo->prepare("SELECT * FROM bots WHERE id = ?");
            $stmt_bot->execute([$bot_id]);
            $bot_info = $stmt_bot->fetch();
            if (!$bot_info) { die("Bot tidak ditemukan."); }

            $stmt_chat_info = $pdo->prepare("SELECT raw_data FROM messages WHERE chat_id = ? AND bot_id = ? ORDER BY id DESC LIMIT 1");
            $stmt_chat_info->execute([$chat_id, $bot_id]);
            $last_message_raw = $stmt_chat_info->fetchColumn();
            $chat_title = "Chat ID: $chat_id";
            if ($last_message_raw) {
                $raw = json_decode($last_message_raw, true);
                $chat_title = $raw['channel_post']['chat']['title'] ?? $raw['message']['chat']['title'] ?? $chat_title;
            }

            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = 50;
            $offset = ($page - 1) * $limit;

            $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE chat_id = ? AND bot_id = ?");
            $count_stmt->execute([$chat_id, $bot_id]);
            $total_messages = $count_stmt->fetchColumn();
            $total_pages = ceil($total_messages / $limit);

            $sql = "SELECT m.*, mf.type as media_type, u.first_name as sender_first_name\n                FROM messages m\n                LEFT JOIN media_files mf ON m.telegram_message_id = mf.message_id AND m.chat_id = mf.chat_id\n                LEFT JOIN users u ON m.user_id = u.id\n                WHERE m.chat_id = ? AND m.bot_id = ?\n                ORDER BY m.created_at DESC\n                LIMIT ? OFFSET ?";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(1, $chat_id, PDO::PARAM_INT);
            $stmt->bindValue(2, $bot_id, PDO::PARAM_INT);
            $stmt->bindValue(3, $limit, PDO::PARAM_INT);
            $stmt->bindValue(4, $offset, PDO::PARAM_INT);
            $stmt->execute();
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->view('admin/chat/channel', [
                'page_title' => "Riwayat Chat: " . htmlspecialchars($chat_title),
                'chat_title' => $chat_title,
                'bot_info' => $bot_info,
                'messages' => $messages,
                'total_messages' => $total_messages,
                'total_pages' => $total_pages,
                'page' => $page,
                'chat_id' => $chat_id
            ], 'admin_layout');
        } catch (Exception $e) {
            app_log('Error in ChatController/channel: ' . $e->getMessage(), 'error');
            $this->view('admin/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading the channel chat page.'
            ], 'admin_layout');
        }
    }

    public function delete() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                header("Location: /admin/dashboard");
                exit;
            }

            $pdo = get_db_connection();
            $message_ids = $_POST['message_ids'] ?? [];
            $action = $_POST['action'] ?? '';
            $bot_id = isset($_POST['bot_id']) ? (int)$_POST['bot_id'] : 0;

            // Determine redirect URL based on whether user_id or chat_id is present
            $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
            $chat_id = isset($_POST['chat_id']) ? (int)$_POST['chat_id'] : 0;
            $redirect_url = '/admin/dashboard';

            if ($user_id && $bot_id) {
                $redirect_url = "/admin/chat?telegram_id=$user_id&bot_id=$bot_id";
            } elseif ($chat_id && $bot_id) {
                $redirect_url = "/admin/channel_chat?chat_id=$chat_id&bot_id=$bot_id";
            }

            if (empty($message_ids) || empty($action) || !$bot_id) {
                header("Location: /admin/dashboard");
                exit;
            }

            $stmt_bot = $pdo->prepare("SELECT token FROM bots WHERE id = ?");
            $stmt_bot->execute([$bot_id]);
            $bot_info = $stmt_bot->fetch();

            if (!$bot_info) { die("Bot tidak ditemukan."); }

            $telegram_api = new TelegramAPI($bot_info['token']);

            $placeholders = implode(',', array_fill(0, count($message_ids), '?'));
            $stmt = $pdo->prepare("SELECT id, telegram_message_id, chat_id FROM messages WHERE id IN ($placeholders)");
            $stmt->execute($message_ids);
            $messages_to_delete = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($messages_to_delete as $msg) {
                if ($action === 'delete_telegram' || $action === 'delete_both') {
                    if ($msg['telegram_message_id'] && $msg['chat_id']) {
                        $telegram_api->deleteMessage($msg['chat_id'], $msg['telegram_message_id']);
                    }
                }
                if ($action === 'delete_db' || $action === 'delete_both') {
                    $stmt_delete = $pdo->prepare("DELETE FROM messages WHERE id = ?");
                    $stmt_delete->execute([$msg['id']]);
                }
            }

            header("Location: " . $redirect_url);
            exit;
        } catch (Exception $e) {
            app_log('Error in ChatController/delete: ' . $e->getMessage(), 'error');
            // Redirect back with an error message
            $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
            $bot_id = isset($_POST['bot_id']) ? (int)$_POST['bot_id'] : 0;
            $chat_id = isset($_POST['chat_id']) ? (int)$_POST['chat_id'] : 0;
            $redirect_url = '/admin/dashboard';
            if ($user_id && $bot_id) {
                $redirect_url = "/admin/chat?telegram_id=$user_id&bot_id=$bot_id";
            } elseif ($chat_id && $bot_id) {
                $redirect_url = "/admin/channel_chat?chat_id=$chat_id&bot_id=$bot_id";
            }
            $_SESSION['flash_error'] = 'Failed to delete messages: ' . $e->getMessage();
            header("Location: " . $redirect_url);
            exit;
        }
    }
}
