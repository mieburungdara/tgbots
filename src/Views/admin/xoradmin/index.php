<?php
// This view assumes all necessary data is available in the $data array.
if (!function_exists('format_currency')) {
    function format_currency($number, $currency = 'Rp') {
        if (is_null($number)) {
            $number = 0;
        }
        return $currency . ' ' . number_format($number, 0, ',', '.');
    }
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

        <div class="logout-form">
            <a href="/logout" style="display: inline-block; padding: 8px 15px; background-color: #5bc0de; color: white; text-decoration: none; border-radius: 4px; font-size: 14px;">Logout</a>
        </div>

        <div class="tabs">
            <a href="?action=dashboard" class="tab-link <?= $data['active_tab'] === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
            <a href="?action=users" class="tab-link <?= $data['active_tab'] === 'users' ? 'active' : '' ?>">Manajemen Pengguna</a>
            <a href="?action=balance" class="tab-link <?= $data['active_tab'] === 'balance' ? 'active' : '' ?>">Manajemen Saldo</a>
            <a href="?action=logs" class="tab-link <?= $data['active_tab'] === 'logs' ? 'active' : '' ?>">Logs</a>
            <a href="?action=bots" class="tab-link <?= $data['active_tab'] === 'bots' || $data['active_tab'] === 'edit_bot' ? 'active' : '' ?>">Manajemen Bot</a>
            <a href="?action=roles" class="tab-link <?= $data['active_tab'] === 'roles' ? 'active' : '' ?>">Manajemen Peran</a>
            <a href="?action=db_reset" class="tab-link <?= $data['active_tab'] === 'db_reset' ? 'active' : '' ?>">Reset Database</a>
        </div>

        <div class="main-content">
            <?php if ($data['active_tab'] === 'dashboard'): ?>
            <div id="dashboard" class="tab-content active">
                <h2>Dashboard Percakapan</h2>
                <p>Pilih bot untuk melihat percakapan. Tautan percakapan akan merujuk ke panel admin lama untuk sementara waktu.</p>

                <div class="bot-selector" style="margin-bottom: 20px;">
                    <strong>Pilih Bot:</strong>
                    <?php foreach ($data['bots'] as $bot): ?>
                        <a href="?action=dashboard&bot_id=<?= $bot['id'] ?>" style="margin: 0 5px; padding: 5px 10px; border-radius: 4px; text-decoration: none; background-color: <?= ($data['selected_bot_id'] == $bot['id']) ? '#337ab7' : '#eee' ?>; color: <?= ($data['selected_bot_id'] == $bot['id']) ? 'white' : 'black' ?>;">
                            <?= htmlspecialchars($bot['first_name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if ($data['selected_bot_id']): ?>
                    <div class="search-form">
                        <form action="?action=dashboard" method="get">
                            <input type="hidden" name="action" value="dashboard">
                            <input type="hidden" name="bot_id" value="<?= htmlspecialchars($data['selected_bot_id']) ?>">
                            <input type="text" name="search_user" placeholder="Cari percakapan pengguna..." value="<?= htmlspecialchars($data['search_user']) ?>" style="width: 300px; display: inline-block;">
                            <button type="submit" style="width: auto; display: inline-block;">Cari</button>
                            <?php if(!empty($data['search_user'])): ?>
                                <a href="?action=dashboard&bot_id=<?= $data['selected_bot_id'] ?>" style="width: auto; display: inline-block;">Hapus Filter</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <h3>Percakapan Pengguna</h3>
                    <?php if (empty($data['conversations'])): ?>
                        <p>Tidak ada percakapan yang cocok dengan kriteria.</p>
                    <?php else: ?>
                        <table>
                            <thead><tr><th>Nama</th><th>Pesan Terakhir</th><th>Waktu</th><th>Aksi</th></tr></thead>
                            <tbody>
                            <?php foreach ($data['conversations'] as $conv): ?>
                                <tr>
                                    <td><?= htmlspecialchars($conv['first_name'] ?? 'Tanpa Nama') ?></td>
                                    <td><?= htmlspecialchars($conv['last_message'] ?? '...') ?></td>
                                    <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($conv['last_message_time'] ?? 'now'))) ?></td>
                                    <td><a href="/admin/chat?telegram_id=<?= $conv['telegram_id'] ?>&bot_id=<?= $data['selected_bot_id'] ?>">Lihat</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php if(empty($data['search_user'])): ?>
                    <h3 style="margin-top: 40px;">Percakapan Channel & Grup</h3>
                     <?php if (empty($data['channel_chats'])): ?>
                        <p>Belum ada pesan dari channel atau grup untuk bot ini.</p>
                    <?php else: ?>
                        <table>
                            <thead><tr><th>Nama Grup/Channel</th><th>Pesan Terakhir</th><th>Waktu</th><th>Aksi</th></tr></thead>
                            <tbody>
                            <?php foreach ($data['channel_chats'] as $chat): ?>
                                <?php
                                    $chat_title = 'Grup/Channel Tanpa Nama';
                                    if (!empty($chat['last_message_raw'])) {
                                        $raw = json_decode($chat['last_message_raw'], true);
                                        $chat_title = $raw['channel_post']['chat']['title'] ?? $raw['message']['chat']['title'] ?? $chat_title;
                                    }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($chat_title) ?></td>
                                    <td><?= htmlspecialchars($chat['last_message'] ?? '...') ?></td>
                                    <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($chat['last_message_time'] ?? 'now'))) ?></td>
                                    <td><a href="/admin/channel_chat?chat_id=<?= htmlspecialchars($chat['chat_id']) ?>&bot_id=<?= htmlspecialchars($data['selected_bot_id']) ?>">Lihat</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    <?php endif; ?>

                <?php else: ?>
                    <div style="text-align: center; padding-top: 50px; color: #6c757d;">
                        <p>Silakan pilih bot dari daftar di atas untuk melihat percakapannya.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php elseif ($data['active_tab'] === 'users'): ?>
                <div id="users" class="tab-content active">
                    <h2>Manajemen Pengguna</h2>
                    <div class="search-form" style="margin-bottom: 20px;">
                        <form action="?" method="get">
                            <input type="hidden" name="action" value="users">
                            <input type="text" name="search" placeholder="Cari ID, Nama, Username..." value="<?= htmlspecialchars($data['search_term']) ?>" style="width: 300px; display: inline-block;">
                            <button type="submit" style="width: auto; display: inline-block;">Cari</button>
                            <?php if(!empty($data['search_term'])): ?>
                                <a href="?action=users">Hapus Filter</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <p>Menampilkan <?= count($data['users']) ?> dari <?= $data['total_users'] ?> total pengguna.</p>

                    <table>
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Nama</th>
                                <th>Username</th>
                                <th>Status</th>
                                <th>Peran</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($data['users'])): ?>
                                <tr><td colspan="6" style="text-align:center;">Tidak ada pengguna ditemukan.</td></tr>
                            <?php else: ?>
                                <?php foreach ($data['users'] as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['id']) ?></td>
                                    <td><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></td>
                                    <td>@<?= htmlspecialchars($user['username'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars(ucfirst($user['status'])) ?></td>
                                    <td><?= htmlspecialchars($user['roles'] ?? 'Tidak ada') ?></td>
                                    <td><a href="?action=dashboard&search_user=<?= htmlspecialchars($user['username'] ?? $user['id']) ?>">Lihat Chat</a></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="pagination" style="margin-top: 20px;">
                        <?php
                        $currentPage = $data['page'];
                        $totalPages = $data['total_pages'];
                        $query_params = ['action' => 'users', 'search' => $data['search_term']];

                        if ($totalPages > 1):
                            if ($currentPage > 1) {
                                $query_params['page'] = $currentPage - 1;
                                echo '<a href="?' . http_build_query($query_params) . '">&laquo; Sebelumnya</a>';
                            }
                            echo '<span style="margin: 0 10px;"> Halaman ' . $currentPage . ' dari ' . $totalPages . ' </span>';
                            if ($currentPage < $totalPages) {
                                $query_params['page'] = $currentPage + 1;
                                echo '<a href="?' . http_build_query($query_params) . '">Berikutnya &raquo;</a>';
                            }
                        endif;
                        ?>
                    </div>
                </div>
            <?php elseif ($data['active_tab'] === 'balance'): ?>
                <div id="balance" class="tab-content active">
                    <h2>Manajemen Saldo</h2>
                    <?php if (!empty($_SESSION['flash_message'])): ?>
                        <div class="message"><?= htmlspecialchars($_SESSION['flash_message']); unset($_SESSION['flash_message']); ?></div>
                    <?php endif; ?>

                    <div class="search-form" style="margin-bottom: 20px;">
                        <form action="?" method="get">
                            <input type="hidden" name="action" value="balance">
                            <input type="text" name="search" placeholder="Cari Nama/Username..." value="<?= htmlspecialchars($data['search_term']) ?>" style="width: 300px; display: inline-block;">
                            <button type="submit" style="width: auto; display: inline-block;">Cari</button>
                            <?php if(!empty($data['search_term'])): ?>
                                <a href="?action=balance">Hapus Filter</a>
                            <?php endif; ?>
                        </form>
                    </div>

                     <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama</th>
                                <th>Saldo</th>
                                <th>Pemasukan</th>
                                <th>Pengeluaran</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['users_data'] as $user_data): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user_data['telegram_id']) ?></td>
                                    <td><?= htmlspecialchars(trim($user_data['first_name'] . ' ' . $user_data['last_name'])) ?></td>
                                    <td class="clickable-log" data-log-type="balance" data-telegram-id="<?= $user_data['telegram_id'] ?>" data-user-name="<?= htmlspecialchars($user_data['first_name']) ?>"><?= format_currency($user_data['balance']) ?></td>
                                    <td class="clickable-log" data-log-type="sales" data-telegram-id="<?= $user_data['telegram_id'] ?>" data-user-name="<?= htmlspecialchars($user_data['first_name']) ?>"><?= format_currency($user_data['total_income']) ?></td>
                                    <td class="clickable-log" data-log-type="purchases" data-telegram-id="<?= $user_data['telegram_id'] ?>" data-user-name="<?= htmlspecialchars($user_data['first_name']) ?>"><?= format_currency($user_data['total_spending']) ?></td>
                                    <td>
                                        <button class="open-balance-modal" data-telegram-id="<?= $user_data['telegram_id'] ?>" data-user-name="<?= htmlspecialchars(trim($user_data['first_name'] . ' ' . $user_data['last_name'])) ?>">
                                            Ubah Saldo
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="pagination" style="margin-top: 20px;">
                         <?php
                        $currentPage = $data['page'];
                        $totalPages = $data['total_pages'];
                        $query_params = ['action' => 'balance', 'search' => $data['search_term']];

                        if ($totalPages > 1):
                            if ($currentPage > 1) {
                                $query_params['page'] = $currentPage - 1;
                                echo '<a href="?' . http_build_query($query_params) . '">&laquo; Sebelumnya</a>';
                            }
                            echo '<span style="margin: 0 10px;"> Halaman ' . $currentPage . ' dari ' . $totalPages . ' </span>';
                            if ($currentPage < $totalPages) {
                                $query_params['page'] = $currentPage + 1;
                                echo '<a href="?' . http_build_query($query_params) . '">Berikutnya &raquo;</a>';
                            }
                        endif;
                        ?>
                    </div>
                </div>
            <?php elseif ($data['active_tab'] === 'bots'): ?>
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
                    <h2>Edit Bot: <?= htmlspecialchars($data['bot']['first_name']) ?> <a href="?action=bots" style="font-size: 0.7em; float: right;">&laquo; Kembali ke Daftar</a></h2>
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

            <?php elseif ($data['active_tab'] === 'check_schema'): ?>
                <div id="check_schema" class="tab-content active">
                    <h2>Hasil Pemeriksaan Skema</h2>
                    <?php
                        $report = $data['report'] ?? [];
                        $error = $data['error'] ?? null;
                        $has_differences = !empty($report['missing_tables']) || !empty($report['extra_tables']) || !empty($report['column_differences']);
                    ?>
                    <?php if ($error): ?>
                        <div class="error"><?= htmlspecialchars($error) ?></div>
                    <?php elseif (!$has_differences): ?>
                        <div class="message">✅ Skema database Anda sudah sinkron dengan file `updated_schema.sql`. Tidak ada aksi yang diperlukan.</div>
                    <?php else: ?>
                        <div class="warning">
                            <h4>Perbedaan Ditemukan</h4>
                            <p>Ditemukan perbedaan antara skema database Anda dan file `updated_schema.sql`. Harap tinjau dan jalankan query di bawah ini secara manual untuk melakukan sinkronisasi.</p>
                        </div>

                        <?php if (!empty($report['missing_tables'])): ?>
                            <h3>Tabel yang Hilang</h3>
                            <textarea readonly rows="5" style="width: 100%;"><?php
                                foreach ($report['missing_tables'] as $table) { echo htmlspecialchars($table['query'] . ";\n\n"); }
                            ?></textarea>
                        <?php endif; ?>
                        <?php if (!empty($report['extra_tables'])): ?>
                            <h3>Tabel Tambahan</h3>
                            <textarea readonly rows="3" style="width: 100%;"><?php
                                foreach ($report['extra_tables'] as $table) { echo htmlspecialchars($table['query'] . "\n"); }
                            ?></textarea>
                        <?php endif; ?>
                        <?php if (!empty($report['column_differences'])): ?>
                            <h3>Perbedaan Kolom</h3>
                            <?php foreach ($report['column_differences'] as $table_name => $diffs): ?>
                                <h4>Tabel: <?= htmlspecialchars($table_name) ?></h4>
                                <?php if (!empty($diffs['missing'])): ?>
                                    <h5>Kolom Hilang (ADD):</h5>
                                    <textarea readonly rows="3" style="width: 100%;"><?php foreach ($diffs['missing'] as $col) { echo htmlspecialchars($col['query'] . "\n"); } ?></textarea>
                                <?php endif; ?>
                                <?php if (!empty($diffs['extra'])): ?>
                                    <h5>Kolom Tambahan (DROP):</h5>
                                    <textarea readonly rows="3" style="width: 100%;"><?php foreach ($diffs['extra'] as $col) { echo htmlspecialchars($col['query'] . "\n"); } ?></textarea>
                                <?php endif; ?>
                                <?php if (!empty($diffs['modified'])): ?>
                                    <h5>Kolom Berubah (MODIFY):</h5>
                                     <textarea readonly rows="3" style="width: 100%;"><?php foreach ($diffs['modified'] as $col) { echo htmlspecialchars($col['query'] . "\n"); } ?></textarea>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php elseif ($data['active_tab'] === 'logs'): ?>
                <div id="logs" class="tab-content active">
                    <h2>Logs</h2>
                    <div class="tabs">
                        <a href="?action=logs&type=app" class="tab-link <?= $data['log_type'] === 'app' ? 'active' : '' ?>">App Logs</a>
                        <a href="?action=logs&type=media" class="tab-link <?= $data['log_type'] === 'media' ? 'active' : '' ?>">Media Logs</a>
                        <a href="?action=logs&type=telegram" class="tab-link <?= $data['log_type'] === 'telegram' ? 'active' : '' ?>">Telegram Error Logs</a>
                        <a href="?action=debug_feed" class="tab-link <?= $data['active_tab'] === 'debug_feed' ? 'active' : '' ?>">Debug Feed</a>
                    </div>
                    <div class="log-content" style="padding-top: 20px;">
                        <?php if ($data['log_type'] === 'app'): ?>
                            <div>
                                <form action="?" method="get" style="display:inline-block;">
                                    <input type="hidden" name="action" value="logs">
                                    <input type="hidden" name="type" value="app">
                                    <label for="level_select">Filter by Level:</label>
                                    <select name="level" id="level_select" onchange="this.form.submit()">
                                        <option value="all" <?= ($data['selected_level'] === 'all') ? 'selected' : '' ?>>All Levels</option>
                                        <?php foreach ($data['log_levels'] as $level): ?>
                                            <option value="<?= htmlspecialchars($level) ?>" <?= ($data['selected_level'] === $level) ? 'selected' : '' ?>>
                                                <?= ucfirst(htmlspecialchars($level)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                                <form action="/xoradmin/clearAppLogs" method="post" style="display:inline-block;" onsubmit="return confirm('Anda yakin ingin MENGHAPUS SEMUA log aplikasi?');">
                                    <button type="submit">Clear App Logs</button>
                                </form>
                            </div>
                            <table>
                                <thead><tr><th>ID</th><th>Timestamp</th><th>Level</th><th>Message</th><th>Context</th></tr></thead>
                                <tbody>
                                    <?php foreach ($data['logs'] as $log): ?>
                                    <tr>
                                        <td><?= $log['id'] ?></td>
                                        <td><?= $log['created_at'] ?></td>
                                        <td><?= $log['level'] ?></td>
                                        <td><?= htmlspecialchars($log['message']) ?></td>
                                        <td>
                                            <?php if (!empty($log['context'])): ?>
                                                <button class="context-toggle-button" data-target="context-<?= $log['id'] ?>">Show</button>
                                                <pre id="context-<?= $log['id'] ?>" style="display: none;"><code><?= htmlspecialchars(json_encode(json_decode($log['context']), JSON_PRETTY_PRINT))) ?></code></pre>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <!-- Pagination for App Logs here -->
                        <?php elseif ($data['log_type'] === 'media'): ?>
                            <table>
                                <thead><tr><th>Waktu</th><th>Pengguna</th><th>Bot</th><th>Detail Media</th><th>Aksi</th></tr></thead>
                                <tbody>
                                    <?php foreach ($data['grouped_logs'] as $group): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($group['group_info']['time']) ?></td>
                                        <td><?= htmlspecialchars($group['group_info']['user']) ?></td>
                                        <td><?= htmlspecialchars($group['group_info']['bot']) ?></td>
                                        <td>
                                            <?php foreach ($group['items'] as $item): ?>
                                            <div><?= htmlspecialchars($item['type']) ?>: <?= htmlspecialchars($item['file_name'] ?? '') ?></div>
                                            <?php endforeach; ?>
                                        </td>
                                        <td>
                                            <button class="btn-forward" data-group-id="<?= htmlspecialchars($group['group_info']['media_group_id'] ?? 'single_' . $group['items'][0]['id']) ?>" data-bot-id="<?= htmlspecialchars($group['group_info']['bot_id']) ?>">
                                                Teruskan
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php elseif ($data['log_type'] === 'telegram'): ?>
                             <table>
                                <thead><tr><th>Waktu</th><th>Metode</th><th>Chat ID</th><th>Kode</th><th>Deskripsi</th><th>Request</th></tr></thead>
                                <tbody>
                                    <?php foreach ($data['logs'] as $log): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($log['created_at']) ?></td>
                                        <td><?= htmlspecialchars($log['method']) ?></td>
                                        <td><?= htmlspecialchars($log['chat_id'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($log['error_code'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($log['description']) ?></td>
                                        <td>
                                            <?php if (!empty($log['request_data'])): ?>
                                                <button class="context-toggle-button" data-target="context-<?= $log['id'] ?>">Show</button>
                                                <pre id="context-<?= $log['id'] ?>" style="display: none;"><code><?= htmlspecialchars(json_encode(json_decode($log['request_data']), JSON_PRETTY_PRINT))) ?></code></pre>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <!-- Pagination for Telegram logs here -->
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($data['active_tab'] === 'debug_feed'): ?>
                <div id="debug_feed" class="tab-content active">
                    <h2>Raw Telegram Update Feed</h2>
                    <table>
                        <thead><tr><th>ID</th><th>Timestamp</th><th>Payload</th></tr></thead>
                        <tbody>
                            <?php foreach ($data['updates'] as $update): ?>
                            <tr>
                                <td><?= htmlspecialchars($update['id']) ?></td>
                                <td><?= htmlspecialchars($update['created_at']) ?></td>
                                <td><pre><code><?= htmlspecialchars(json_encode(json_decode($update['payload']), JSON_PRETTY_PRINT)) ?></code></pre></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <!-- Pagination -->
                </div>
            <?php elseif ($data['active_tab'] === 'db_reset'): ?>
                <div id="db_reset" class="tab-content danger-zone active">
                    <h2>Reset Database & Skema</h2>
                    <a href="?action=check_schema" style="display: inline-block; margin-bottom: 20px; padding: 10px 15px; background-color: #5bc0de; color: white; text-decoration: none; border-radius: 4px;">Periksa Skema Database</a>
                    <div class="warning"><strong>PERINGATAN:</strong> Semua data akan hilang secara permanen.</div>
                    <?php if (!empty($data['db_error'])): ?><div class="error"><?= htmlspecialchars($data['db_error']); ?></div><?php endif; ?>
                    <?php if (!empty($data['db_message'])): ?><div class="message"><?= htmlspecialchars($data['db_message']); ?></div><?php endif; ?>
                    <form action="/xoradmin/reset_db" method="post" onsubmit="return confirm('APAKAH ANDA YAKIN INGIN MERESET DATABASE?');">
                        <input type="submit" value="HAPUS DAN RESET DATABASE SEKARANG">
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="responseModal" class="modal"><div class="modal-content"><span class="close">&times;</span><h2 id="modal-title">Hasil Aksi</h2><pre id="modal-body">Memproses...</pre></div></div>

    <!-- Modal untuk Ubah Saldo -->
    <div id="balance-modal" class="modal" style="z-index: 1060;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title-adjust">Ubah Saldo untuk Pengguna</h2>
                <span class="close-button close-balance-modal">&times;</span>
            </div>
            <form action="/xoradmin/adjustBalance?<?= http_build_query($_GET) ?>" method="post">
                <input type="hidden" name="user_id" id="modal-user-id">
                <label for="modal-amount">Jumlah:</label>
                <input type="number" name="amount" id="modal-amount" step="0.01" min="0.01" required>
                <label for="modal-description">Deskripsi:</label>
                <input type="text" name="description" id="modal-description" required>
                <button type="submit" name="action" value="add_balance">Tambah Saldo</button>
                <button type="submit" name="action" value="subtract_balance">Kurangi Saldo</button>
            </form>
        </div>
    </div>

    <!-- Modal untuk Log Transaksi -->
    <div id="log-modal" class="modal" style="z-index: 1070;">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2 id="log-modal-title">Riwayat Transaksi</h2>
                <span class="close-button close-log-modal">&times;</span>
            </div>
            <div id="log-modal-body" style="max-height: 60vh; overflow-y: auto;"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleButtons = document.querySelectorAll('.context-toggle-button');
            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const targetPre = document.getElementById(targetId);
                    if (targetPre) {
                        const isHidden = targetPre.style.display === 'none';
                        targetPre.style.display = isHidden ? 'block' : 'none';
                        this.textContent = isHidden ? 'Hide' : 'Show';
                    }
                });
            });

            document.querySelectorAll('.btn-forward').forEach(button => {
                button.addEventListener('click', function() {
                    const groupId = this.dataset.groupId;
                    const botId = this.dataset.botId;
                    if (confirm('Anda yakin ingin meneruskan media ini ke semua admin?')) {
                        handleAjaxAction('forward_media', botId, { group_id: groupId });
                    }
                });
            });
        });

        const modal = document.getElementById('responseModal');
        const modalTitle = document.getElementById('modal-title');
        const modalBody = document.getElementById('modal-body');
        const span = document.getElementsByClassName('close')[0];

        function showModal(title, content) { modalTitle.textContent = title; modalBody.textContent = content; modal.style.display = 'block'; }
        if(span) span.onclick = function() { modal.style.display = 'none'; }
        window.onclick = function(event) { if (event.target == modal) modal.style.display = 'none'; }

        async function handleAjaxAction(action, botId, extraData = {}) {
            let confirmation = action !== 'delete-webhook' || confirm('Yakin ingin menghapus webhook?');
            if (!confirmation) return;

            showModal('Hasil ' + action, 'Memproses...');
            try {
                const formData = new FormData();
                formData.append('action', action);
                if (botId) formData.append('bot_id', botId);
                for(const key in extraData) {
                    formData.append(key, extraData[key]);
                }

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

        // Balance Modals Logic
        const balanceModal = document.getElementById('balance-modal');
        if(balanceModal) {
            const balanceModalTitle = document.getElementById('modal-title-adjust');
            const balanceModalUserIdInput = document.getElementById('modal-user-id');
            document.querySelectorAll('.open-balance-modal').forEach(button => {
                button.addEventListener('click', function() {
                    balanceModalTitle.textContent = 'Ubah Saldo untuk ' + this.dataset.userName;
                    balanceModalUserIdInput.value = this.dataset.telegramId;
                    balanceModal.style.display = 'flex';
                });
            });
            document.querySelectorAll('.close-balance-modal').forEach(button => button.addEventListener('click', () => balanceModal.style.display = 'none'));
            if(balanceModal) balanceModal.addEventListener('click', e => { if (e.target === balanceModal) balanceModal.style.display = 'none'; });
        }

        const logModal = document.getElementById('log-modal');
        if(logModal) {
            const logModalTitle = document.getElementById('log-modal-title');
            const logModalBody = document.getElementById('log-modal-body');
            document.querySelectorAll('.clickable-log').forEach(cell => {
                cell.addEventListener('click', async function() {
                    const telegramId = this.dataset.telegramId;
                    const logType = this.dataset.logType;

                    logModalBody.innerHTML = '<p>Memuat data...</p>';
                    logModal.style.display = 'flex';
                    logModalTitle.textContent = `Riwayat untuk ${this.dataset.userName}`;

                    try {
                        const formData = new FormData();
                        formData.append('action', `get_${logType}_log`);
                        formData.append('telegram_id', telegramId);

                        const response = await fetch('/api/xoradmin', { method: 'POST', body: formData });
                        const result = await response.json();

                        if (result.status !== 'success' || !result.data) throw new Error(result.message || 'Data tidak ditemukan.');

                        if (result.data.length === 0) {
                            logModalBody.innerHTML = '<p>Tidak ada riwayat ditemukan.</p>';
                            return;
                        }

                        const table = document.createElement('table');
                        table.className = 'chat-log-table';
                        const thead = document.createElement('thead');
                        const tbody = document.createElement('tbody');
                        const headers = Object.keys(result.data[0]);

                        let headerRow = '<tr>';
                        headers.forEach(h => headerRow += `<th>${h}</th>`);
                        headerRow += '</tr>';
                        thead.innerHTML = headerRow;

                        let tableBody = '';
                        result.data.forEach(item => {
                            tableBody += '<tr>';
                            headers.forEach(h => {
                                let val = item[h] || '';
                                if(h.includes('price') || h.includes('amount')) val = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(val);
                                tableBody += `<td>${val}</td>`
                            });
                            tableBody += '</tr>';
                        });
                        tbody.innerHTML = tableBody;

                        table.appendChild(thead);
                        table.appendChild(tbody);
                        logModalBody.innerHTML = '';
                        logModalBody.appendChild(table);

                    } catch (error) {
                        logModalBody.innerHTML = `<p style="color:red;">Gagal memuat data: ${error.message}</p>`;
                    }
                });
            });
            document.querySelectorAll('.close-log-modal').forEach(button => button.addEventListener('click', () => logModal.style.display = 'none'));
            if(logModal) logModal.addEventListener('click', e => { if (e.target === logModal) logModal.style.display = 'none'; });
        }
    </script>
</body>
</html>
