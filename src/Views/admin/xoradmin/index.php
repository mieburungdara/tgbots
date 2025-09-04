<?php
// This view assumes all necessary data is available in the $data array.
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

        <form action="/xoradmin/logout" method="post" class="logout-form">
            <input type="submit" value="Logout">
        </form>

        <div class="tabs">
            <a href="?action=bots" class="tab-link <?= $data['active_tab'] === 'bots' || $data['active_tab'] === 'edit_bot' ? 'active' : '' ?>">Manajemen Bot</a>
            <a href="?action=roles" class="tab-link <?= $data['active_tab'] === 'roles' ? 'active' : '' ?>">Manajemen Peran</a>
            <a href="?action=db_reset" class="tab-link <?= $data['active_tab'] === 'db_reset' ? 'active' : '' ?>">Reset Database</a>
        </div>

        <div class="main-content">
            <?php if ($data['active_tab'] === 'bots'): ?>
                <div id="bots" class="tab-content active">
                    <h2>Manajemen Bot</h2>
                    <?php if (!empty($_SESSION['bot_error'])): ?><div class="error"><?= htmlspecialchars($_SESSION['bot_error']); unset($_SESSION['bot_error']); ?></div><?php endif; ?>
                    <?php if (!empty($_SESSION['bot_message'])): ?><div class="message"><?= htmlspecialchars($_SESSION['bot_message']); unset($_SESSION['bot_message']); ?></div><?php endif; ?>

                    <h3>Tambah Bot Baru</h3>
                    <form action="/xoradmin/add_bot" method="post">
                        <input type="text" name="token" placeholder="Token API dari BotFather" required>
                        <button type="submit">Tambah Bot</button>
                    </form>

                    <h3>Daftar Bot</h3>
                    <table>
                        <thead><tr><th>ID Bot</th><th>Nama</th><th>Username</th><th>Aksi</th></tr></thead>
                        <tbody>
                            <?php if (empty($data['bots'])): ?>
                                <tr><td colspan="4" style="text-align: center;">Belum ada bot yang ditambahkan.</td></tr>
                            <?php else: ?>
                                <?php foreach ($data['bots'] as $b): ?>
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

            <?php elseif ($data['active_tab'] === 'edit_bot' && isset($data['bot'])): ?>
                <div id="edit-bot" class="tab-content active">
                    <h2>Edit Bot: <?= htmlspecialchars($data['bot']['first_name']) ?> <a href="/xoradmin?action=bots" style="font-size: 0.7em; float: right;">&laquo; Kembali ke Daftar</a></h2>
                    <?php if ($data['status_message']): ?><div class="alert-success"><?= htmlspecialchars($data['status_message']) ?></div><?php endif; ?>
                    <div class="bot-info">
                        <h3>Informasi Bot</h3>
                        <p><strong>ID Telegram:</strong> <?= htmlspecialchars($data['bot']['id']) ?></p>
                        <p><strong>Username:</strong> @<?= htmlspecialchars($data['bot']['username'] ?? 'N/A') ?></p>
                        <p><strong>Token:</strong> <code><?= substr(htmlspecialchars($data['bot']['token']), 0, 15) ?>...</code></p>
                    </div>
                    <div class="actions">
                        <h3>Manajemen Bot & Webhook</h3>
                        <button class="set-webhook" data-bot-id="<?= $data['bot']['id'] ?>">Set Webhook</button>
                        <button class="check-webhook" data-bot-id="<?= $data['bot']['id'] ?>">Check Webhook</button>
                        <button class="delete-webhook" data-bot-id="<?= $data['bot']['id'] ?>">Delete Webhook</button>
                        <button class="get-me" data-bot-id="<?= $data['bot']['id'] ?>">Get Me & Update</button>
                    </div>
                    <div class="settings">
                        <h3>Pengaturan Penyimpanan Pesan</h3>
                        <form action="/xoradmin/save_bot_settings" method="post">
                            <input type="hidden" name="bot_id" value="<?= $data['bot']['id'] ?>">
                            <?php foreach ($data['settings'] as $key => $value): ?>
                            <label><input type="checkbox" name="settings[<?= $key ?>]" value="1" <?= $value ? 'checked' : '' ?>> <?= ucwords(str_replace('_', ' ', $key)) ?></label><br>
                            <?php endforeach; ?>
                            <button type="submit">Simpan Pengaturan</button>
                        </form>
                    </div>
                </div>

            <?php elseif ($data['active_tab'] === 'roles'): ?>
                <div id="roles" class="tab-content active">
                    <h2>Manajemen Peran Pengguna</h2>
                    <p>Tetapkan peran "Admin" untuk pengguna. Pengguna dengan peran ini akan mendapatkan akses ke panel admin utama.</p>
                    <table>
                        <thead><tr><th>ID</th><th>Nama</th><th>Username</th><th>Peran</th><th>Aksi</th></tr></thead>
                        <tbody>
                            <?php if (empty($data['users_with_roles'])): ?>
                                <tr><td colspan="5" style="text-align: center;">Tidak ada pengguna ditemukan.</td></tr>
                            <?php else: ?>
                                <?php foreach ($data['users_with_roles'] as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['id']) ?></td>
                                        <td><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></td>
                                        <td>@<?= htmlspecialchars($user['username'] ?? 'N/A') ?></td>
                                        <td><?= !empty($user['roles']) ? htmlspecialchars($user['roles']) : '<span style="color: #888;">Tidak ada</span>' ?></td>
                                        <td>
                                            <?php if (strpos($user['roles'] ?? '', 'Admin') === false): ?>
                                                <button class="btn-make-admin" data-user-id="<?= htmlspecialchars($user['id']) ?>">Jadikan Admin</button>
                                            <?php else: ?>
                                                <span style="color: green;">✓ Admin</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($data['active_tab'] === 'db_reset'): ?>
                <div id="db_reset" class="tab-content danger-zone active">
                    <h2>Reset Database & Skema</h2>
                    <a href="/admin/database/check" style="display: inline-block; margin-bottom: 20px; padding: 10px 15px; background-color: #5bc0de; color: white; text-decoration: none; border-radius: 4px;">Periksa Skema Database</a>
                    <div class="warning"><strong>PERINGATAN:</strong> Semua data akan hilang secara permanen.</div>
                    <?php if (!empty($_SESSION['db_error'])): ?><div class="error"><?= htmlspecialchars($_SESSION['db_error']); unset($_SESSION['db_error']); ?></div><?php endif; ?>
                    <?php if (!empty($_SESSION['db_message'])): ?><div class="message"><?= htmlspecialchars($_SESSION['db_message']); unset($_SESSION['db_message']); ?></div><?php endif; ?>
                    <form action="/xoradmin/reset_db" method="post" onsubmit="return confirm('APAKAH ANDA YAKIN INGIN MERESET DATABASE?');">
                        <input type="submit" value="HAPUS DAN RESET DATABASE SEKARANG">
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="responseModal" class="modal"><div class="modal-content"><span class="close">&times;</span><h2 id="modal-title">Hasil Aksi</h2><pre id="modal-body">Memproses...</pre></div></div>

    <script>
        const modal = document.getElementById('responseModal');
        const modalTitle = document.getElementById('modal-title');
        const modalBody = document.getElementById('modal-body');
        const span = document.getElementsByClassName('close')[0];

        function showModal(title, content) { modalTitle.textContent = title; modalBody.textContent = content; modal.style.display = 'block'; }
        span.onclick = function() { modal.style.display = 'none'; }
        window.onclick = function(event) { if (event.target == modal) modal.style.display = 'none'; }

        async function handleAjaxAction(action, botId) {
            let confirmation = action !== 'delete-webhook' || confirm('Yakin ingin menghapus webhook?');
            if (!confirmation) return;

            showModal('Hasil ' + action, 'Memproses...');
            try {
                const formData = new FormData();
                formData.append('action', action);
                if (botId) formData.append('bot_id', botId);

                const response = await fetch('/api/xoradmin', { method: 'POST', body: formData });
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const result = await response.json();
                if (result.status === 'error') throw new Error(result.message);

                let successMessage = (result.message || 'Sukses') + '\n\n' + JSON.stringify(result.data, null, 2);
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

        document.querySelectorAll('.btn-make-admin').forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.dataset.userId;
                if (confirm(`Apakah Anda yakin ingin menjadikan pengguna ID ${userId} sebagai Admin?`)) {
                    const formData = new FormData();
                    formData.append('action', 'make_admin');
                    formData.append('user_id', userId);

                    fetch('/api/xoradmin', { method: 'POST', body: formData })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                alert('Pengguna berhasil dijadikan Admin.');
                                location.reload();
                            } else { throw new Error(data.message); }
                        })
                        .catch(error => { alert('Gagal: ' + error.message); });
                }
            });
        });
    </script>
</body>
</html>
