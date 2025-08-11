<?php
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';

$pdo = get_db_connection();
$bot = null;
$error = null;

// Validasi ID Bot Telegram dari URL
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: bots.php");
    exit;
}
$telegram_bot_id = $_GET['id'];

// Ambil data bot dari database menggunakan ID bot dari token
$stmt = $pdo->prepare("SELECT id, name, token, created_at FROM bots WHERE token LIKE ?");
$stmt->execute(["{$telegram_bot_id}:%"]);
$bot = $stmt->fetch();

// Jika bot tidak ditemukan, kembali ke halaman daftar bot
if (!$bot) {
    header("Location: bots.php");
    exit;
}

// Ambil pengaturan bot
$stmt_settings = $pdo->prepare("SELECT setting_key, setting_value FROM bot_settings WHERE bot_id = ?");
$stmt_settings->execute([$bot['id']]);
$bot_settings_raw = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);

// Tetapkan pengaturan default jika tidak ada di database
$settings = [
    'save_text_messages'    => $bot_settings_raw['save_text_messages'] ?? '1', // Default on
    'save_media_messages'   => $bot_settings_raw['save_media_messages'] ?? '1', // Default on
    'save_callback_queries' => $bot_settings_raw['save_callback_queries'] ?? '0', // Default off
    'save_edited_messages'  => $bot_settings_raw['save_edited_messages'] ?? '0', // Default off
];

