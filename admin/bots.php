<?php
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

$page_title = 'Kelola Bot';
include __DIR__ . '/partials/header.php';
?>

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
                        <a href="edit_bot.php?id=<?= htmlspecialchars($telegram_bot_id) ?>" class="btn btn-edit">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php include __DIR__ . '/partials/footer.php'; ?>
