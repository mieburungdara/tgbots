<?php
/**
 * Halaman Edit Bot (Admin).
 *
 * Halaman ini menyediakan antarmuka untuk mengelola satu bot secara spesifik.
 * Administrator dapat melihat informasi bot, mengelola webhook (set, check, delete),
 * dan mengubah pengaturan penyimpanan pesan untuk bot tersebut.
 *
 * Aksi-aksi (seperti manajemen webhook dan pengaturan) ditangani oleh file-file
 * handler terpisah (`webhook_manager.php`, `settings_manager.php`).
 */
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';

$pdo = get_db_connection();
$bot = null;
$error = null;

// Validasi ID Bot Telegram dari parameter URL.
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: bots.php");
    exit;
}
$bot_id = $_GET['id'];

// Ambil data bot dari database menggunakan ID Telegram-nya.
$stmt = $pdo->prepare("SELECT id, first_name, username, token, created_at FROM bots WHERE id = ?");
$stmt->execute([$bot_id]);
$bot = $stmt->fetch();

// Jika bot tidak ditemukan, alihkan kembali ke halaman daftar bot.
if (!$bot) {
    header("Location: bots.php");
    exit;
}

// Ambil pengaturan spesifik untuk bot ini dari tabel `bot_settings`.
$stmt_settings = $pdo->prepare("SELECT setting_key, setting_value FROM bot_settings WHERE bot_id = ?");
$stmt_settings->execute([$bot_id]);
$bot_settings_raw = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);

// Tetapkan nilai default untuk setiap pengaturan jika belum ada di database,
// untuk memastikan semua checkbox memiliki status yang benar.
$settings = [
    'save_text_messages'    => $bot_settings_raw['save_text_messages'] ?? '1', // Default on
    'save_media_messages'   => $bot_settings_raw['save_media_messages'] ?? '1', // Default on
    'save_callback_queries' => $bot_settings_raw['save_callback_queries'] ?? '0', // Default off
    'save_edited_messages'  => $bot_settings_raw['save_edited_messages'] ?? '0', // Default off
];

// Pesan status dari settings_manager.php
$status_message = $_SESSION['status_message'] ?? null;
unset($_SESSION['status_message']);

$page_title = 'Edit Bot: ' . htmlspecialchars($bot['first_name']);
require_once __DIR__ . '/../partials/header.php';
?>

<style>
    /* Modal Styles */
    .modal { display: none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
    .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 8px; }
    .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; }
    .close:hover, .close:focus { color: black; text-decoration: none; cursor: pointer; }
    #modal-body { white-space: pre-wrap; background-color: #eee; padding: 15px; border-radius: 5px; }
    .actions button { margin-right: 10px; margin-bottom: 10px; }
</style>

