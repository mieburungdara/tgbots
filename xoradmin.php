<?php
// WARNING: This script is highly destructive and provides powerful admin capabilities.
// Use with extreme caution. It is recommended to delete this file after use.

session_start();

// --- Konfigurasi ---
$correct_password = 'sup3r4dmin';
$sql_schema_file = 'updated_schema.sql';

// --- Inisialisasi Variabel ---
$message = '';
$error = '';
$db_message = '';
$db_error = '';
$bot_message = '';
$bot_error = '';
$active_tab = 'bots'; // Tab default
$action_view = 'list_bots'; // Tampilan default di tab bot

// --- Inisialisasi Koneksi DB & Helper ---
$pdo = null;
$is_authenticated = isset($_SESSION['is_authenticated']) && $_SESSION['is_authenticated'];

if ($is_authenticated) {
    require_once __DIR__ . '/core/database.php';
    require_once __DIR__ . '/core/helpers.php';
    require_once __DIR__ . '/core/TelegramAPI.php';
    $pdo = get_db_connection();
    if (!$pdo) {
        $error = "Koneksi database gagal. Pastikan file `config.php` sudah benar.";
    }
}

// --- Logika Proses ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        if (isset($_POST['password']) && $_POST['password'] === $correct_password) {
            $_SESSION['is_authenticated'] = true;
            header("Location: xoradmin.php");
            exit;
        } else {
            $error = "Password salah!";
            unset($_SESSION['is_authenticated']);
        }
    }

    if (isset($_POST['logout'])) {
        session_destroy();
        header("Location: xoradmin.php");
        exit;
    }

    if ($is_authenticated && $pdo) {
        $post_action = $_POST['action'] ?? null;

        // --- Backend Handlers (AJAX) ---
        if ($post_action === 'get_me' || $post_action === 'set' || $post_action === 'check' || $post_action === 'delete') {
            header('Content-Type: application/json');
            $handler_response = ['status' => 'error', 'message' => 'Aksi tidak diketahui.'];
            $bot_id = isset($_POST['bot_id']) ? (int)$_POST['bot_id'] : 0;

            if ($bot_id > 0) {
                try {
                    $stmt_token = $pdo->prepare("SELECT token FROM bots WHERE id = ?");
                    $stmt_token->execute([$bot_id]);
                    $token = $stmt_token->fetchColumn();
                    if (!$token) throw new Exception("Bot tidak ditemukan.");

                    $telegram_api = new TelegramAPI($token);

                    if ($post_action === 'get_me') {
                        $bot_info = $telegram_api->getMe();
                        if (!isset($bot_info['ok']) || !$bot_info['ok']) throw new Exception("Gagal mendapatkan info dari Telegram: " . ($bot_info['description'] ?? ''));
                        $bot_result = $bot_info['result'];
                        if ($bot_result['id'] != $bot_id) throw new Exception("Token tidak cocok dengan ID bot.");

                        $stmt_update = $pdo->prepare("UPDATE bots SET first_name = ?, username = ? WHERE id = ?");
                        $stmt_update->execute([$bot_result['first_name'], $bot_result['username'] ?? null, $bot_id]);

                        $handler_response = ['status' => 'success', 'message' => 'Informasi bot diperbarui.', 'data' => $bot_result];
                    } else { // Webhook actions
                        $result = null;
                        if ($post_action === 'set') {
                            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                            $webhook_url = $protocol . $_SERVER['HTTP_HOST'] . str_replace(basename(__FILE__), 'webhook.php', $_SERVER['PHP_SELF']) . '?id=' . $bot_id;
                            $result = $telegram_api->setWebhook($webhook_url);
                        } elseif ($post_action === 'check') {
                            $result = $telegram_api->getWebhookInfo();
                        } elseif ($post_action === 'delete') {
                            $result = $telegram_api->deleteWebhook();
                        }
                        if ($result === false) throw new Exception("Gagal komunikasi dengan API Telegram.");
                        $handler_response = ['status' => 'success', 'data' => $result];
                    }
                } catch (Exception $e) {
                    $handler_response['message'] = $e->getMessage();
                }
            }
            echo json_encode($handler_response);
            exit;
        }

        // --- Form Submissions ---
        if (isset($_POST['confirm_reset'])) {
            $active_tab = 'db_reset';
            // ... (logika reset database tetap sama) ...
        }

        if (isset($_POST['add_bot'])) {
            $active_tab = 'bots';
             // ... (logika tambah bot tetap sama) ...
        }

        if (isset($_POST['save_bot_settings'])) {
            $active_tab = 'bots';
            $action_view = 'edit_bot';
            $bot_id = filter_input(INPUT_POST, 'bot_id', FILTER_VALIDATE_INT);
            if ($bot_id) {
                try {
                    $settings_from_form = $_POST['settings'] ?? [];
                    $allowed_keys = ['save_text_messages', 'save_media_messages', 'save_callback_queries', 'save_edited_messages'];
                    $pdo->beginTransaction();
                    foreach ($allowed_keys as $key) {
                        $value = isset($settings_from_form[$key]) && $settings_from_form[$key] === '1' ? '1' : '0';
                        $sql = "INSERT INTO bot_settings (bot_id, setting_key, setting_value) VALUES (:bot_id, :setting_key, :setting_value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([':bot_id' => $bot_id, ':setting_key' => $key, ':setting_value' => $value]);
                    }
                    $pdo->commit();
                    $_SESSION['status_message'] = 'Pengaturan berhasil disimpan.';
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $_SESSION['status_message'] = 'Terjadi error saat menyimpan pengaturan.';
                }
                header("Location: xoradmin.php?action=edit_bot&id=" . $bot_id);
                exit;
            }
        }
    }
}

