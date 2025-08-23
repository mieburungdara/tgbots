<?php
/**
 * AJAX Handler untuk Menjalankan Migrasi Database.
 *
 * Menerima permintaan POST, menjalankan migrasi yang tertunda, dan
 * mengembalikan hasilnya dalam format JSON.
 */
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';

$response = ['status' => 'error', 'message' => 'Permintaan tidak valid.'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Metode permintaan harus POST.");
    }

    // Optional: Tambahkan pengecekan keamanan di sini, misal: verifikasi token CSRF atau sesi admin
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        throw new Exception("Akses ditolak. Anda harus login sebagai admin.");
    }

    $pdo = get_db_connection();
    if (!$pdo) {
        throw new Exception("Koneksi database gagal.");
    }

    // Logika migrasi yang dipindahkan dari database.php
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
        $response = ['status' => 'success', 'message' => "Database sudah paling baru. Tidak ada migrasi yang perlu dijalankan."];
    } else {
        $results = [];
        foreach ($migrations_to_run as $migration_file) {
            $file_path = $migration_files_path . $migration_file;
            $extension = pathinfo($file_path, PATHINFO_EXTENSION);

            // Mulai transaksi per file untuk keamanan
            $pdo->beginTransaction();
            try {
                if ($extension === 'sql') {
                    $sql = file_get_contents($file_path);
                    $pdo->exec($sql);
                } elseif ($extension === 'php') {
                    require_once $file_path;
                    $function_name = 'run_migration_' . str_pad((int) filter_var($migration_file, FILTER_SANITIZE_NUMBER_INT), 3, '0', STR_PAD_LEFT);
                    if (function_exists($function_name)) {
                        $function_name($pdo);
                    } else {
                        throw new Exception("Fungsi migrasi '{$function_name}' tidak ditemukan.");
                    }
                }

                $stmt = $pdo->prepare("INSERT INTO migrations (migration_file) VALUES (?)");
                $stmt->execute([$migration_file]);
                $pdo->commit();
                $results[] = "OK: " . $migration_file;

            } catch (Exception $e) {
                $pdo->rollBack();
                $results[] = "ERROR: " . $migration_file . " - " . $e->getMessage();
                throw new Exception("Gagal pada migrasi: " . $migration_file . "\n" . $e->getMessage());
            }
        }
        $response = ['status' => 'success', 'message' => "Migrasi berhasil dijalankan:\n" . implode("\n", $results)];
    }
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
    // Log error jika perlu
    // app_log("Migration AJAX Error: " . $e->getMessage(), 'error');
}

echo json_encode($response);
exit;
