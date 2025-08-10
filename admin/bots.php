<?php
session_start();
require_once __DIR__ . '/../core/database.php';

$pdo = get_db_connection();
$error = null;
$success = null;

if (!$pdo) {
    die("Koneksi database gagal. Periksa file log untuk detailnya.");
}

// Handle penambahan bot baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bot'])) {
    $name = trim($_POST['name']);
    $token = trim($_POST['token']);

    if (empty($name) || empty($token)) {
        $error = "Nama bot dan token tidak boleh kosong.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO bots (name, token) VALUES (?, ?)");
            $stmt->execute([$name, $token]);
            $success = "Bot '{$name}' berhasil ditambahkan! Harap set webhook untuk bot ini ke: <br><code>" . htmlspecialchars("https://<YOUR_DOMAIN>/webhook.php?bot_id=" . $pdo->lastInsertId()) . "</code>";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Kode untuk duplicate entry
                $error = "Error: Token ini sudah ada di database.";
            } else {
                $error = "Gagal menyimpan bot ke database: " . $e->getMessage();
            }
        }
    }
}

// Ambil daftar bot yang ada
$bots = $pdo->query("SELECT id, name, created_at FROM bots ORDER BY created_at DESC")->fetchAll();

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
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
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
            <a href="bots.php" class="active">Kelola Bot</a>
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
            <input type="text" name="name" placeholder="Nama Bot (misal: Bot Toko Online)" required>
            <input type="text" name="token" placeholder="Token API dari BotFather" required>
            <button type="submit" name="add_bot">Tambah Bot</button>
        </form>

        <h2>Daftar Bot</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama</th>
                    <th>Tanggal Dibuat</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bots)): ?>
                    <tr>
                        <td colspan="3">Belum ada bot yang ditambahkan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($bots as $bot): ?>
                        <tr>
                            <td><?= htmlspecialchars($bot['id']) ?></td>
                            <td><?= htmlspecialchars($bot['name']) ?></td>
                            <td><?= htmlspecialchars($bot['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