// --- Logika GET Request ---
if ($is_authenticated && $pdo) {
    $get_action = $_GET['action'] ?? 'list_bots';
    if ($get_action === 'edit_bot' && isset($_GET['id'])) {
        $action_view = 'edit_bot';
        $bot_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
        $stmt = $pdo->prepare("SELECT id, first_name, username, token, created_at FROM bots WHERE id = ?");
        $stmt->execute([$bot_id]);
        $bot = $stmt->fetch();
        if (!$bot) {
            header("Location: xoradmin.php");
            exit;
        }
        $stmt_settings = $pdo->prepare("SELECT setting_key, setting_value FROM bot_settings WHERE bot_id = ?");
        $stmt_settings->execute([$bot_id]);
        $bot_settings_raw = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
        $settings = [
            'save_text_messages'    => $bot_settings_raw['save_text_messages'] ?? '1',
            'save_media_messages'   => $bot_settings_raw['save_media_messages'] ?? '1',
            'save_callback_queries' => $bot_settings_raw['save_callback_queries'] ?? '0',
            'save_edited_messages'  => $bot_settings_raw['save_edited_messages'] ?? '0',
        ];
        $status_message = $_SESSION['status_message'] ?? null;
        unset($_SESSION['status_message']);
    }
    $bots = $pdo->query("SELECT id, first_name, username, created_at FROM bots ORDER BY created_at DESC")->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XOR Admin Panel</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; color: #333; max-width: 960px; margin: 20px auto; padding: 0 20px; background-color: #f4f4f4; }
        .container { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1, h2, h3 { color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .warning { background-color: #fcf8e3; border: 1px solid #faebcc; color: #8a6d3b; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .message, .alert-success { background-color: #dff0d8; border: 1px solid #d6e9c6; color: #3c763d; padding: 15px; border-radius: 4px; margin-top: 20px; white-space: pre-wrap; word-wrap: break-word; }
        .error { background-color: #f2dede; border: 1px solid #ebccd1; color: #a94442; padding: 15px; border-radius: 4px; margin-top: 20px; }
        form { margin-top: 20px; }
        input[type="password"], input[type="text"], input[type="submit"], button { width: 100%; padding: 10px; margin-bottom: 15px; border-radius: 4px; border: 1px solid #ccc; box-sizing: border-box; }
        button, input[type="submit"] { background-color: #337ab7; color: white; font-size: 16px; border: none; cursor: pointer; transition: background-color 0.3s; }
        button:hover, input[type="submit"]:hover { background-color: #286090; }
        .logout-form { text-align: right; margin: -20px 0 20px 0; border: none; }
        .logout-form input[type="submit"] { width: auto; background-color: #5bc0de; }
        .logout-form input[type="submit"]:hover { background-color: #31b0d5; }
        .danger-zone input[type="submit"] { background-color: #d9534f; }
        .danger-zone input[type="submit"]:hover { background-color: #c9302c; }
        .tabs { border-bottom: 1px solid #ddd; display: flex; }
        .tab-link { padding: 10px 15px; cursor: pointer; border: 1px solid transparent; border-bottom: none; margin-bottom: -1px; background: #f8f8f8; text-decoration: none; color: #337ab7; }
        .tab-link.active { background: #fff; border-color: #ddd; border-bottom-color: #fff; border-radius: 4px 4px 0 0; }
        .tab-content { display: none; padding: 20px; border: 1px solid #ddd; border-top: none; }
        .tab-content.active { display: block; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; }
        .actions button { margin-right: 10px; margin-bottom: 10px; width: auto; }
        .modal { display: none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 8px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; }
        .close:hover, .close:focus { color: black; text-decoration: none; cursor: pointer; }
        #modal-body { white-space: pre-wrap; background-color: #eee; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>XOR Admin Panel</h1>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!$is_authenticated): ?>
            <div class="warning"><strong>Peringatan:</strong> Diperlukan otentikasi untuk melanjutkan.</div>
            <form action="" method="post">
                <label for="password">Masukkan Password:</label>
                <input type="password" id="password" name="password" required>
                <input type="submit" name="login" value="Otentikasi">
            </form>
        <?php else: ?>
            <form action="" method="post" class="logout-form">
                <input type="submit" name="logout" value="Logout">
            </form>

            <div class="tabs">
                <a href="?action=list_bots" class="tab-link <?= $action_view !== 'db_reset' ? 'active' : '' ?>">Manajemen Bot</a>
                <a href="?action=db_reset" class="tab-link <?= $action_view === 'db_reset' ? 'active' : '' ?>">Reset Database</a>
            </div>

            <div class="main-content">
                <?php if ($action_view === 'list_bots'): ?>
                    <div id="bots" class="tab-content active">
                        <h2>Manajemen Bot</h2>
                        <?php if ($bot_error): ?><div class="error"><?= htmlspecialchars($bot_error) ?></div><?php endif; ?>
                        <?php if ($bot_message): ?><div class="message"><?= htmlspecialchars($bot_message) ?></div><?php endif; ?>

                        <h3>Tambah Bot Baru</h3>
                        <form action="xoradmin.php" method="post">
                            <input type="text" name="token" placeholder="Token API dari BotFather" required>
                            <button type="submit" name="add_bot">Tambah Bot</button>
                        </form>

                        <h3>Daftar Bot</h3>
                        <table>
                            <thead><tr><th>ID Bot</th><th>Nama</th><th>Username</th><th>Aksi</th></tr></thead>
                            <tbody>
                                <?php if (empty($bots)): ?>
                                    <tr><td colspan="4" style="text-align: center;">Belum ada bot yang ditambahkan.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($bots as $b): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($b['id']) ?></td>
                                            <td><?= htmlspecialchars($b['first_name']) ?></td>
                                            <td>@<?= htmlspecialchars($b['username'] ?? 'N/A') ?></td>
                                            <td><a href="?action=edit_bot&id=<?= htmlspecialchars($b['id']) ?>">Edit</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($action_view === 'edit_bot' && isset($bot)): ?>
                    <div id="edit-bot" class="tab-content active">
                        <h2>Edit Bot: <?= htmlspecialchars($bot['first_name']) ?> <a href="?action=list_bots" style="font-size: 0.7em; float: right;">&laquo; Kembali ke Daftar</a></h2>
                        <?php if ($status_message): ?><div class="alert-success"><?= htmlspecialchars($status_message) ?></div><?php endif; ?>
                        <div class="bot-info">
                            <h3>Informasi Bot</h3>
                            <p><strong>ID Telegram:</strong> <?= htmlspecialchars($bot['id']) ?></p>
                            <p><strong>Username:</strong> @<?= htmlspecialchars($bot['username'] ?? 'N/A') ?></p>
                            <p><strong>Token:</strong> <code><?= substr(htmlspecialchars($bot['token']), 0, 15) ?>...</code></p>
                        </div>
                        <div class="actions">
                            <h3>Manajemen Bot & Webhook</h3>
                            <button class="set-webhook" data-bot-id="<?= $bot['id'] ?>">Set Webhook</button>
                            <button class="check-webhook" data-bot-id="<?= $bot['id'] ?>">Check Webhook</button>
                            <button class="delete-webhook" data-bot-id="<?= $bot['id'] ?>">Delete Webhook</button>
                            <button class="get-me" data-bot-id="<?= $bot['id'] ?>">Get Me & Update</button>
                        </div>
                        <div class="settings">
                            <h3>Pengaturan Penyimpanan Pesan</h3>
                            <form action="xoradmin.php" method="post">
                                <input type="hidden" name="bot_id" value="<?= $bot['id'] ?>">
                                <?php foreach ($settings as $key => $value): ?>
                                <label><input type="checkbox" name="settings[<?= $key ?>]" value="1" <?= $value ? 'checked' : '' ?>> <?= ucwords(str_replace('_', ' ', $key)) ?></label><br>
                                <?php endforeach; ?>
                                <button type="submit" name="save_bot_settings">Simpan Pengaturan</button>
                            </form>
                        </div>
                    </div>
                <?php elseif ($action_view === 'db_reset'): ?>
                    <div id="db_reset" class="tab-content danger-zone active">
                        <h2>Reset Database</h2>
                        <div class="warning"><strong>PERINGATAN:</strong> Semua data akan hilang secara permanen.</div>
                        <?php if ($db_error): ?><div class="error"><?= htmlspecialchars($db_error) ?></div><?php endif; ?>
                        <?php if ($db_message): ?><div class="message"><?= $db_message ?></div><?php endif; ?>
                        <form action="" method="post" onsubmit="return confirm('APAKAH ANDA YAKIN INGIN MERESET DATABASE?');">
                            <input type="hidden" name="confirm_reset" value="1">
                            <input type="submit" value="HAPUS DAN RESET DATABASE SEKARANG">
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- The Modal -->
    <div id="responseModal" class="modal"><div class="modal-content"><span class="close">&times;</span><h2 id="modal-title">Hasil Aksi</h2><pre id="modal-body">Memproses...</pre></div></div>

    <script>
        <?php if ($is_authenticated): ?>
        // All JS logic for modal and actions
        const modal = document.getElementById('responseModal');
        const modalTitle = document.getElementById('modal-title');
        const modalBody = document.getElementById('modal-body');
        const span = document.getElementsByClassName('close')[0];

        function showModal(title, content) { modalTitle.textContent = title; modalBody.textContent = content; modal.style.display = 'block'; }
        span.onclick = function() { modal.style.display = 'none'; }
        window.onclick = function(event) { if (event.target == modal) modal.style.display = 'none'; }

        async function handleAjaxAction(action, botId) {
            let confirmation = action !== 'delete' || confirm('Yakin ingin menghapus webhook?');
            if (!confirmation) return;

            showModal('Hasil ' + action, 'Memproses...');
            try {
                const formData = new FormData();
                formData.append('action', action);
                formData.append('bot_id', botId);

                const response = await fetch('xoradmin.php', { method: 'POST', body: formData });
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const result = await response.json();
                if (result.status === 'error') throw new Error(result.message);

                let successMessage = result.message || JSON.stringify(result.data, null, 2);
                if (action === 'get_me') {
                    successMessage = `Sukses! Info bot diperbarui.\nNama: ${result.data.first_name}\nUsername: @${result.data.username}\n\nMuat ulang halaman untuk melihat perubahan.`;
                }
                showModal('Hasil ' + action, successMessage);
            } catch (error) {
                showModal('Error', 'Gagal: ' + error.message);
            }
        }

        document.querySelectorAll('.set-webhook, .check-webhook, .delete-webhook, .get-me').forEach(button => {
            button.addEventListener('click', function() {
                handleAjaxAction(this.classList[0], this.dataset.botId);
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>
