<?php
// WARNING: This script is highly destructive and provides powerful admin capabilities.
// Use with extreme caution. It is recommended to delete this file after use.

session_start();

// --- Konfigurasi ---
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}
$correct_password = defined('XOR_ADMIN_PASSWORD') ? XOR_ADMIN_PASSWORD : 'sup3r4dmin';
$sql_schema_file = 'updated_schema.sql';

// --- Inisialisasi ---
$is_authenticated = isset($_SESSION['is_authenticated']) && $_SESSION['is_authenticated'];
$pdo = null;
if ($is_authenticated) {
    require_once __DIR__ . '/core/database.php';
    require_once __DIR__ . '/core/helpers.php';
    require_once __DIR__ . '/core/TelegramAPI.php';
    $pdo = get_db_connection();
}

// --- Page Request Handler (Non-AJAX) ---
$error = '';
$db_message = '';
$db_error = '';
$bot_message = '';
$bot_error = '';
$action_view = 'list_bots';
$active_tab = 'bots';

if (isset($_GET['action']) && $_GET['action'] === 'db_reset') {
    $action_view = 'db_reset';
    $active_tab = 'db_reset';
} elseif (isset($_GET['action']) && $_GET['action'] === 'roles') {
    $action_view = 'roles';
    $active_tab = 'roles';
}

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
        if (isset($_POST['confirm_reset'])) {
            $action_view = 'db_reset';
            $active_tab = 'db_reset';
            try {
                $db_message .= "Berhasil terhubung ke database.<br>";
                $pdo->exec('SET FOREIGN_KEY_CHECKS=0;');
                $db_message .= "Pemeriksaan foreign key dinonaktifkan.<br>";
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                if (empty($tables)) {
                    $db_message .= "Tidak ada tabel untuk dihapus.<br>";
                } else {
                    foreach ($tables as $table) {
                        $pdo->exec("DROP TABLE IF EXISTS `$table`");
                    }
                    $db_message .= "<b>Semua tabel berhasil dihapus.</b><br>";
                }
                if (!file_exists($sql_schema_file)) throw new Exception("File skema '$sql_schema_file' tidak ditemukan.");
                $sql_script = file_get_contents($sql_schema_file);
                if ($sql_script === false) throw new Exception("Gagal membaca file skema '$sql_schema_file'.");
                $pdo->exec($sql_script);
                $db_message .= "<b>Skema database berhasil dibuat ulang dari '$sql_schema_file'.</b><br>";
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1;');
                $db_message .= "Pemeriksaan foreign key diaktifkan kembali.<br>";
                $db_message .= "<br><b style='color: green;'>PROSES RESET DATABASE SELESAI.</b>";
            } catch (Exception $e) {
                $db_error = "Terjadi kesalahan: " . $e->getMessage();
            }
        }

        if (isset($_POST['add_bot'])) {
            $token = trim($_POST['token']);
            if (empty($token)) {
                $bot_error = "Token tidak boleh kosong.";
            } else {
                try {
                    $telegram_api = new TelegramAPI($token);
                    $bot_info = $telegram_api->getMe();
                    if (isset($bot_info['ok']) && $bot_info['ok'] === true) {
                        $bot_result = $bot_info['result'];
                        $first_name = $bot_result['first_name'];
                        $username = $bot_result['username'] ?? null;
                        $telegram_bot_id = $bot_result['id'];

                        $stmt_check_id = $pdo->prepare("SELECT id FROM bots WHERE id = ?");
                        $stmt_check_id->execute([$telegram_bot_id]);
                        if ($stmt_check_id->fetch()) {
                             throw new Exception("Bot dengan ID Telegram {$telegram_bot_id} ini sudah terdaftar.", 23000);
                        }

                        $stmt = $pdo->prepare("INSERT INTO bots (id, first_name, username, token) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$telegram_bot_id, $first_name, $username, $token]);
                        $bot_message = "Bot '{$first_name}' (@{$username}) berhasil ditambahkan!";
                    } else {
                        throw new Exception("Token tidak valid atau gagal menghubungi API Telegram. " . ($bot_info['description'] ?? ''));
                    }
                } catch (Exception $e) {
                    if ($e->getCode() == 23000) {
                        $bot_error = "Error: " . $e->getMessage();
                    } else {
                        $bot_error = "Gagal menambahkan bot: " . $e->getMessage();
                    }
                }
            }
        }

        if (isset($_POST['save_bot_settings'])) {
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

if ($is_authenticated && $pdo) {
    if (isset($_GET['action']) && $_GET['action'] === 'edit_bot' && isset($_GET['id'])) {
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

    // Fetch users and roles for the new tab
    $users_with_roles = [];
    if ($action_view === 'roles') {
        $sql = "
            SELECT u.id, u.first_name, u.last_name, u.username, GROUP_CONCAT(r.name SEPARATOR ', ') as roles
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            GROUP BY u.id
            ORDER BY u.first_name, u.last_name
        ";
        $users_with_roles = $pdo->query($sql)->fetchAll();
    }
    // Fetch all available roles for the modal
    $all_roles = $pdo->query("SELECT id, name FROM roles ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
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
        .role-badge { display: inline-block; padding: 0.25em 0.6em; font-size: 75%; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: 0.375rem; color: #fff; background-color: #6c757d; margin-right: 5px; }
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
                <a href="?action=list_bots" class="tab-link <?= $active_tab === 'bots' ? 'active' : '' ?>">Manajemen Bot</a>
                <a href="?action=roles" class="tab-link <?= $active_tab === 'roles' ? 'active' : '' ?>">Manajemen Peran</a>
                <a href="?action=db_reset" class="tab-link <?= $active_tab === 'db_reset' ? 'active' : '' ?>">Reset Database</a>
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
                <?php elseif ($action_view === 'roles'): ?>
                    <div id="roles" class="tab-content active">
                        <h2>Manajemen Peran Pengguna</h2>
                        <p>Tetapkan peran untuk setiap pengguna. Peran "Admin" akan memberikan akses ke panel admin utama.</p>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID Pengguna</th>
                                    <th>Nama</th>
                                    <th>Username</th>
                                    <th>Peran Saat Ini</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users_with_roles)): ?>
                                    <tr><td colspan="5" style="text-align: center;">Tidak ada pengguna ditemukan.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($users_with_roles as $user): ?>
                                        <tr id="user-row-<?= $user['id'] ?>">
                                            <td><?= htmlspecialchars($user['id']) ?></td>
                                            <td><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></td>
                                            <td>@<?= htmlspecialchars($user['username'] ?? 'N/A') ?></td>
                                            <td class="roles-cell">
                                                <?php if (!empty($user['roles'])): ?>
                                                    <?php foreach (explode(', ', $user['roles']) as $role): ?>
                                                        <span class="role-badge"><?= htmlspecialchars($role) ?></span>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span style="color: #888;">Tidak ada</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn-manage-roles" data-user-id="<?= $user['id'] ?>" data-user-name="<?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>">Kelola Peran</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($action_view === 'db_reset'): ?>
                    <div id="db_reset" class="tab-content danger-zone active">
                        <h2>Reset Database</h2>
                        <div class="warning"><strong>PERINGATAN:</strong> Semua data akan hilang secara permanen.</div>
                        <?php if ($db_error): ?><div class="error"><?= htmlspecialchars($db_error) ?></div><?php endif; ?>
                        <?php if ($db_message): ?><div class="message"><?= $db_message ?></div><?php endif; ?>
                        <form action="xoradmin.php" method="post" onsubmit="return confirm('APAKAH ANDA YAKIN INGIN MERESET DATABASE?');">
                            <input type="hidden" name="confirm_reset" value="1">
                            <input type="submit" value="HAPUS DAN RESET DATABASE SEKARANG">
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal for Webhook/Bot Actions -->
    <div id="responseModal" class="modal"><div class="modal-content"><span class="close">&times;</span><h2 id="modal-title">Hasil Aksi</h2><pre id="modal-body">Memproses...</pre></div></div>

    <!-- Modal for Role Management -->
    <div id="rolesModal" class="modal" style="display:none;"><div class="modal-content"><span class="close">&times;</span><h2 id="roles-modal-title">Kelola Peran</h2><form id="roles-form"><input type="hidden" id="modal-user-id" name="user_id"><div id="roles-checkbox-container"></div><button type="button" id="save-roles-button">Simpan</button></form></div></div>

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

                const response = await fetch('xoradminapi.php', { method: 'POST', body: formData });
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const result = await response.json();
                if (result.status === 'error') throw new Error(result.message);

                let successMessage = result.message + '\n\n' + JSON.stringify(result.data, null, 2);
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

        // --- Role Management Modal JS ---
        const rolesModal = document.getElementById('rolesModal');
        if (rolesModal) {
            const rolesCloseButton = rolesModal.querySelector('.close');
            const manageRolesButtons = document.querySelectorAll('.btn-manage-roles');
            const saveRolesButton = document.getElementById('save-roles-button');
            const rolesCheckboxContainer = document.getElementById('roles-checkbox-container');
            const modalUserIdInput = document.getElementById('modal-user-id');
            const rolesModalTitle = document.getElementById('roles-modal-title');
            const allRoles = <?= json_encode($all_roles ?? []) ?>;

            function openRolesModal(userId, userName) {
                modalUserIdInput.value = userId;
                rolesModalTitle.textContent = 'Kelola Peran untuk ' + userName;
                rolesCheckboxContainer.innerHTML = 'Memuat peran...';
                rolesModal.style.display = 'block';

                const formData = new FormData();
                formData.append('action', 'get_user_roles');
                formData.append('user_id', userId);

                fetch('xoradminapi.php', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status !== 'success') throw new Error(data.message);
                        populateRolesCheckboxes(data.role_ids);
                    })
                    .catch(error => {
                        rolesCheckboxContainer.innerHTML = `<p style="color:red;">Gagal memuat peran: ${error.message}</p>`;
                    });
            }

            function populateRolesCheckboxes(assignedRoleIds) {
                rolesCheckboxContainer.innerHTML = '';
                allRoles.forEach(role => {
                    const isChecked = assignedRoleIds.includes(parseInt(role.id, 10));
                    rolesCheckboxContainer.innerHTML += `<div><label><input type="checkbox" name="role_ids[]" value="${role.id}" ${isChecked ? 'checked' : ''}> ${role.name}</label></div>`;
                });
            }

            function closeRolesModal() {
                rolesModal.style.display = 'none';
            }

            manageRolesButtons.forEach(button => {
                button.addEventListener('click', function() {
                    openRolesModal(this.dataset.userId, this.dataset.userName);
                });
            });

            saveRolesButton.addEventListener('click', function() {
                const userId = modalUserIdInput.value;
                const checkedRoles = Array.from(rolesCheckboxContainer.querySelectorAll('input:checked')).map(cb => cb.value);

                const formData = new FormData();
                formData.append('action', 'update_user_roles');
                formData.append('user_id', userId);
                formData.append('role_ids', JSON.stringify(checkedRoles));

                saveRolesButton.textContent = 'Menyimpan...';
                saveRolesButton.disabled = true;

                fetch('xoradminapi.php', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status !== 'success') throw new Error(data.message);
                        closeRolesModal();
                        location.reload(); // Easiest way to show the updated roles
                    })
                    .catch(error => alert('Gagal menyimpan: ' + error.message))
                    .finally(() => {
                        saveRolesButton.textContent = 'Simpan';
                        saveRolesButton.disabled = false;
                    });
            });

            rolesCloseButton.onclick = closeRolesModal;
            window.addEventListener('click', function(event) {
                if (event.target == rolesModal) closeRolesModal();
            });
        }
        <?php endif; ?>
    </script>
</body>
</html>
