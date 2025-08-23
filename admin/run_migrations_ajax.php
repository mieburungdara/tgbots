<?php
/**
 * AJAX Handler untuk Menjalankan Migrasi Database.
 *
 * Versi sederhana: Menjalankan migrasi dan menampilkan output mentah sebagai text/plain.
 */
header('Content-Type: text/plain; charset=utf-8');
session_start();

try {
    require_once __DIR__ . '/../core/database.php';
    require_once __DIR__ . '/../core/helpers.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Metode permintaan harus POST.");
    }

    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        throw new Exception("Akses ditolak. Anda harus login sebagai admin.");
    }

    $pdo = get_db_connection();
    if (!$pdo) {
        throw new Exception("Koneksi database gagal.");
    }

    ensure_migrations_table_exists($pdo);
    $executed_migrations = $pdo->query("SELECT migration_file FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
    $migration_files_path = __DIR__ . '/../migrations/';
    $all_migration_files = glob($migration_files_path . '*.{sql,php}', GLOB_BRACE);

    $migrations_to_run = [];
    foreach ($all_migration_files as $file_path) {
        $file_name = basename($file_path);
        if (!in_array($file_name, $executed_migrations)) {
            $migrations_to_run[] = $file_name;
        }
    }
    sort($migrations_to_run);

    if (empty($migrations_to_run)) {
        echo "Database sudah paling baru. Tidak ada migrasi yang perlu dijalankan.";
    } else {
        echo "Memulai proses migrasi...\n\n";
        foreach ($migrations_to_run as $migration_file) {
            echo "==================================================\n";
            echo "Menjalankan migrasi: {$migration_file}\n";
            echo "==================================================\n";

            $file_path = $migration_files_path . $migration_file;
            $extension = pathinfo($file_path, PATHINFO_EXTENSION);

            $pdo->beginTransaction();
            try {
                if ($extension === 'sql') {
                    $sql = file_get_contents($file_path);
                    $pdo->exec($sql);
                    echo "Skrip SQL berhasil dieksekusi.\n";
                } elseif ($extension === 'php') {
                    require $file_path; // Output dari skrip ini akan langsung di-echo
                }

                $stmt = $pdo->prepare("INSERT INTO migrations (migration_file) VALUES (?)");
                $stmt->execute([$migration_file]);
                $pdo->commit();
                echo "\nStatus: SUKSES\n\n";

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo "\n!!! ERROR !!!\n";
                echo "Gagal pada migrasi: " . $migration_file . "\n";
                echo "Pesan Error: " . $e->getMessage() . "\n";
                echo "\nProses migrasi dihentikan.\n";
                exit; // Hentikan jika ada error
            }
        }
        echo "Semua migrasi berhasil dijalankan.";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error Kritis: " . $e->getMessage();
}

exit;
