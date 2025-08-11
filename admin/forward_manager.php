<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/TelegramAPI.php';
require_once __DIR__ . '/../core/helpers.php';

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
    if (!$pdo) {
        throw new Exception("Koneksi database gagal.");
    }

    // 1. Ambil token bot
    $bot_stmt = $pdo->prepare("SELECT token FROM bots WHERE id = ?");
    $bot_stmt->execute([$bot_id]);
    $bot_token = $bot_stmt->fetchColumn();
    if (!$bot_token) {
        throw new Exception("Bot tidak ditemukan.");
    }

    // 2. Ambil daftar admin
    $admins_stmt = $pdo->query("SELECT telegram_id FROM users WHERE role = 'admin'");
    $admin_ids = $admins_stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($admin_ids)) {
        throw new Exception("Tidak ada pengguna dengan peran admin yang ditemukan.");
    }

    // 3. Ambil file media untuk grup
    $is_single = strpos($group_id, 'single_') === 0;
    $db_id = $is_single ? (int)substr($group_id, 7) : $group_id;
    $sql = $is_single
        ? "SELECT file_id, type, caption FROM media_files WHERE id = ?"
        : "SELECT file_id, type, caption FROM media_files WHERE media_group_id = ? ORDER BY id ASC";

    $media_stmt = $pdo->prepare($sql);
    $media_stmt->execute([$db_id]);
    $media_files = $media_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($media_files)) {
        throw new Exception("File media tidak ditemukan untuk grup ini.");
    }

    // 4. Kirim media ke setiap admin
    $api = new TelegramAPI($bot_token);
    $success_count = 0;
    $total_admins = count($admin_ids);

    foreach ($admin_ids as $admin_chat_id) {
        $result = false;
        if (count($media_files) > 1) {
            // Kirim sebagai media group
            $media_group = [];
            $first = true;
            foreach ($media_files as $file) {
                $media_item = ['type' => strtolower($file['type']), 'media' => $file['file_id']];
                // Hanya caption pertama yang akan ditampilkan di media group
                if ($first && !empty($file['caption'])) {
                    $media_item['caption'] = $file['caption'];
                    $first = false;
                }
                $media_group[] = $media_item;
            }
            $result = $api->sendMediaGroup($admin_chat_id, json_encode($media_group));
        } else {
            // Kirim sebagai media tunggal
            $file = $media_files[0];
            $type = ucfirst(strtolower($file['type']));
            $method_name = "send" . $type; // e.g., sendPhoto, sendVideo
            if (method_exists($api, $method_name)) {
                // Untuk API send<Type>, parameter ke-3 adalah caption.
                $result = $api->$method_name($admin_chat_id, $file['file_id'], $file['caption']);
            }
        }
        if ($result && ($result['ok'] ?? false)) {
            $success_count++;
        }
    }

    $response['status'] = 'success';
    $response['message'] = "Media berhasil diteruskan ke {$success_count} dari {$total_admins} admin.";

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    app_log("Forwarding error: " . $e->getMessage(), 'error');
}

echo json_encode($response);
exit;
