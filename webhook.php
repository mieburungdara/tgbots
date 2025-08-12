<?php

// Sertakan file-file yang diperlukan
require_once __DIR__ . '/core/database.php';
require_once __DIR__ . '/core/TelegramAPI.php';
require_once __DIR__ . '/core/helpers.php';

// Seluruh logika webhook dibungkus dalam blok try-catch untuk menangani semua kemungkinan error.
try {

// --- 1. Validasi ID Bot Telegram dari URL ---
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    http_response_code(400); // Bad Request
    app_log("Webhook Error: ID bot dari URL tidak valid atau tidak ada.", 'bot');
    echo "Error: ID bot tidak valid.";
    exit;
}
$telegram_bot_id = $_GET['id'];

// --- 2. Dapatkan koneksi database dan token bot ---
$pdo = get_db_connection();
if (!$pdo) {
    http_response_code(500); // Internal Server Error
    // Pesan ini sudah dicatat di dalam get_db_connection()
    exit;
}

// Cari bot berdasarkan ID bot dari token, bukan ID internal
$stmt = $pdo->prepare("SELECT id, token FROM bots WHERE token LIKE ?");
$stmt->execute(["{$telegram_bot_id}:%"]);
$bot = $stmt->fetch();

if (!$bot) {
    http_response_code(404); // Not Found
    app_log("Webhook Error: Bot dengan ID Telegram {$telegram_bot_id} tidak ditemukan.", 'bot');
    echo "Error: Bot tidak ditemukan.";
    exit;
}
$bot_token = $bot['token'];
$internal_bot_id = $bot['id']; // ID internal untuk foreign key

// Ambil pengaturan untuk bot ini
$stmt_settings = $pdo->prepare("SELECT setting_key, setting_value FROM bot_settings WHERE bot_id = ?");
$stmt_settings->execute([$internal_bot_id]);
$bot_settings_raw = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);

// Tetapkan pengaturan default jika tidak ada di database
$settings = [
    'save_text_messages'    => $bot_settings_raw['save_text_messages'] ?? '1',
    'save_media_messages'   => $bot_settings_raw['save_media_messages'] ?? '1',
    'save_callback_queries' => $bot_settings_raw['save_callback_queries'] ?? '0',
    'save_edited_messages'  => $bot_settings_raw['save_edited_messages'] ?? '0',
];

// --- 3. Baca dan proses input dari Telegram ---
$update_json = file_get_contents('php://input');
$update = json_decode($update_json, true);

if (!$update) {
    exit; // Keluar jika tidak ada update
}

// --- 4. Tentukan Jenis Update & Periksa Pengaturan ---
$update_type = null;
$message_context = null;
$setting_to_check = null;
$is_media = false;

if (isset($update['message'])) {
    $update_type = 'message';
    $message_context = $update['message'];
    $media_keys = ['photo', 'video', 'audio', 'voice', 'document', 'animation', 'video_note'];
    foreach ($media_keys as $key) {
        if (isset($message_context[$key])) {
            $is_media = true;
            break;
        }
    }
    $setting_to_check = $is_media ? 'save_media_messages' : 'save_text_messages';
} elseif (isset($update['edited_message'])) {
    $update_type = 'edited_message';
    $message_context = $update['edited_message'];
    $setting_to_check = 'save_edited_messages';
} elseif (isset($update['callback_query'])) {
    $update_type = 'callback_query';
    $message_context = $update['callback_query']['message'];
    $message_context['from'] = $update['callback_query']['from']; // User yang klik
    $message_context['text'] = "Callback: " . ($update['callback_query']['data'] ?? ''); // Simpan data callback
    $message_context['date'] = time(); // Waktu saat tombol diklik
    $setting_to_check = 'save_callback_queries';
}

// Jika jenis update tidak didukung atau dinonaktifkan oleh admin, keluar.
if ($setting_to_check === null || empty($settings[$setting_to_check])) {
    http_response_code(200); // Balas OK ke Telegram tapi tidak melakukan apa-apa
    exit;
}

