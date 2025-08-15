<?php
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/TelegramAPI.php';

$pdo = get_db_connection();
$error = null;
$success = null;

if (!$pdo) {
    die("Koneksi database gagal. Pastikan file `config.php` sudah benar. Periksa file log server untuk detailnya.");
}

// Handle penambahan bot baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bot'])) {
    $token = trim($_POST['token']);

    if (empty($token)) {
        $error = "Token tidak boleh kosong.";
    } else {
        try {
            $telegram_api = new TelegramAPI($token);
            $bot_info = $telegram_api->getMe();

            if (isset($bot_info['ok']) && $bot_info['ok'] === true) {
                $bot_result = $bot_info['result'];
                $first_name = $bot_result['first_name'];
                $username = $bot_result['username'] ?? null;
                $telegram_id = $bot_result['id'];

                // Cek apakah token sudah ada
                $stmt_check = $pdo->prepare("SELECT id FROM bots WHERE token = ?");
                $stmt_check->execute([$token]);
                if ($stmt_check->fetch()) {
                     throw new Exception("Token ini sudah ada di database.", 23000);
                }

                $stmt = $pdo->prepare("INSERT INTO bots (first_name, username, token) VALUES (?, ?, ?)");
                $stmt->execute([$first_name, $username, $token]);

                $bot_id_from_token = explode(':', $token)[0];
                $success = "Bot '{$first_name}' (@{$username}) berhasil ditambahkan!";

            } else {
                throw new Exception("Token tidak valid atau gagal menghubungi API Telegram. " . ($bot_info['description'] ?? ''));
            }
        } catch (Exception $e) {
            if ($e->getCode() == 23000) {
                $error = "Error: " . $e->getMessage();
            } else {
                $error = "Gagal menambahkan bot: " . $e->getMessage();
            }
        }
    }
}

// Ambil daftar bot yang ada
$bots = $pdo->query("SELECT id, first_name, username, token, created_at FROM bots ORDER BY created_at DESC")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Bot - Admin Panel</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 40px; background-color: #f4f6f8; color: #333; }
        .container { max-width: 800px; margin: 0 auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; }
        form { margin-top: 20px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9; }
        input[type="text"] { width: calc(100% - 22px); padding: 10px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        .btn-edit { display: inline-block; padding: 5px 10px; background-color: #28a745; color: white; text-decoration: none; border-radius: 4px; }
        .btn-edit:hover { background-color: #218838; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; word-wrap: break-word; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        nav { margin-bottom: 20px; }
        nav a { text-decoration: none; color: #007bff; padding: 10px; }
        nav a.active { font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <nav>
            <a href="index.php">Percakapan</a> |
            <a href="bots.php" class="active">Kelola Bot</a> |
            <a href="users.php">Pengguna</a> |
            <a href="roles.php">Manajemen Peran</a> |
            <a href="packages.php">Konten</a> |
            <a href="media_logs.php">Log Media</a> |
            <a href="channels.php">Channel</a> |
            <a href="database.php">Database</a> |
            <a href="logs.php">Logs</a> |
            <a href="telegram_logs.php">Log Error Telegram</a>
        </nav>

        <h1>Kelola Bot Telegram</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <h2>Tambah Bot Baru</h2>
        <form action="bots.php" method="post">
            <input type="text" name="token" placeholder="Token API dari BotFather" required>
            <button type="submit" name="add_bot">Tambah Bot</button>
        </form>

        <h2>Daftar Bot</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama</th>
                    <th>Username</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bots)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center;">Belum ada bot yang ditambahkan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($bots as $bot): ?>
                        <?php
                            $telegram_bot_id = explode(':', $bot['token'])[0];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($telegram_bot_id) ?></td>
                            <td><?= htmlspecialchars($bot['first_name']) ?></td>
                            <td>@<?= htmlspecialchars($bot['username'] ?? 'N/A') ?></td>
                            <td>
                                <a href="edit_bot.php?id=<?= htmlspecialchars($telegram_bot_id) ?>" class="btn-edit">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
