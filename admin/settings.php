<?php
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/database/PrivateChannelRepository.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("Koneksi database gagal.");
}

$channelRepo = new PrivateChannelRepository($pdo);
$message = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_channel') {
        $channel_id = filter_input(INPUT_POST, 'channel_id', FILTER_VALIDATE_INT);
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        if ($channel_id && $name) {
            if ($channelRepo->addChannel($channel_id, $name)) {
                $_SESSION['message'] = "Channel '{$name}' berhasil ditambahkan.";
            } else {
                $_SESSION['message'] = "Gagal menambahkan channel. Mungkin ID sudah ada atau terjadi error lain.";
            }
        } else {
            $_SESSION['message'] = "Data channel tidak valid.";
        }
        header("Location: settings.php");
        exit;
    }

    if ($action === 'delete_channel') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            if ($channelRepo->deleteChannel($id)) {
                $_SESSION['message'] = "Channel berhasil dihapus.";
            } else {
                $_SESSION['message'] = "Gagal menghapus channel.";
            }
        }
        header("Location: settings.php");
        exit;
    }

    if ($action === 'run_migrations') {
        ensure_migrations_table_exists($pdo);

    // 2. Dapatkan migrasi yang sudah dieksekusi
    $executed_migrations = $pdo->query("SELECT migration_file FROM migrations")->fetchAll(PDO::FETCH_COLUMN);

    // 3. Pindai direktori migrasi
    $migration_files_path = __DIR__ . '/../migrations/';
    $all_migration_files = glob($migration_files_path . '*.sql');

    $migrations_to_run = [];
    foreach ($all_migration_files as $file_path) {
        $file_name = basename($file_path);
        if (!in_array($file_name, $executed_migrations)) {
            $migrations_to_run[] = $file_name;
        }
    }

    sort($migrations_to_run); // Pastikan urutan eksekusi benar

    $results = [];
    $error = false;

    if (empty($migrations_to_run)) {
        $_SESSION['message'] = "Database sudah paling baru. Tidak ada migrasi yang perlu dijalankan.";
    } else {
        foreach ($migrations_to_run as $migration_file) {
            try {
                $sql = file_get_contents($migration_files_path . $migration_file);
                $pdo->exec($sql);

                // Catat migrasi yang berhasil
                $stmt = $pdo->prepare("INSERT INTO migrations (migration_file) VALUES (?)");
                $stmt->execute([$migration_file]);

                $results[] = "OK: " . $migration_file;
            } catch (Exception $e) {
                $results[] = "ERROR: " . $migration_file . " - " . $e->getMessage();
                $error = true;
                break; // Hentikan jika ada error
            }
        }

        if ($error) {
            $_SESSION['message'] = "Terjadi error saat migrasi:\n" . implode("\n", $results);
        } else {
            $_SESSION['message'] = "Migrasi berhasil dijalankan:\n" . implode("\n", $results);
        }
    }
        header("Location: settings.php");
        exit;
    }
}

// Pesan untuk ditampilkan setelah proses
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Get current channels for display
$private_channels = $channelRepo->getAllChannels();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - Admin Panel</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 40px; background-color: #f4f6f8; color: #333; }
        .container { max-width: 800px; margin: 0 auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; }
        nav { margin-bottom: 20px; }
        nav a { text-decoration: none; color: #007bff; padding: 10px; }
        nav a.active { font-weight: bold; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert-info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .btn { padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; }
        .btn:hover { background-color: #0056b3; }
        .btn-danger { background-color: #dc3545; }
        .btn-danger:hover { background-color: #c82333; }
        .description { margin-bottom: 20px; line-height: 1.6; }
        .mt-20 { margin-top: 40px; }
        .mb-20 { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 8px; box-sizing: border-box; border-radius: 4px; border: 1px solid #ccc; }
    </style>
</head>
<body>
    <div class="container">
        <nav>
            <a href="index.php">Percakapan</a> |
            <a href="bots.php">Kelola Bot</a> |
            <a href="users.php">Pengguna</a> |
            <a href="roles.php">Manajemen Peran</a> |
            <a href="media_logs.php">Log Media</a> |
            <a href="settings.php" class="active">Pengaturan</a> |
            <a href="logs.php">Logs</a>
        </nav>

        <h1>Pengaturan Aplikasi</h1>

        <?php if ($message): ?>
            <div class="alert alert-info">
                <?php echo nl2br(htmlspecialchars($message)); ?>
            </div>
        <?php endif; ?>

        <hr>

        <h2>Kelola Channel Penyimpanan</h2>
        <p class="description">
            Tambahkan atau hapus channel pribadi yang akan digunakan bot untuk menyimpan file media.
            Bot akan mendistribusikan file ke channel-channel ini secara bergantian (round-robin) untuk setiap bot.
        </p>

        <h3>Tambah Channel Baru</h3>
        <form action="settings.php" method="post" class="mb-20">
            <input type="hidden" name="action" value="add_channel">
            <div class="form-group">
                <label for="name">Nama Channel (untuk referensi)</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="channel_id">ID Channel Telegram</label>
                <input type="text" id="channel_id" name="channel_id" required placeholder="-100123456789">
            </div>
            <button type="submit" class="btn">Tambah Channel</button>
        </form>

        <h3>Daftar Channel Tersimpan</h3>
        <table>
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>ID Channel</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($private_channels)): ?>
                    <tr>
                        <td colspan="3">Belum ada channel yang ditambahkan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($private_channels as $channel): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($channel['name']); ?></td>
                            <td><?php echo htmlspecialchars($channel['channel_id']); ?></td>
                            <td>
                                <form action="settings.php" method="post" onsubmit="return confirm('Yakin ingin menghapus channel ini?');">
                                    <input type="hidden" name="action" value="delete_channel">
                                    <input type="hidden" name="id" value="<?php echo $channel['id']; ?>">
                                    <button type="submit" class="btn btn-danger">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="mt-20">
            <hr>
        </div>

        <h2 class="mt-20">Database</h2>
        <p class="description">
            Gunakan tombol di bawah ini untuk menjalankan pembaruan skema database.
            Sistem akan secara otomatis menerapkan file migrasi baru dari direktori <code>/migrations</code>
            tanpa menghapus data yang ada di tabel yang tidak terpengaruh.
        </p>
        <form action="settings.php" method="post" onsubmit="return confirm('Apakah Anda yakin ingin menjalankan migrasi database? Proses ini tidak dapat diurungkan.');">
            <input type="hidden" name="action" value="run_migrations">
            <button type="submit" class="btn">Jalankan Migrasi Database</button>
        </form>
    </div>
</body>
</html>
