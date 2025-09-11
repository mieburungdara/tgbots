<?php
/**
 * Skrip Command-Line Interface (CLI) untuk Menjalankan Migrasi Database.
 *
 * CARA PENGGUNAAN:
 * 1. Pastikan Anda memiliki akses ke command line (terminal atau SSH) di server hosting Anda.
 * 2. Navigasi ke direktori root aplikasi Anda.
 * 3. Jalankan perintah: `php run_migrations_cli.php`
 *
 * Jika Anda tidak memiliki akses SSH, Anda mungkin bisa menggunakan fitur "Cron Job"
 * di cPanel atau panel hosting lainnya untuk menjalankan perintah di atas satu kali.
 *
 * Skrip ini aman untuk dijalankan berulang kali. Ia akan secara otomatis mendeteksi
 * migrasi mana yang belum dijalankan dan hanya akan menjalankan yang baru.
 */

// Hanya izinkan eksekusi dari command line
if (php_sapi_name() !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    die("Akses Ditolak. Skrip ini hanya dapat dijalankan dari command line (CLI).");
}

echo "=============================================\n";
echo "===       SKRIP MIGRASI DATABASE          ===\n";
echo "=============================================\n\n";

try {
    // Sertakan file-file yang diperlukan
    require_once __DIR__ . '/core/autoloader.php';
    require_once __DIR__ . '/core/database.php';
    use TGBot\Logger;

    $logger = new Logger('migrations', __DIR__ . '/logs/migrations.log');

    echo "Menghubungkan ke database...
";
    $pdo = get_db_connection($logger);
    if (!$pdo) {
        throw new Exception("Koneksi database gagal. Periksa config.php dan log error.");
    }
    echo "Koneksi berhasil.

";

    // Pastikan tabel 'migrations' ada
    ensure_migrations_table_exists($pdo, $logger);
    echo "Tabel pelacak migrasi ('migrations') sudah siap.
";

    // Dapatkan daftar migrasi yang sudah dieksekusi
    $executed_migrations = $pdo->query("SELECT migration_file FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
    echo "Ditemukan " . count($executed_migrations) . " migrasi yang sudah dijalankan sebelumnya.\n";

    // Dapatkan semua file migrasi yang tersedia
    $migration_files_path = __DIR__ . '/migrations/';
    $all_migration_files = glob($migration_files_path . '*.{sql,php}', GLOB_BRACE);

    // Filter untuk mendapatkan migrasi yang belum dijalankan
    $migrations_to_run = [];
    foreach ($all_migration_files as $file_path) {
        $file_name = basename($file_path);
        if (!in_array($file_name, $executed_migrations)) {
            $migrations_to_run[] = $file_name;
        }
    }
    sort($migrations_to_run); // Jalankan dalam urutan nama file

    if (empty($migrations_to_run)) {
        echo "\n---------------------------------------------\n";
        echo "HASIL: Database sudah paling baru. Tidak ada migrasi yang perlu dijalankan.\n";
        echo "---------------------------------------------\n";
    } else {
        echo "Ditemukan " . count($migrations_to_run) . " migrasi baru yang akan dijalankan:\n";
        foreach ($migrations_to_run as $file) {
            echo " - " . $file . "\n";
        }
        echo "\nMemulai proses migrasi...\n\n";

        foreach ($migrations_to_run as $migration_file) {
            echo "---------------------------------------------\n";
            echo "--> Menjalankan: {$migration_file}\n";

            $file_path = $migration_files_path . $migration_file;
            $extension = pathinfo($file_path, PATHINFO_EXTENSION);

            $pdo->beginTransaction();
            try {
                if ($extension === 'sql') {
                    $sql = file_get_contents($file_path);
                    $pdo->exec($sql);
                    echo "    Skrip SQL berhasil dieksekusi.\n";
                } elseif ($extension === 'php') {
                    // require akan menjalankan skrip PHP
                    require $file_path;
                }

                // Catat migrasi yang berhasil ke database
                $stmt = $pdo->prepare("INSERT INTO migrations (migration_file) VALUES (?)");
                $stmt->execute([$migration_file]);
                $pdo->commit();
                echo "--> Status: SUKSES\n";

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo "\n!!!!!!!!!!!!!!!!!! ERROR !!!!!!!!!!!!!!!!!!\n";
                echo "Gagal pada migrasi: " . $migration_file . "\n";
                echo "Pesan Error: " . $e->getMessage() . "\n";
                echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n\n";
                echo "Proses migrasi dihentikan karena terjadi error.\n";
                exit(1); // Keluar dengan status error
            }
        }
        echo "\n---------------------------------------------\n";
        echo "HASIL: Semua migrasi baru berhasil dijalankan.\n";
        echo "---------------------------------------------\n";
    }
} catch (Throwable $e) {
    echo "\n!!!!!!!!!!!!!!!!!! ERROR KRITIS !!!!!!!!!!!!!!!!!!\n";
    echo "Pesan Error: " . $e->getMessage() . "\n";
    echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n\n";
    exit(1); // Keluar dengan status error
}

exit(0); // Keluar dengan status sukses
