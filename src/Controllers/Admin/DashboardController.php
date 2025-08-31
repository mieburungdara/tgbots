<?php

namespace TGBot\Controllers\Admin;

require_once __DIR__ . '/../BaseController.php';

use Exception;
use TGBot\Controllers\BaseController;

class DashboardController extends BaseController {

    public function index() {
        try {
            // The BaseController's constructor already handles session start and auth check.

            $pdo = get_db_connection();

            // The helper function 'get_initials' is loaded via the front controller,
            // so it will be available in the view scope.

            // Ambil semua bot untuk sidebar
            $bots = $pdo->query("SELECT id, first_name FROM bots ORDER BY first_name ASC")->fetchAll();

            // Dapatkan parameter dari URL
            $selected_bot_id = isset($_GET['bot_id']) ? (int)$_GET['bot_id'] : null;
            $search_user = trim($_GET['search_user'] ?? '');

            $conversations = [];
            $channel_chats = [];
            $bot_exists = false;

            if ($selected_bot_id) {
                // Verifikasi bot ada
                $stmt = $pdo->prepare("SELECT 1 FROM bots WHERE id = ?");
                $stmt->execute([$selected_bot_id]);
                $bot_exists = $stmt->fetchColumn();

                if ($bot_exists) {
                    // --- Ambil percakapan PRIBADI ---
                    $params = [$selected_bot_id];
                    $user_where_clause = '';
                    if (!empty($search_user)) {
                        $user_where_clause = "AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.id = ?)";
                        $params = array_merge($params, ["%$search_user%", "%$search_user%", "%$search_user%", $search_user]);
                    }

                    // Note: This is a complex query. In a future step, this could be
                    // moved to a dedicated ConversationRepository for cleaner code.
                    $sql_users = "
                        SELECT u.id as telegram_id, u.first_name, u.username,
                               (SELECT text FROM messages m WHERE m.user_id = u.id AND m.bot_id = r.bot_id ORDER BY m.id DESC LIMIT 1) as last_message,
                               (SELECT telegram_timestamp FROM messages m WHERE m.user_id = u.id AND m.bot_id = r.bot_id ORDER BY m.id DESC LIMIT 1) as last_message_time
                        FROM users u
                        JOIN rel_user_bot r ON u.id = r.user_id
                        WHERE r.bot_id = ? {$user_where_clause}
                        ORDER BY last_message_time DESC";

                    $stmt_users = $pdo->prepare($sql_users);
                    $stmt_users->execute($params);
                    $conversations = $stmt_users->fetchAll();

                    // --- Ambil percakapan CHANNEL dan GRUP (hanya jika tidak ada filter user) ---
                    if (empty($search_user)) {
                        $stmt_channels = $pdo->prepare(
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

            // Pass data to the view.
            // We will create the layout file in the next step.
            $this->view('admin/dashboard/index', [
                'page_title' => 'Percakapan',
                'bots' => $bots,
                'selected_bot_id' => $selected_bot_id,
                'search_user' => $search_user,
                'conversations' => $conversations,
                'channel_chats' => $channel_chats,
                'bot_exists' => $bot_exists
            ], 'admin_layout'); // Using a layout
        } catch (Exception $e) {
            app_log('Error in DashboardController/index: ' . $e->getMessage(), 'error');
            $this->view('admin/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading the dashboard.'
            ], 'admin_layout');
        }
    }
}
