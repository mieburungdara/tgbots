<?php
/**
 * Handler Backend untuk Meneruskan Media (Admin).
 *
 * File ini menangani permintaan AJAX dari halaman `chat.php` untuk meneruskan
 * sebuah media atau grup media dari seorang pengguna ke semua pengguna
 * dengan peran 'admin'.
 *
 * Logika:
 * 1. Validasi permintaan (harus POST, parameter `group_id` dan `bot_id` ada).
 * 2. Ambil token bot dan daftar ID admin dari database.
 * 3. Ambil detail file media berdasarkan `group_id`.
 * 4. Buat caption informasi yang berisi detail pengirim.
 * 5. Iterasi melalui setiap admin dan kirim media (sebagai sendMediaGroup atau send<Type>).
 * 6. Kembalikan respons JSON yang merangkum hasil operasi.
 */
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/TelegramAPI.php';
require_once __DIR__ . '/../core/helpers.php';

$response = ['status' => 'error', 'message' => 'Permintaan tidak valid.'];

try {
    // Validasi dasar permintaan
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

    // 1. Ambil token bot yang akan digunakan untuk mengirim
    $bot_stmt = $pdo->prepare("SELECT token FROM bots WHERE id = ?");
    $bot_stmt->execute([$bot_id]);
    $bot_token = $bot_stmt->fetchColumn();
    if (!$bot_token) {
        throw new Exception("Bot tidak ditemukan.");
    }

    // 2. Ambil daftar semua pengguna admin sebagai tujuan
    $admins_stmt = $pdo->query("SELECT telegram_id FROM users WHERE role = 'admin'");
    $admin_ids = $admins_stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($admin_ids)) {
        throw new Exception("Tidak ada pengguna dengan peran admin yang ditemukan.");
    }

    // 3. Ambil semua file media yang terkait dengan grup/item yang diteruskan
    $is_single = strpos($group_id, 'single_') === 0;
    $db_id = $is_single ? (int)substr($group_id, 7) : $group_id;

    $base_sql = "
        SELECT mf.file_id, mf.type, mf.caption, mf.created_at, mf.file_size,
               u.first_name, u.username, u.telegram_id
        FROM media_files mf
        LEFT JOIN users u ON mf.user_id = u.telegram_id
    ";
    $sql = $is_single
        ? $base_sql . " WHERE mf.id = ?" // Ambil satu file jika ini media tunggal
        : $base_sql . " WHERE mf.media_group_id = ? ORDER BY mf.id ASC"; // Ambil semua file jika ini grup

    $media_stmt = $pdo->prepare($sql);
    $media_stmt->execute([$db_id]);
    $media_files = $media_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($media_files)) {
        throw new Exception("File media tidak ditemukan untuk grup ini.");
    }

    // 4. Buat caption informasi untuk ditambahkan ke pesan
    $sender_info = $media_files[0]; // Info pengirim sama untuk semua item dalam grup
    $file_size_kb = $sender_info['file_size'] ? round($sender_info['file_size'] / 1024, 2) . ' KB' : 'N/A';
    $info_caption = "
--- ℹ️ Info Media ---
Pengirim: " . htmlspecialchars($sender_info['first_name']) . ($sender_info['username'] ? " (@" . htmlspecialchars($sender_info['username']) . ")" : "") . "
ID Pengirim: `{$sender_info['telegram_id']}`
Waktu Kirim: {$sender_info['created_at']}
Ukuran File: {$file_size_kb}
--------------------
";

    // 5. Kirim media ke setiap admin
    $api = new TelegramAPI($bot_token);
    $success_count = 0;
    $total_admins = count($admin_ids);

    foreach ($admin_ids as $admin_chat_id) {
        $result = false;
        // Jika ada lebih dari satu file, kirim sebagai media group
        if (count($media_files) > 1) {
            $media_group = [];
            $first = true;
            foreach ($media_files as $file) {
                $media_item = ['type' => strtolower($file['type']), 'media' => $file['file_id']];
                // Tambahkan caption hanya ke item pertama dalam grup
                if ($first) {
                    $full_caption = $info_caption;
                    if (!empty($file['caption'])) {
                        $full_caption .= "\n" . $file['caption'];
                    }
                    $media_item['caption'] = $full_caption;
                    $media_item['parse_mode'] = 'Markdown';
                    $first = false;
                }
                $media_group[] = $media_item;
            }
            $result = $api->sendMediaGroup($admin_chat_id, json_encode($media_group));
        } else {
            // Jika hanya satu file, kirim sebagai media tunggal
            $file = $media_files[0];
            $full_caption = $info_caption;
            if (!empty($file['caption'])) {
                $full_caption .= "\n" . $file['caption'];
            }

            // Panggil metode yang sesuai (sendPhoto, sendVideo, dll.)
            $type = ucfirst(strtolower($file['type']));
            $method_name = "send" . $type;
            if (method_exists($api, $method_name)) {
                $result = $api->$method_name($admin_chat_id, $file['file_id'], $full_caption);
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