// --- 5. Validasi dan Ekstrak Data Penting ---
// Lakukan validasi untuk memastikan struktur dasar ada sebelum diekstrak
if (!isset($message_context['from']['id']) || !isset($message_context['chat']['id'])) {
    app_log("Update '{$update_type}' tidak memiliki 'from' atau 'chat' id, diabaikan.", 'bot');
    http_response_code(200);
    exit;
}

$chat_id_from_telegram = $message_context['chat']['id'];
app_log("Update '{$update_type}' diterima dari chat_id: {$chat_id_from_telegram}", 'bot');

// Ekstrak data penting dari konteks pesan dengan aman
$telegram_message_id = $message_context['message_id'] ?? 0;
$user_id_from_telegram = $message_context['from']['id'];
$first_name = $message_context['from']['first_name'] ?? '';
$username = $message_context['from']['username'] ?? null;
$text_content = $message_context['text'] ?? ($message_context['caption'] ?? '');
$timestamp = $message_context['date'] ?? time();
$message_date = date('Y-m-d H:i:s', $timestamp);


    // --- Mulai Transaksi Database ---
    $pdo->beginTransaction();

    // --- Cari atau buat pengguna, dapatkan data lengkap termasuk state dan saldo ---
    $stmt_user = $pdo->prepare(
        "SELECT u.id, u.telegram_id, u.role, u.balance, r.state, r.state_context
         FROM users u
         LEFT JOIN rel_user_bot r ON u.id = r.user_id AND r.bot_id = ?
         WHERE u.telegram_id = ?"
    );
    $stmt_user->execute([$internal_bot_id, $user_id_from_telegram]);
    $current_user = $stmt_user->fetch();

    if ($current_user) {
        $internal_user_id = $current_user['id'];
    } else {
        $initial_role = (defined('SUPER_ADMIN_TELEGRAM_ID') && (string)$user_id_from_telegram === (string)SUPER_ADMIN_TELEGRAM_ID) ? 'admin' : 'user';
        $stmt_insert = $pdo->prepare("INSERT INTO users (telegram_id, first_name, username, role) VALUES (?, ?, ?, ?)");
        $stmt_insert->execute([$user_id_from_telegram, $first_name, $username, $initial_role]);
        $internal_user_id = $pdo->lastInsertId();
        $stmt_user->execute([$internal_bot_id, $user_id_from_telegram]);
        $current_user = $stmt_user->fetch();
        app_log("Pengguna baru dibuat: telegram_id: {$user_id_from_telegram}, user: {$first_name}, peran: {$initial_role}", 'bot');
    }

    if ($current_user) {
        if (defined('SUPER_ADMIN_TELEGRAM_ID') && !empty(SUPER_ADMIN_TELEGRAM_ID) && (string)$user_id_from_telegram === (string)SUPER_ADMIN_TELEGRAM_ID) {
            if ($current_user['role'] !== 'admin') {
                $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$internal_user_id]);
                $current_user['role'] = 'admin';
                app_log("Peran admin diberikan kepada super admin: {$user_id_from_telegram}", 'bot');
            }
        }
        $stmt_rel_check = $pdo->prepare("SELECT state FROM rel_user_bot WHERE user_id = ? AND bot_id = ?");
        $stmt_rel_check->execute([$internal_user_id, $internal_bot_id]);
        if ($stmt_rel_check->fetch() === false) {
             $pdo->prepare("INSERT INTO rel_user_bot (user_id, bot_id) VALUES (?, ?)")->execute([$internal_user_id, $internal_bot_id]);
             $stmt_user->execute([$internal_bot_id, $user_id_from_telegram]);
             $current_user = $stmt_user->fetch();
        }
        $stmt_member = $pdo->prepare("SELECT id FROM members WHERE user_id = ?");
        $stmt_member->execute([$internal_user_id]);
        if (!$stmt_member->fetch()) {
            $pdo->prepare("INSERT INTO members (user_id) VALUES (?)")->execute([$internal_user_id]);
        }
    }

    // Simpan pesan ke tabel 'messages'
    $pdo->prepare("INSERT INTO messages (user_id, bot_id, telegram_message_id, text, raw_data, direction, telegram_timestamp) VALUES (?, ?, ?, ?, ?, 'incoming', ?)")
        ->execute([$internal_user_id, $internal_bot_id, $telegram_message_id, $text_content, $update_json, $message_date]);

    // ===============================================================
    // =========== BLOK LOGIKA APLIKASI MARKETPLACE DIMULAI ==========
    // ===============================================================
    $telegram_api = new TelegramAPI($bot_token);

    function setUserState($pdo, $user_id, $bot_id, $state, $context = null) {
        $stmt = $pdo->prepare("UPDATE rel_user_bot SET state = ?, state_context = ? WHERE user_id = ? AND bot_id = ?");
        $stmt->execute([$state, $context ? json_encode($context) : null, $user_id, $bot_id]);
    }

    $update_handled = false;

    // 1. HANDLE STATE-BASED CONVERSATION
    if ($current_user['state'] !== null && $update_type === 'message' && isset($message_context['text'])) {
        $text = $message_context['text'];
        $state_context = json_decode($current_user['state_context'] ?? '{}', true);

        if (strpos($text, '/cancel') === 0) {
            setUserState($pdo, $internal_user_id, $internal_bot_id, null, null);
            $telegram_api->sendMessage($chat_id_from_telegram, "Operasi dibatalkan.");
            $update_handled = true;
        } else {
            switch ($current_user['state']) {
                case 'awaiting_price':
                    if (is_numeric($text) && $text >= 0) {
                        $price = (float)$text;
                        $package_id = $state_context['package_id'];
                        $pdo->prepare("UPDATE media_packages SET price = ?, status = 'available' WHERE id = ? AND seller_user_id = ?")->execute([$price, $package_id, $internal_user_id]);
                        setUserState($pdo, $internal_user_id, $internal_bot_id, null, null);
                        $telegram_api->sendMessage($chat_id_from_telegram, "✅ Harga telah ditetapkan. Paket media Anda dengan ID #{$package_id} sekarang tersedia untuk dijual.");
                    } else {
                        $telegram_api->sendMessage($chat_id_from_telegram, "⚠️ Harga tidak valid. Harap masukkan angka saja (contoh: 50000).");
                    }
                    $update_handled = true;
                    break;
            }
        }
    }

    // 2. HANDLE MEDIA SUBMISSION
    if (!$update_handled && $is_media && isset($update['message'])) {
        $media_type = null; $media_info = null;
        $media_keys = ['photo', 'video', 'document', 'audio', 'voice', 'animation', 'video_note'];
        foreach ($media_keys as $key) { if (isset($update['message'][$key])) { $media_type = $key; $media_info = ($key === 'photo') ? end($update['message']['photo']) : $update['message'][$key]; break; } }

        if ($media_type && $media_info) {
            $sql = "INSERT INTO media_files (file_id, type, file_size, width, height, duration, mime_type, file_name, caption, caption_entities, user_id, chat_id, message_id, media_group_id, has_spoiler) VALUES (:file_id, :type, :file_size, :width, :height, :duration, :mime_type, :file_name, :caption, :caption_entities, :user_id, :chat_id, :message_id, :media_group_id, :has_spoiler)";
            $stmt_media = $pdo->prepare($sql);
            $stmt_media->execute([':file_id' => $media_info['file_id'], ':type' => $media_type, ':file_size' => $media_info['file_size'] ?? null, ':width' => $media_info['width'] ?? null, ':height' => $media_info['height'] ?? null, ':duration' => $media_info['duration'] ?? null, ':mime_type' => $media_info['mime_type'] ?? null, ':file_name' => $media_info['file_name'] ?? null, ':caption' => $update['message']['caption'] ?? null, ':caption_entities' => isset($update['message']['caption_entities']) ? json_encode($update['message']['caption_entities']) : null, ':user_id' => $user_id_from_telegram, ':chat_id' => $chat_id_from_telegram, ':message_id' => $telegram_message_id, ':media_group_id' => $update['message']['media_group_id'] ?? null, ':has_spoiler' => $update['message']['has_media_spoiler'] ?? false]);
            $last_media_insert_id = $pdo->lastInsertId();

            // In the new flow, we just save the media. Package creation is handled by the /sell command.
            // No further action is needed here.
        }
        $update_handled = true;
    }

    // 3. HANDLE CALLBACK QUERIES
    if (!$update_handled && $update_type === 'callback_query') {
        $callback_data = $update['callback_query']['data'];
        $callback_query_id = $update['callback_query']['id'];

        if (strpos($callback_data, 'buy_') === 0) {
            $package_id = substr($callback_data, strlen('buy_'));

            $stmt_pkg = $pdo->prepare("SELECT price, seller_user_id FROM media_packages WHERE id = ? AND status = 'available'");
            $stmt_pkg->execute([$package_id]);
            $package = $stmt_pkg->fetch();

            if ($package && $current_user['balance'] >= $package['price']) {
                // Transfer balance
                $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$package['price'], $internal_user_id]);
                $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$package['price'], $package['seller_user_id']]);

                // Mark package as sold
                $pdo->prepare("UPDATE media_packages SET status = 'sold' WHERE id = ?")->execute([$package_id]);

                // Record the sale in the new sales table
                $stmt_sale = $pdo->prepare("INSERT INTO sales (package_id, seller_user_id, buyer_user_id, price) VALUES (?, ?, ?, ?)");
                $stmt_sale->execute([$package_id, $package['seller_user_id'], $internal_user_id, $package['price']]);

                $stmt_files = $pdo->prepare("SELECT file_id, type FROM media_files WHERE package_id = ? ORDER BY id");
                $stmt_files->execute([$package_id]);
                $files = $stmt_files->fetchAll();

                if (!empty($files)) {
                    $media_group = [];
                    foreach ($files as $file) { $media_group[] = ['type' => $file['type'], 'media' => $file['file_id']]; }
                    $pkg_desc_stmt = $pdo->prepare("SELECT description FROM media_packages WHERE id = ?");
                    $pkg_desc_stmt->execute([$package_id]);
                    $pkg_description = $pkg_desc_stmt->fetchColumn();
                    $media_group[0]['caption'] = "Terima kasih telah membeli!\n\n" . $pkg_description;
                    $telegram_api->sendMediaGroup($chat_id_from_telegram, json_encode($media_group));
                }
                $telegram_api->apiRequest('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'Pembelian berhasil!']);
            } else {
                $telegram_api->apiRequest('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'Pembelian gagal. Saldo tidak cukup atau item tidak tersedia.', 'show_alert' => true]);
            }
        }
        $update_handled = true;
    }

    // 4. HANDLE TEXT MESSAGES (COMMANDS)
    if (!$update_handled && $update_type === 'message' && isset($message_context['text'])) {
        $text = $message_context['text'];
        $is_admin = ($current_user['role'] === 'admin');

        if ($is_admin) {
            if (strpos($text, '/dev_addsaldo') === 0) {
                $parts = explode(' ', $text);
                if (count($parts) === 3 && is_numeric($parts[1]) && is_numeric($parts[2])) {
                    $pdo->prepare("UPDATE users SET balance = balance + ? WHERE telegram_id = ?")->execute([(float)$parts[2], $parts[1]]);
                    $telegram_api->sendMessage($chat_id_from_telegram, "✅ Saldo untuk {$parts[1]} berhasil ditambahkan sebesar {$parts[2]}.");
                } else { $telegram_api->sendMessage($chat_id_from_telegram, "Format salah. Gunakan: /dev_addsaldo <telegram_id> <jumlah>"); }
            }
            if (strpos($text, '/feature') === 0) {
                $parts = explode(' ', $text);
                if (count($parts) === 3 && is_numeric($parts[1])) {
                    list(, $package_id, $channel_id) = $parts;
                    $stmt_pkg = $pdo->prepare("SELECT p.description, p.price, f.file_id, f.type FROM media_packages p JOIN media_files f ON p.id = f.package_id WHERE p.id = ? AND p.status = 'available' LIMIT 1");
                    $stmt_pkg->execute([$package_id]);
                    $pkg_info = $stmt_pkg->fetch();
                    if($pkg_info) {
                        $bot_username = $pdo->query("SELECT username FROM bots WHERE id = {$internal_bot_id}")->fetchColumn();
                        $price_formatted = "Rp " . number_format($pkg_info['price'], 0, ',', '.');
                        $caption = "✨ **Item Unggulan!** ✨\n\n{$pkg_info['description']}\n\nHarga: **{$price_formatted}**";
                        $keyboard = ['inline_keyboard' => [[['text' => '➡️ Lihat & Beli di Bot', 'url' => "https://t.me/{$bot_username}?start=package_{$package_id}"]]]];
                        $method = 'send' . ucfirst(strtolower($pkg_info['type']));
                        if (method_exists($telegram_api, $method) && in_array($pkg_info['type'], ['photo', 'video', 'document', 'audio', 'animation'])) {
                            $telegram_api->$method($channel_id, $pkg_info['file_id'], $caption, 'Markdown', json_encode($keyboard));
                        } else { $telegram_api->sendMessage($channel_id, $caption, 'Markdown', json_encode($keyboard)); }
                        $telegram_api->sendMessage($chat_id_from_telegram, "✅ Item #{$package_id} berhasil di-feature ke channel {$channel_id}.");
                    } else { $telegram_api->sendMessage($chat_id_from_telegram, "Item #{$package_id} tidak ditemukan atau tidak tersedia."); }
                } else { $telegram_api->sendMessage($chat_id_from_telegram, "Format salah. Gunakan: /feature <package_id> <channel_id_atau_@username>");}
            }
        }

        if (strpos($text, '/start') === 0) {
            $parts = explode(' ', $text);
            if (count($parts) > 1 && strpos($parts[1], 'package_') === 0) {
                $package_id = substr($parts[1], strlen('package_'));
                $stmt_pkg = $pdo->prepare("SELECT description, price, status FROM media_packages WHERE id = ?");
                $stmt_pkg->execute([$package_id]);
                $package = $stmt_pkg->fetch();
                if($package && $package['status'] == 'available') {
                    $price_formatted = "Rp " . number_format($package['price'], 0, ',', '.');
                    $balance_formatted = "Rp " . number_format($current_user['balance'], 0, ',', '.');
                    $reply_text = "Anda tertarik dengan item berikut:\n\n*Deskripsi:* {$package['description']}\n*Harga:* {$price_formatted}\n\nSaldo Anda saat ini: {$balance_formatted}.";
                    $keyboard = ['inline_keyboard' => [[['text' => "Beli Sekarang ({$price_formatted})", 'callback_data' => "buy_{$package_id}"]]]];
                    $telegram_api->sendMessage($chat_id_from_telegram, $reply_text, 'Markdown', json_encode($keyboard));
                } else { $telegram_api->sendMessage($chat_id_from_telegram, "Maaf, item ini sudah tidak tersedia atau tidak ditemukan."); }
            } else {
                $welcome_message = "👋 *Selamat Datang di Bot Marketplace!* 🤖\n\n";
                $welcome_message .= "Berikut adalah beberapa perintah yang bisa Anda gunakan:\n\n";
                $welcome_message .= "- *Menjual Konten* 📸\n";
                $welcome_message .= "  Reply media (foto/video) yang ingin Anda jual dengan perintah `/sell`.\n\n";
                $welcome_message .= "- *Cek Saldo* 💰\n";
                $welcome_message .= "  Gunakan perintah `/balance` untuk melihat saldo Anda.\n\n";
                $welcome_message .= "- *Akses Konten* 📂\n";
                $welcome_message .= "  Gunakan `/konten <ID Paket>` untuk mengunduh kembali konten yang sudah Anda beli atau jual.\n\n";
                $welcome_message .= "- *Login ke Panel* 🌐\n";
                $welcome_message .= "  Gunakan perintah `/login` untuk mendapatkan link akses ke panel member Anda.\n\n";
                $welcome_message .= "Ada yang bisa saya bantu?";

                $telegram_api->sendMessage($chat_id_from_telegram, $welcome_message, 'Markdown');
            }
        } elseif (strpos($text, '/sell') === 0) {
            if (!isset($message_context['reply_to_message'])) {
                $telegram_api->sendMessage($chat_id_from_telegram, "Untuk menjual, silakan reply media yang ingin Anda jual dengan perintah /sell.");
            } else {
                $replied_message = $message_context['reply_to_message'];
                $replied_message_id = $replied_message['message_id'];
                $replied_chat_id = $replied_message['chat']['id'];
                $description = $replied_message['caption'] ?? '';
                $media_group_id = $replied_message['media_group_id'] ?? null;

                $media_file_ids = [];

                if ($media_group_id) {
                    // Handle media group
                    $stmt_media = $pdo->prepare("SELECT id FROM media_files WHERE media_group_id = ? AND chat_id = ?");
                    $stmt_media->execute([$media_group_id, $replied_chat_id]);
                    $media_file_ids = $stmt_media->fetchAll(PDO::FETCH_COLUMN);
                } else {
                    // Handle single media
                    $media_keys = ['photo', 'video', 'audio', 'voice', 'document', 'animation', 'video_note'];
                    $is_replied_media = false;
                    foreach ($media_keys as $key) {
                        if (isset($replied_message[$key])) {
                            $is_replied_media = true;
                            break;
                        }
                    }

                    if ($is_replied_media) {
                        $stmt_media = $pdo->prepare("SELECT id FROM media_files WHERE message_id = ? AND chat_id = ?");
                        $stmt_media->execute([$replied_message_id, $replied_chat_id]);
                        $media_file_id = $stmt_media->fetchColumn();
                        if ($media_file_id) {
                            $media_file_ids[] = $media_file_id;
                        }
                    }
                }

                if (empty($media_file_ids)) {
                    $telegram_api->sendMessage($chat_id_from_telegram, "⚠️ Gagal. Pastikan Anda me-reply pesan media (foto/video) yang sudah tersimpan di bot.");
                } else {
                    // Create a new package
                    $stmt_package = $pdo->prepare("INSERT INTO media_packages (seller_user_id, bot_id, description, status) VALUES (?, ?, ?, 'pending')");
                    $stmt_package->execute([$internal_user_id, $internal_bot_id, $description]);
                    $package_id = $pdo->lastInsertId();

                    // Link media files to the package
                    $stmt_link = $pdo->prepare("UPDATE media_files SET package_id = ? WHERE id = ?");
                    foreach ($media_file_ids as $media_id) {
                        $stmt_link->execute([$package_id, $media_id]);
                    }

                    setUserState($pdo, $internal_user_id, $internal_bot_id, 'awaiting_price', ['package_id' => $package_id]);
                    $telegram_api->sendMessage($chat_id_from_telegram, "✅ Media telah disiapkan untuk dijual dengan deskripsi:\n\n*\"{$description}\"*\n\nSekarang, silakan masukkan harga untuk paket ini (contoh: 50000).", 'Markdown');
                }
            }
        } elseif (strpos($text, '/konten') === 0) {
            $parts = explode(' ', $text);
            if (count($parts) !== 2 || !is_numeric($parts[1])) {
                $telegram_api->sendMessage($chat_id_from_telegram, "Format perintah salah. Gunakan: /konten <ID Konten>");
            } else {
                $package_id = (int)$parts[1];

                // Check if user has access (is seller or buyer)
                $stmt_pkg = $pdo->prepare("SELECT seller_user_id FROM media_packages WHERE id = ?");
                $stmt_pkg->execute([$package_id]);
                $package = $stmt_pkg->fetch();

                $has_access = false;
                if ($package) {
                    // Check if current user is the seller
                    if ($package['seller_user_id'] == $internal_user_id) {
                        $has_access = true;
                    } else {
                        // Check if current user is a buyer
                        $stmt_sale = $pdo->prepare("SELECT id FROM sales WHERE package_id = ? AND buyer_user_id = ?");
                        $stmt_sale->execute([$package_id, $internal_user_id]);
                        if ($stmt_sale->fetch()) {
                            $has_access = true;
                        }
                    }
                }

                if ($has_access) {
                    $stmt_files = $pdo->prepare("SELECT file_id, type FROM media_files WHERE package_id = ? ORDER BY id");
                    $stmt_files->execute([$package_id]);
                    $files = $stmt_files->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($files)) {
                        $media_group = [];
                        foreach ($files as $file) {
                            $media_group[] = ['type' => $file['type'], 'media' => $file['file_id']];
                        }
                        // Add a caption to the first item of the media group
                        $media_group[0]['caption'] = "Berikut konten yang Anda minta untuk paket ID #{$package_id}.";

                        $telegram_api->sendMediaGroup($chat_id_from_telegram, json_encode($media_group));
                    } else {
                        $telegram_api->sendMessage($chat_id_from_telegram, "Konten untuk paket ini tidak dapat ditemukan.");
                    }
                } else {
                    $telegram_api->sendMessage($chat_id_from_telegram, "Anda tidak memiliki akses ke konten ini atau konten tidak ditemukan.");
                }
            }
        } elseif (strpos($text, '/balance') === 0) {
            $balance = "Rp " . number_format($current_user['balance'], 2, ',', '.');
            $telegram_api->sendMessage($chat_id_from_telegram, "Saldo Anda saat ini: {$balance}");
        } elseif (strpos($text, '/login') === 0) {
            if (!defined('BASE_URL') || empty(BASE_URL)) { $telegram_api->sendMessage($chat_id_from_telegram, "Maaf, terjadi kesalahan teknis (ERR:CFG01)."); }
            else {
                $login_token = bin2hex(random_bytes(32));
                $pdo->prepare("UPDATE members SET login_token = ?, token_created_at = NOW(), token_used = 0 WHERE user_id = ?")->execute([$login_token, $internal_user_id]);
                $login_link = rtrim(BASE_URL, '/') . '/member/index.php?token=' . $login_token;
                $keyboard = ['inline_keyboard' => [[['text' => 'Login ke Panel Member', 'url' => $login_link]]]];
                $telegram_api->sendMessage($chat_id_from_telegram, "Klik tombol di bawah ini untuk masuk ke Panel Member Anda. Tombol ini hanya dapat digunakan satu kali.", null, json_encode($keyboard));
            }
        }
    }

    $pdo->commit();

    // Beri respons OK ke Telegram untuk menandakan update sudah diterima.
    http_response_code(200);
    echo "OK";

} catch (Throwable $e) {
    // Jika terjadi error di mana pun, tangkap di sini.
    // Rollback transaksi jika sedang berjalan.
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Catat error fatal ke log.
    $error_message = sprintf(
        "Fatal Webhook Error: %s in %s on line %d",
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );
    app_log($error_message, 'error');

    // Beri respons 500 ke Telegram.
    http_response_code(500);
    echo "Internal Server Error";
}
