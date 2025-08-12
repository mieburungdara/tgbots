<?php
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("Koneksi database gagal.");
}

$message = null;

// Handle permintaan untuk menjalankan migrasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_migrations') {
    ensure_migrations_table_exists($pdo);

    $executed_migrations = $pdo->query("SELECT migration_file FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
    $migration_files_path = __DIR__ . '/../migrations/';
    $all_migration_files = glob($migration_files_path . '*.sql');

    $migrations_to_run = [];
    foreach ($all_migration_files as $file_path) {
        $file_name = basename($file_path);
        if (!in_array($file_name, $executed_migrations)) {
            $migrations_to_run[] = $file_name;
        }
    }

    sort($migrations_to_run);

    $results = [];
    $error = false;

    if (empty($migrations_to_run)) {
        $_SESSION['message'] = "Database sudah paling baru. Tidak ada migrasi yang perlu dijalankan.";
    } else {
        foreach ($migrations_to_run as $migration_file) {
            try {
                $sql = file_get_contents($migration_files_path . $migration_file);
                $pdo->exec($sql);
                $stmt = $pdo->prepare("INSERT INTO migrations (migration_file) VALUES (?)");
                $stmt->execute([$migration_file]);
                $results[] = "OK: " . $migration_file;
            } catch (Exception $e) {
                $results[] = "ERROR: " . $migration_file . " - " . $e->getMessage();
                $error = true;
                break;
            }
        }

        if ($error) {
            $_SESSION['message'] = "Terjadi error saat migrasi:\n" . implode("\n", $results);
        } else {
            $_SESSION['message'] = "Migrasi berhasil dijalankan:\n" . implode("\n", $results);
        }
    }
    header("Location: database.php");
    exit;
}

// Pesan untuk ditampilkan setelah proses
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database - Admin Panel</title>
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
        .description { margin-bottom: 20px; line-height: 1.6; }
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
            <a href="channels.php">Channel</a> |
            <a href="database.php" class="active">Database</a> |
            <a href="logs.php">Logs</a>
        </nav>

        <h1>Database</h1>

        <?php if ($message): ?>
            <div class="alert alert-info">
                <?php echo nl2br(htmlspecialchars($message)); ?>
            </div>
        <?php endif; ?>

        <h2>Migrasi Database</h2>
        <p class="description">
            Gunakan tombol di bawah ini untuk menjalankan pembaruan skema database.
            Sistem akan secara otomatis menerapkan file migrasi baru dari direktori <code>/migrations</code>
            tanpa menghapus data yang ada di tabel yang tidak terpengaruh.
        </p>
        <form action="database.php" method="post" onsubmit="return confirm('Apakah Anda yakin ingin menjalankan migrasi database? Proses ini tidak dapat diurungkan.');">
            <input type="hidden" name="action" value="run_migrations">
            <button type="submit" class="btn">Jalankan Migrasi Database</button>
        </form>
    </div>
</body>
</html>