// Pesan status dari settings_manager.php
$status_message = $_SESSION['status_message'] ?? null;
unset($_SESSION['status_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Bot: <?= htmlspecialchars($bot['name']) ?> - Admin Panel</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 40px; background-color: #f4f6f8; color: #333; }
        .container { max-width: 800px; margin: 0 auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; }
        nav { margin-bottom: 20px; }
        nav a { text-decoration: none; color: #007bff; padding: 10px; }
        nav a.active { font-weight: bold; }
        .bot-info { margin-bottom: 20px; }
        .bot-info p { margin: 5px 0; }
        .actions button {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            color: white;
            margin-right: 10px;
        }
        .actions .set-webhook { background-color: #007bff; }
        .actions .set-webhook:hover { background-color: #0056b3; }
        .actions .check-webhook { background-color: #17a2b8; }
        .actions .check-webhook:hover { background-color: #117a8b; }
        .actions .delete-webhook { background-color: #dc3545; }
        .actions .delete-webhook:hover { background-color: #c82333; }
        .save-settings { background-color: #28a745; }
        .save-settings:hover { background-color: #218838; }

        .settings { margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px; }
        .setting-item { margin-bottom: 15px; }
        .setting-item label { font-weight: normal; display: flex; align-items: center; }
        .setting-item input { margin-right: 10px; }

        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 15px; border-radius: 4px; margin-bottom: 20px;}

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 8px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; }
        .close:hover, .close:focus { color: black; text-decoration: none; cursor: pointer; }
        #modal-body { white-space: pre-wrap; background-color: #eee; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <nav>
            <a href="index.php">Percakapan</a> |
            <a href="bots.php" class="active">Kelola Bot</a> |
            <a href="users.php">Pengguna</a> |
            <a href="roles.php">Manajemen Peran</a> |
            <a href="media_logs.php">Log Media</a> |
            <a href="settings.php">Pengaturan</a> |
            <a href="logs.php">Logs</a>
        </nav>

        <h1>Edit Bot: <?= htmlspecialchars($bot['name']) ?></h1>

        <?php if ($status_message): ?>
            <div class="alert-success"><?= htmlspecialchars($status_message) ?></div>
        <?php endif; ?>

        <div class="bot-info">
            <h2>Informasi Bot</h2>
            <p><strong>ID Telegram:</strong> <?= htmlspecialchars($telegram_bot_id) ?></p>
            <p><strong>Nama:</strong> <?= htmlspecialchars($bot['name']) ?></p>
            <p><strong>Token:</strong> <code><?= substr(htmlspecialchars($bot['token']), 0, 15) ?>...</code></p>
            <p><strong>Dibuat pada:</strong> <?= htmlspecialchars($bot['created_at']) ?></p>
        </div>

        <div class="actions">
            <h2>Manajemen Webhook</h2>
            <button class="set-webhook" data-bot-id="<?= $bot['id'] // Gunakan ID internal untuk AJAX ?>">Set Webhook</button>
            <button class="check-webhook" data-bot-id="<?= $bot['id'] // Gunakan ID internal untuk AJAX ?>">Check Webhook</button>
            <button class="delete-webhook" data-bot-id="<?= $bot['id'] // Gunakan ID internal untuk AJAX ?>">Delete Webhook</button>
        </div>

        <div class="settings">
            <h2>Pengaturan Penyimpanan Pesan</h2>
            <p>Pilih jenis pembaruan (update) dari Telegram yang ingin Anda simpan ke database untuk bot ini.</p>
            <form action="settings_manager.php" method="post">
                <input type="hidden" name="bot_id" value="<?= $bot['id'] ?>">
                <input type="hidden" name="telegram_bot_id" value="<?= htmlspecialchars($telegram_bot_id) ?>">

                <div class="setting-item">
                    <label>
                        <input type="checkbox" name="settings[save_text_messages]" value="1" <?= $settings['save_text_messages'] ? 'checked' : '' ?>>
                        Simpan Pesan Teks
                    </label>
                </div>
                <div class="setting-item">
                    <label>
                        <input type="checkbox" name="settings[save_media_messages]" value="1" <?= $settings['save_media_messages'] ? 'checked' : '' ?>>
                        Simpan Pesan Media (Foto, Video, dll.)
                    </label>
                </div>
                <div class="setting-item">
                    <label>
                        <input type="checkbox" name="settings[save_callback_queries]" value="1" <?= $settings['save_callback_queries'] ? 'checked' : '' ?>>
                        Simpan Penekanan Tombol (Callback Query)
                    </label>
                </div>
                <div class="setting-item">
                    <label>
                        <input type="checkbox" name="settings[save_edited_messages]" value="1" <?= $settings['save_edited_messages'] ? 'checked' : '' ?>>
                        Simpan Pesan yang Diedit
                    </label>
                </div>
                <br>
                <button type="submit" class="actions set-webhook save-settings">Simpan Pengaturan</button>
            </form>
        </div>

    </div>

    <!-- The Modal -->
    <div id="responseModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modal-title">Hasil Aksi</h2>
            <pre id="modal-body">Memproses...</pre>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('responseModal');
            const modalTitle = document.getElementById('modal-title');
            const modalBody = document.getElementById('modal-body');
            const span = document.getElementsByClassName('close')[0];

            function showModal(title, content) {
                modalTitle.textContent = title;
                modalBody.textContent = content;
                modal.style.display = 'block';
            }

            span.onclick = function() {
                modal.style.display = 'none';
            }
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            }

            async function handleWebhookAction(action, botId) {
                let confirmation = true;
                if (action === 'delete') {
                    confirmation = confirm('Apakah Anda yakin ingin menghapus webhook untuk bot ini?');
                }

                if (!confirmation) {
                    return;
                }

                showModal('Hasil ' + action, 'Sedang memproses permintaan...');

                try {
                    const response = await fetch('webhook_manager.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=${action}&bot_id=${botId}`
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const result = await response.json();
                    if (result.status === 'error') {
                        throw new Error(result.data);
                    }
                    const formattedResult = JSON.stringify(result.data, null, 2);
                    showModal('Hasil ' + action, formattedResult);

                } catch (error) {
                    showModal('Error', 'Gagal melakukan permintaan: ' + error.message);
                }
            }

            document.querySelectorAll('.set-webhook, .check-webhook, .delete-webhook').forEach(button => {
                button.addEventListener('click', function() {
                    const action = this.classList.contains('set-webhook') ? 'set' :
                                   this.classList.contains('check-webhook') ? 'check' : 'delete';
                    handleWebhookAction(action, this.dataset.botId);
                });
            });
        });
    </script>
</body>
</html>
