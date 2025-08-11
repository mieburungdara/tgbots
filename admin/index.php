<?php
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("Koneksi database gagal. Pastikan file `config.php` sudah benar. Periksa file log server untuk detailnya.");
}

// Cek apakah tabel sudah dibuat
try {
    $tables_exist = check_tables_exist($pdo);
} catch (PDOException $e) {
    die("Error saat memeriksa database: " . $e->getMessage());
}

if (!$tables_exist) {
    // Tabel tidak ada, coba jalankan setup otomatis
    $setup_success = setup_database($pdo);
    if ($setup_success) {
        // Setup berhasil, muat ulang halaman untuk melanjutkan
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    } else {
        // Setup gagal, tampilkan pesan error
        $setup_error_message = "<strong>Gagal melakukan setup database secara otomatis!</strong><br>" .
            "Pastikan kredensial database di `config.php` sudah benar dan server database berjalan.<br><br>" .
            "Anda juga bisa mencoba mengimpor file <code>setup.sql</code> secara manual.";

        include __DIR__ . '/../core/templates/setup_error.php';
        exit;
    }
}

// Ambil daftar bot untuk dropdown
$bots = $pdo->query("SELECT id, name, token FROM bots ORDER BY name ASC")->fetchAll();

// Dapatkan bot_id yang dipilih dari URL, jika ada
$selected_telegram_bot_id = isset($_GET['bot_id']) ? (string)$_GET['bot_id'] : null;
$chats = [];
$internal_bot_id = null;

if ($selected_telegram_bot_id) {
    // Cari ID internal bot berdasarkan ID bot telegram
    $stmt = $pdo->prepare("SELECT id FROM bots WHERE token LIKE ?");
    $stmt->execute([$selected_telegram_bot_id . ':%']);
    $internal_bot_id = $stmt->fetchColumn();

    if ($internal_bot_id) {
        // Ambil semua percakapan untuk bot yang dipilih menggunakan skema baru
        $stmt = $pdo->prepare(
            "SELECT
                u.id as user_internal_id,
                u.telegram_id,
                u.first_name,
                u.username,
                (SELECT text FROM messages
                 WHERE user_id = u.id AND bot_id = r.bot_id
                 ORDER BY id DESC LIMIT 1) as last_message,
                (SELECT telegram_timestamp FROM messages
                 WHERE user_id = u.id AND bot_id = r.bot_id
                 ORDER BY id DESC LIMIT 1) as last_message_time
            FROM users u
            JOIN rel_user_bot r ON u.id = r.user_id
            WHERE r.bot_id = ?
            ORDER BY last_message_time DESC"
        );
        $stmt->execute([$internal_bot_id]);
        $conversations = $stmt->fetchAll();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Percakapan - Admin Panel</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 40px; background-color: #f4f6f8; color: #333; }
        .container { max-width: 900px; margin: 0 auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:hover { background-color: #f1f1f1; }
        .bot-selector { margin-bottom: 20px; }
        label { font-weight: bold; margin-right: 10px; }
        select { padding: 8px; border-radius: 4px; border: 1px solid #ccc; }
        nav { margin-bottom: 20px; }
        nav a { text-decoration: none; color: #007bff; padding: 10px; }
        nav a.active { font-weight: bold; }
        a { color: #007bff; text-decoration: none; }
        .last-message { color: #555; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <nav>
            <a href="index.php" class="active">Percakapan</a> |
            <a href="bots.php">Kelola Bot</a> |
            <a href="users.php">Pengguna</a> |
            <a href="settings.php">Pengaturan</a> |
            <a href="logs.php">Logs</a>
        </nav>

        <h1>Daftar Percakapan</h1>

        <div class="bot-selector">
            <form action="index.php" method="get">
                <label for="bot_id">Pilih Bot:</label>
                <select name="bot_id" id="bot_id" onchange="this.form.submit()">
                    <option value="">-- Pilih Bot --</option>
                    <?php foreach ($bots as $bot): ?>
                        <?php $telegram_bot_id = explode(':', $bot['token'])[0]; ?>
                        <option value="<?= $telegram_bot_id ?>" <?= ($selected_telegram_bot_id == $telegram_bot_id) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($bot['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($selected_telegram_bot_id): ?>
            <?php if (!$internal_bot_id): ?>
                <p>Bot dengan ID <?= htmlspecialchars($selected_telegram_bot_id) ?> tidak ditemukan.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Nama Pengguna</th>
                            <th>Username</th>
                            <th>Pesan Terakhir</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($conversations)): ?>
                            <tr>
                                <td colspan="4">Belum ada percakapan untuk bot ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($conversations as $conv): ?>
                                <tr>
                                    <td>
                                        <a href="chat.php?user_id=<?= $conv['user_internal_id'] ?>&bot_id=<?= $internal_bot_id ?>">
                                            <?= htmlspecialchars($conv['first_name']) ?>
                                        </a>
                                    </td>
                                    <td>@<?= htmlspecialchars($conv['username']) ?></td>
                                <td class="last-message"><?= htmlspecialchars(mb_strimwidth($conv['last_message'], 0, 50, "...")) ?></td>
                                <td><?= htmlspecialchars($conv['last_message_time']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php endif; ?>
        <?php else: ?>
            <p>Silakan pilih bot untuk melihat percakapannya.</p>
        <?php endif; ?>

    </div>
</body>
</html>
