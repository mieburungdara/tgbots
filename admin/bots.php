<?php
/**
 * Halaman Manajemen Bot (Admin).
 *
 * Halaman ini memungkinkan administrator untuk menambah dan melihat daftar
 * bot Telegram yang dikelola oleh sistem.
 *
 * Fitur:
 * - Formulir untuk menambahkan bot baru menggunakan token API-nya.
 * - Validasi token dengan memanggil metode `getMe` dari API Telegram.
 * - Menyimpan informasi bot (nama, username, token) ke database.
 * - Menampilkan daftar semua bot yang sudah ada dalam sebuah tabel.
 */
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/TelegramAPI.php';

$pdo = get_db_connection();
$error = null;
$success = null;

if (!$pdo) {
    die("Koneksi database gagal. Pastikan file `config.php` sudah benar. Periksa file log server untuk detailnya.");
}

// Menangani logika penambahan bot baru saat formulir disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bot'])) {
    $token = trim($_POST['token']);

    if (empty($token)) {
        $error = "Token tidak boleh kosong.";
    } else {
        try {
            // 1. Validasi token dengan menghubungi API Telegram
            $telegram_api = new TelegramAPI($token);
            $bot_info = $telegram_api->getMe();

            if (isset($bot_info['ok']) && $bot_info['ok'] === true) {
                $bot_result = $bot_info['result'];
                $first_name = $bot_result['first_name'];
                $username = $bot_result['username'] ?? null;
                $bot_id = $bot_result['id'];

                // 2. Cek duplikasi (token atau ID bot) di database
                $stmt_check_token = $pdo->prepare("SELECT id FROM bots WHERE token = ?");
                $stmt_check_token->execute([$token]);
                if ($stmt_check_token->fetch()) {
                     throw new Exception("Token ini sudah ada di database.", 23000);
                }

                $stmt_check_id = $pdo->prepare("SELECT id FROM bots WHERE id = ?");
                $stmt_check_id->execute([$bot_id]);
                if ($stmt_check_id->fetch()) {
                     throw new Exception("Bot dengan ID Telegram {$bot_id} ini sudah terdaftar.", 23000);
                }

                // 3. Simpan bot baru ke database
                $stmt = $pdo->prepare("INSERT INTO bots (id, first_name, username, token) VALUES (?, ?, ?, ?)");
                $stmt->execute([$bot_id, $first_name, $username, $token]);

                $success = "Bot '{$first_name}' (@{$username}) berhasil ditambahkan!";

            } else {
                // Jika `getMe` gagal, token tidak valid
                throw new Exception("Token tidak valid atau gagal menghubungi API Telegram. " . ($bot_info['description'] ?? ''));
            }
        } catch (Exception $e) {
            // Tangani error, termasuk error duplikasi dari database
            if ($e->getCode() == 23000) { // SQLSTATE 23000: Integrity constraint violation
                $error = "Error: " . $e->getMessage();
            } else {
                $error = "Gagal menambahkan bot: " . $e->getMessage();
            }
        }
    }
}

// Ambil daftar bot yang ada
$bots = $pdo->query("SELECT id, first_name, username, created_at FROM bots ORDER BY created_at DESC")->fetchAll();

$page_title = 'Kelola Bot';
require_once __DIR__ . '/../partials/header.php';
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
            <th>ID Bot</th>
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
                <tr>
                    <td><?= htmlspecialchars($bot['id']) ?></td>
                    <td><?= htmlspecialchars($bot['first_name']) ?></td>
                    <td>@<?= htmlspecialchars($bot['username'] ?? 'N/A') ?></td>
                    <td>
                        <a href="edit_bot.php?id=<?= htmlspecialchars($bot['id']) ?>" class="btn btn-edit">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
