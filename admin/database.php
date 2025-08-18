<?php
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("Koneksi database gagal.");
}

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'run_migrations') {
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
    } elseif ($_POST['action'] === 'clean_database') {
        try {
            clean_transactional_data($pdo);
            $_SESSION['message'] = "Berhasil! Semua data pengguna, konten, dan penjualan telah dihapus.";
        } catch (Exception $e) {
            $_SESSION['message'] = "Gagal membersihkan database: " . $e->getMessage();
        }
    }
    header("Location: database.php");
    exit;
}

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

$page_title = 'Manajemen Database';
require_once __DIR__ . '/../partials/header.php';
?>

<h1>Manajemen Database</h1>

        <?php if ($message): ?>
            <div class="alert alert-info">
                <?= nl2br(htmlspecialchars($message)); ?>
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

        <div class="danger-zone">
            <h2>Zona Berbahaya</h2>
            <p class="description">
                <strong>PERINGATAN:</strong> Aksi di bawah ini bersifat destruktif dan tidak dapat diurungkan.
                Gunakan ini untuk membersihkan data transaksional (seperti pengguna, konten, penjualan) jika Anda ingin memulai ulang dari awal tanpa menghapus konfigurasi bot atau channel.
            </p>
            <form action="database.php" method="post" onsubmit="return confirm('PERINGATAN KERAS!\n\nAnda akan MENGHAPUS SEMUA data pengguna, konten, dan penjualan secara permanen.\n\nAksi ini tidak dapat diurungkan.\n\nLanjutkan?');">
                <input type="hidden" name="action" value="clean_database">
                <button type="submit" class="btn btn-danger">Bersihkan Semua Data Transaksional</button>
            </form>
        </div>

<?php
require_once __DIR__ . '/../partials/footer.php';
?>