<h1>Edit Bot: <?= htmlspecialchars($bot['first_name']) ?></h1>

        <?php if ($status_message): ?>
            <div class="alert-success"><?= htmlspecialchars($status_message) ?></div>
        <?php endif; ?>

        <div class="bot-info">
            <h2>Informasi Bot</h2>
            <p><strong>ID Telegram:</strong> <?= htmlspecialchars($bot['id']) ?></p>
            <p><strong>Nama Depan:</strong> <?= htmlspecialchars($bot['first_name']) ?></p>
            <p><strong>Username:</strong> @<?= htmlspecialchars($bot['username'] ?? 'N/A') ?></p>
            <p><strong>Token:</strong> <code><?= substr(htmlspecialchars($bot['token']), 0, 15) ?>...</code></p>
            <p><strong>Dibuat pada:</strong> <?= htmlspecialchars($bot['created_at']) ?></p>
        </div>

        <div class="actions">
            <h2>Manajemen Bot & Webhook</h2>
            <button class="set-webhook" data-bot-id="<?= $bot['id'] ?>">Set Webhook</button>
            <button class="check-webhook" data-bot-id="<?= $bot['id'] ?>">Check Webhook</button>
            <button class="delete-webhook" data-bot-id="<?= $bot['id'] ?>">Delete Webhook</button>
            <button class="test-webhook" data-telegram-bot-id="<?= htmlspecialchars($bot['id']) ?>" title="Kirim POST request kosong untuk memeriksa apakah webhook merespons 200 OK">Test Webhook</button>
            <button class="get-me" data-bot-id="<?= $bot['id'] ?>">Get Me & Update</button>
        </div>

        <div class="settings">
            <h2>Pengaturan Penyimpanan Pesan</h2>
            <p>Pilih jenis pembaruan (update) dari Telegram yang ingin Anda simpan ke database untuk bot ini.</p>
            <form action="settings_manager.php" method="post">
                <input type="hidden" name="bot_id" value="<?= $bot['id'] ?>">

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
        // Referensi ke elemen-elemen modal
        const modal = document.getElementById('responseModal');
        const modalTitle = document.getElementById('modal-title');
        const modalBody = document.getElementById('modal-body');
        const span = document.getElementsByClassName('close')[0];

        /**
         * Menampilkan modal dengan judul dan konten yang ditentukan.
         * @param {string} title - Judul modal.
         * @param {string} content - Konten yang akan ditampilkan di body modal.
         */
        function showModal(title, content) {
            modalTitle.textContent = title;
            modalBody.textContent = content;
            modal.style.display = 'block';
        }

        // Fungsionalitas untuk menutup modal
        span.onclick = function() {
            modal.style.display = 'none';
        }
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        /**
         * Menangani aksi terkait webhook (set, check, delete) dengan memanggil webhook_manager.php.
         * @param {string} action - Aksi yang akan dilakukan ('set', 'check', 'delete').
         * @param {number} botId - ID internal bot.
         */
        async function handleWebhookAction(action, botId) {
            let confirmation = true;
            if (action === 'delete') {
                confirmation = confirm('Apakah Anda yakin ingin menghapus webhook untuk bot ini?');
            }
            if (!confirmation) return;

            showModal('Hasil ' + action, 'Sedang memproses permintaan...');
            try {
                const response = await fetch('webhook_manager.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=${action}&bot_id=${botId}`
                });
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const result = await response.json();
                if (result.status === 'error') throw new Error(result.data);
                const formattedResult = JSON.stringify(result.data, null, 2);
                showModal('Hasil ' + action, formattedResult);
            } catch (error) {
                showModal('Error', 'Gagal melakukan permintaan: ' + error.message);
            }
        }

        /**
         * Menangani aksi pengujian webhook dengan mengirimkan POST request kosong ke URL webhook.
         * @param {number} botId - ID Telegram dari bot.
         */
        async function handleTestWebhook(botId) {
            // Ambil BASE_URL dari config, jika tidak ada, coba tebak dari URL saat ini
            const baseUrl = '<?= defined('BASE_URL') && BASE_URL ? rtrim(BASE_URL, '/') : (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] ?>';
            const webhookUrl = `${baseUrl}/webhook.php?id=${botId}`;

            showModal('Hasil Test POST', `Mengirim request ke:\n${webhookUrl}\n\nMohon tunggu...`);

            try {
                const response = await fetch(webhookUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ "update_id": 0, "message": { "text": "/test" } }) // Kirim update palsu
                });

                let statusText = `Status: ${response.status} ${response.statusText}`;
                let bodyText = await response.text();

                if(response.status === 200) {
                    showModal('Hasil Test POST: Sukses', `${statusText}\n\nRespons Body:\n${bodyText}\n\nWebhook tampaknya merespons dengan benar (200 OK).`);
                } else {
                    showModal('Hasil Test POST: Gagal', `${statusText}\n\nRespons Body:\n${bodyText}\n\nWebhook mengembalikan error.`);
                }

            } catch (error) {
                showModal('Error', 'Gagal melakukan permintaan fetch: ' + error.message);
            }
        }

        /**
         * Menangani aksi terkait bot, seperti 'get_me', dengan memanggil bot_manager.php.
         * @param {string} action - Aksi yang akan dilakukan.
         * @param {number} botId - ID internal bot.
         */
        async function handleBotAction(action, botId) {
            showModal('Hasil ' + action, 'Sedang memproses permintaan...');
            try {
                const response = await fetch('bot_manager.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=${action}&bot_id=${botId}`
                });
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const result = await response.json();
                if (result.status === 'error') throw new Error(result.message);

                let successMessage = `Sukses! Informasi bot telah diperbarui.\n\nNama: ${result.data.first_name}\nUsername: @${result.data.username}\n\nSilakan muat ulang halaman untuk melihat perubahan.`;
                showModal('Hasil ' + action, successMessage);

            } catch (error) {
                showModal('Error', 'Gagal melakukan permintaan: ' + error.message);
            }
        }

        // Tambahkan event listener ke tombol-tombol manajemen webhook dan bot.
        document.querySelectorAll('.set-webhook, .check-webhook, .delete-webhook').forEach(button => {
            button.addEventListener('click', function() {
                const action = this.classList.contains('set-webhook') ? 'set' :
                               this.classList.contains('check-webhook') ? 'check' : 'delete';
                handleWebhookAction(action, this.dataset.botId);
            });
        });

        document.querySelector('.get-me').addEventListener('click', function() {
            handleBotAction('get_me', this.dataset.botId);
        });

        const testWebhookBtn = document.querySelector('.test-webhook');
        if (testWebhookBtn) {
            testWebhookBtn.addEventListener('click', function() {
                handleTestWebhook(this.dataset.telegramBotId);
            });
        }
    });
</script>

<?php
require_once __DIR__ . '/../partials/footer.php';
?>
