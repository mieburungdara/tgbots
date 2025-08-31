<?php

namespace TGBot\Controllers\Admin;

use Exception;
use PDO;
use TGBot\Controllers\BaseController;
use TGBot\TelegramAPI;

class ForwardManagerController extends BaseController
{
    public function forward()
    {
        header('Content-Type: application/json');
        $response = ['status' => 'error', 'message' => 'Permintaan tidak valid.'];

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Metode permintaan tidak valid.");
            }

            $group_id = $_POST['group_id'] ?? null;
            $bot_id = filter_input(INPUT_POST, 'bot_id', FILTER_VALIDATE_INT);

            if (!$group_id || !$bot_id) {
                throw new Exception("ID Grup atau ID Bot tidak ada.");
            }

            $pdo = get_db_connection();

            $bot_stmt = $pdo->prepare("SELECT token FROM bots WHERE id = ?");
            $bot_stmt->execute([$bot_id]);
            $bot_token = $bot_stmt->fetchColumn();
            if (!$bot_token) {
                throw new Exception("Bot tidak ditemukan.");
            }

            $admins_stmt = $pdo->query("
                SELECT u.id FROM users u
                JOIN user_roles ur ON u.id = ur.user_id
                JOIN roles r ON ur.role_id = r.id
                WHERE r.name = 'Admin'
            ");
            $admin_ids = $admins_stmt->fetchAll(PDO::FETCH_COLUMN);
            if (empty($admin_ids)) {
                throw new Exception("Tidak ada admin yang ditemukan.");
            }

            $is_single = strpos($group_id, 'single_') === 0;
            $db_id = $is_single ? (int)substr($group_id, 7) : $group_id;

            $base_sql = "
                SELECT mf.chat_id, mf.message_id, mf.caption, mf.created_at,
                       u.first_name, u.username, u.id as telegram_id
                FROM media_files mf
                LEFT JOIN users u ON mf.user_id = u.id
            ";
            $sql = $is_single
                ? $base_sql . " WHERE mf.id = ?"
                : $base_sql . " WHERE mf.media_group_id = ? ORDER BY mf.id ASC";

            $media_stmt = $pdo->prepare($sql);
            $media_stmt->execute([$db_id]);
            $media_files = $media_stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($media_files)) {
                throw new Exception("File media tidak ditemukan.");
            }
            if (empty($media_files[0]['chat_id']) || empty($media_files[0]['message_id'])) {
                throw new Exception("Media ini tidak dapat diteruskan karena data sumbernya tidak lengkap.");
            }

            $sender_info = $media_files[0];
            $sender_name = htmlspecialchars($sender_info['first_name'] ?? 'Pengguna Tidak Dikenal');
            $sender_username = $sender_info['username'] ? " (@" . htmlspecialchars($sender_info['username']) . ")" : "";
            $info_caption = "
--- ℹ️ Info Media ---
Pengirim: {$sender_name}{$sender_username}
ID Pengirim: `{$sender_info['telegram_id']}`
Waktu Kirim: {$sender_info['created_at']}
--------------------
";
            $api = new TelegramAPI($bot_token);
            $success_count = 0;
            $total_admins = count($admin_ids);
            $from_chat_id = $media_files[0]['chat_id'];

            foreach ($admin_ids as $admin_chat_id) {
                $result_ok = false;
                if (count($media_files) > 1) {
                    $api->sendMessage($admin_chat_id, $info_caption, 'Markdown');
                    $message_ids = array_column($media_files, 'message_id');
                    $result = $api->copyMessages($admin_chat_id, $from_chat_id, json_encode($message_ids));
                    $result_ok = $result && ($result['ok'] ?? false);
                } else {
                    $file = $media_files[0];
                    $full_caption = $info_caption;
                    if (!empty($file['caption'])) {
                        $full_caption .= "\n" . $file['caption'];
                    }
                    $result = $api->copyMessage($admin_chat_id, $from_chat_id, $file['message_id'], $full_caption, 'Markdown');
                    $result_ok = $result && ($result['ok'] ?? false);
                }
                if ($result_ok) $success_count++;
            }

            $response['status'] = 'success';
            $response['message'] = "Media berhasil diteruskan ke {$success_count} dari {$total_admins} admin.";

        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
            app_log("Forwarding error: " . $e->getMessage(), 'error');
        }

        echo json_encode($response);
        exit;
    }
}
