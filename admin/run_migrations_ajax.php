<?php
/**
 * AJAX Handler untuk Menjalankan Migrasi Database.
 *
 * Menerima permintaan POST, menjalankan migrasi yang tertunda, dan
 * mengembalikan hasilnya dalam format JSON.
 * Direvisi untuk menangani output mentah dari skrip migrasi dengan andal.
 */
header('Content-Type: application/json');
session_start();

// Buat respons default
$response = ['status' => 'error', 'message' => 'Terjadi kesalahan yang tidak diketahui.'];

// Gunakan blok try/catch tingkat atas untuk menangkap semuanya, termasuk error parsing
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
        $response = ['status' => 'success', 'message' => "Database sudah paling baru. Tidak ada migrasi yang perlu dijalankan."];
    } else {
        $results = [];
        foreach ($migrations_to_run as $migration_file) {
            $file_path = $migration_files_path . $migration_file;
            $extension = pathinfo($file_path, PATHINFO_EXTENSION);

            $pdo->beginTransaction();
            ob_start(); // Mulai output buffering di sini, sebelum try/catch
            try {
                $migration_output = '';
                if ($extension === 'sql') {
                    $sql = file_get_contents($file_path);
                    $pdo->exec($sql);
                } elseif ($extension === 'php') {
                    // Cukup require file. Kode top-level akan dieksekusi.
                    // Fungsi di dalamnya juga akan didefinisikan.
                    require $file_path;

                    // Cek apakah ada fungsi migrasi konvensional untuk dijalankan
                    // Ini untuk kompatibilitas mundur dengan migrasi berbasis fungsi.
                    $function_name = 'run_migration_' . str_pad((int) filter_var($migration_file, FILTER_SANITIZE_NUMBER_INT), 3, '0', STR_PAD_LEFT);
                    if (function_exists($function_name)) {
                        $function_name($pdo);
                    }
                }

                // Tangkap semua output dari buffer
                $migration_output = ob_get_clean();

                // Catat migrasi yang berhasil
                $stmt = $pdo->prepare("INSERT INTO migrations (migration_file) VALUES (?)");
                $stmt->execute([$migration_file]);
                $pdo->commit();

                $result_message = "OK: " . $migration_file;
                if (!empty(trim($migration_output))) {
                    $result_message .= "\n--- Output ---\n" . trim($migration_output);
                }
                $results[] = $result_message;

            } catch (Throwable $e) { // Tangkap Throwable untuk semua jenis error
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                // Pastikan buffer dibersihkan bahkan saat error
                $error_output = ob_get_clean();
                $results[] = "ERROR: " . $migration_file . " - " . $e->getMessage() . "\n" . $error_output;
                throw new Exception("Gagal pada migrasi: " . $migration_file . ". Lihat log di atas untuk detail.");
            }
        }
        $response = ['status' => 'success', 'message' => "Proses migrasi selesai:\n" . implode("\n\n", $results)];
    }
} catch (Throwable $e) { // Tangkap Throwable di tingkat atas juga
    $response['status'] = 'error';
    $response['message'] = "Error Kritis: " . $e->getMessage();
}

echo json_encode($response);
exit;
