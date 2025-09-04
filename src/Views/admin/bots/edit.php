<?php
// This view assumes the following variables are available in the $data array:
// 'page_title', 'bot', 'settings', 'status_message'
?>

<style>
    .modal { display: none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
    .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 8px; }
    .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; }
    .close:hover, .close:focus { color: black; text-decoration: none; cursor: pointer; }
    #modal-body { white-space: pre-wrap; background-color: #eee; padding: 15px; border-radius: 5px; }
    .actions button { margin-right: 10px; margin-bottom: 10px; }
</style>

<h1><?= htmlspecialchars($data['page_title']) ?></h1>

<?php if ($data['status_message']): ?>
    <div class="alert alert-success" style="padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: .25rem; color: #155724; background-color: #d4edda; border-color: #c3e6cb;"><?= htmlspecialchars($data['status_message']) ?></div>
<?php endif; ?>

<div class="bot-info">
    <h2>Informasi Bot</h2>
    <p><strong>ID Telegram:</strong> <?= htmlspecialchars($data['bot']['id']) ?></p>
    <p><strong>Nama Depan:</strong> <?= htmlspecialchars($data['bot']['first_name']) ?></p>
    <p><strong>Username:</strong> @<?= htmlspecialchars($data['bot']['username'] ?? 'N/A') ?></p>
    <p><strong>Token:</strong> <code><?= substr(htmlspecialchars($data['bot']['token']), 0, 15) ?>...</code></p>
    <p><strong>Dibuat pada:</strong> <?= htmlspecialchars($data['bot']['created_at']) ?></p>
</div>

<div class="actions">
    <h2>Manajemen Bot & Webhook</h2>
    <p>Gunakan tombol di bawah untuk mengelola webhook bot. Aksi ini akan memanggil file `webhook_manager.php` dan `bot_manager.php` yang ada.</p>
    <button class="set-webhook" data-bot-id="<?= $data['bot']['id'] ?>">Set Webhook</button>
    <button class="check-webhook" data-bot-id="<?= $data['bot']['id'] ?>">Check Webhook</button>
    <button class="delete-webhook" data-bot-id="<?= $data['bot']['id'] ?>">Delete Webhook</button>
    <button class="test-webhook" data-telegram-bot-id="<?= htmlspecialchars($data['bot']['id']) ?>" title="Kirim POST request kosong untuk memeriksa apakah webhook merespons 200 OK">Test Webhook</button>
    <button class="get-me" data-bot-id="<?= $data['bot']['id'] ?>">Get Me & Update</button>
</div>

<div class="settings">
    <h2>Pengaturan Penyimpanan Pesan</h2>
    <p>Pilih jenis pembaruan (update) dari Telegram yang ingin Anda simpan ke database untuk bot ini.</p>
    <form action="/xoradmin/bots/settings" method="post">
        <input type="hidden" name="bot_id" value="<?= htmlspecialchars($data['bot']['id']) ?>">

        <div class="setting-item" style="margin-bottom: 10px;">
            <label>
                <input type="checkbox" name="settings[save_text_messages]" value="1" <?= $data['settings']['save_text_messages'] ? 'checked' : '' ?>>
                Simpan Pesan Teks
            </label>
        </div>
        <div class="setting-item" style="margin-bottom: 10px;">
            <label>
                <input type="checkbox" name="settings[save_media_messages]" value="1" <?= $data['settings']['save_media_messages'] ? 'checked' : '' ?>>
                Simpan Pesan Media (Foto, Video, dll.)
            </label>
        </div>
        <div class="setting-item" style="margin-bottom: 10px;">
            <label>
                <input type="checkbox" name="settings[save_callback_queries]" value="1" <?= $data['settings']['save_callback_queries'] ? 'checked' : '' ?>>
                Simpan Penekanan Tombol (Callback Query)
            </label>
        </div>
        <div class="setting-item" style="margin-bottom: 10px;">
            <label>
                <input type="checkbox" name="settings[save_edited_messages]" value="1" <?= $data['settings']['save_edited_messages'] ? 'checked' : '' ?>>
                Simpan Pesan yang Diedit
            </label>
        </div>

        <hr style="margin: 20px 0;">

        <h3>Fitur Khusus Bot</h3>
        <p>Pilih satu fitur utama untuk bot ini. Bot hanya akan merespons perintah yang sesuai dengan fiturnya.</p>
        <div class="setting-item" style="margin-bottom: 10px;">
            <label for="assigned_feature">Fitur yang Ditugaskan:</label>
            <select name="assigned_feature" id="assigned_feature" style="padding: 5px; border-radius: 4px; border: 1px solid #ccc;">
                <?php $feature = $data['bot']['assigned_feature'] ?? null; ?>
                <option value="" <?= empty($feature) ? 'selected' : '' ?>>-- Tidak ada --</option>
                <option value="sell" <?= ($feature === 'sell') ? 'selected' : '' ?>>Jual (/sell)</option>
                <option value="rate" <?= ($feature === 'rate') ? 'selected' : '' ?>>Rating (/rate)</option>
                <option value="tanya" <?= ($feature === 'tanya') ? 'selected' : '' ?>>Tanya (/tanya)</option>
            </select>
        </div>

        <br>
        <button type="submit">Simpan Pengaturan</button>
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

        async function handleApiAction(action, botId, confirmationMessage = null) {
            if (confirmationMessage && !confirm(confirmationMessage)) {
                return;
            }

            showModal('Hasil ' + action, 'Sedang memproses permintaan...');
            try {
                const response = await fetch(`/api/xoradmin/bots/${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `bot_id=${botId}`
                });

                const result = await response.json();
                if (!response.ok) {
                    throw new Error(result.error || `HTTP error! status: ${response.status}`);
                }

                let resultText = typeof result === 'object' ? JSON.stringify(result, null, 2) : result;

                if (action === 'get-me' && result.success) {
                    resultText = `Sukses! Informasi bot telah diperbarui.\n\nNama: ${result.data.first_name}\nUsername: @${result.data.username}\n\nSilakan muat ulang halaman untuk melihat perubahan.`;
                } else if (action === 'test-webhook') {
                    resultText = `Status: ${result.status_code}\n\nRespons Body:\n${result.body}`;
                }

                showModal('Hasil ' + action, resultText);

            } catch (error) {
                showModal('Error', 'Gagal melakukan permintaan: ' + error.message);
            }
        }

        document.querySelector('.set-webhook').addEventListener('click', function() {
            handleApiAction('set-webhook', this.dataset.botId);
        });
        document.querySelector('.check-webhook').addEventListener('click', function() {
            handleApiAction('check-webhook', this.dataset.botId);
        });
        document.querySelector('.delete-webhook').addEventListener('click', function() {
            handleApiAction('delete-webhook', this.dataset.botId, 'Anda yakin ingin menghapus webhook untuk bot ini?');
        });
        document.querySelector('.get-me').addEventListener('click', function() {
            handleApiAction('get-me', this.dataset.botId);
        });
        document.querySelector('.test-webhook').addEventListener('click', function() {
            handleApiAction('test-webhook', this.dataset.botId);
        });
    });
</script>
