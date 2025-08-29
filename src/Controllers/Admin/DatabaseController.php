<?php

require_once __DIR__ . '/../BaseController.php';

class DatabaseController extends BaseController
{
    /**
     * Menampilkan halaman manajemen database.
     */
    public function index()
    {
        $message = $_SESSION['flash_message'] ?? null;
        unset($_SESSION['flash_message']);

        $this->view('admin/database/index', [
            'page_title' => 'Manajemen Database',
            'message' => $message
        ], 'admin_layout');
    }

    /**
     * Menangani permintaan untuk me-reset database dari file SQL.
     */
    public function reset()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['sql_file'])) {
            header('Location: /admin/database');
            exit();
        }

        $pdo = get_db_connection();
        $allowed_files = ['updated_schema.sql', 'setup.sql'];
        $selected_file = $_POST['sql_file'] ?? '';

        if (in_array($selected_file, $allowed_files) && file_exists(BASE_PATH . '/' . $selected_file)) {
            try {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=0;');
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($tables as $table) {
                    $pdo->exec("DROP TABLE IF EXISTS `$table`");
                }

                $sql_script = file_get_contents(BASE_PATH . '/' . $selected_file);
                $pdo->exec($sql_script);

                $pdo->exec('SET FOREIGN_KEY_CHECKS=1;');
                $_SESSION['flash_message'] = "Database berhasil di-reset menggunakan file '{$selected_file}'.";
            } catch (Exception $e) {
                $_SESSION['flash_message'] = "Gagal me-reset database: " . $e->getMessage();
            }
        } else {
            $_SESSION['flash_message'] = "Error: File SQL tidak valid atau tidak ditemukan.";
        }

        header("Location: /admin/database");
        exit;
    }

    /**
     * Menangani permintaan AJAX untuk menjalankan migrasi database.
     */
    public function migrate()
    {
        header('Content-Type: text/plain; charset=utf-8');

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Metode permintaan harus POST.");
            }

            $pdo = get_db_connection();
            if (!$pdo) {
                throw new Exception("Koneksi database gagal.");
            }

            ensure_migrations_table_exists($pdo);
            $executed_migrations = $pdo->query("SELECT migration_file FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
            $migration_files_path = BASE_PATH . '/migrations/';
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
                        throw new Exception("Gagal pada migrasi: {$migration_file}. Pesan Error: " . $e->getMessage(), 0, $e);
                    }
                }
                echo "Semua migrasi berhasil dijalankan.";
            }
        } catch (Throwable $e) {
            if (http_response_code() < 400) {
                http_response_code(500);
            }
            echo "Error Kritis: " . $e->getMessage() . "\n\nStack Trace:\n" . $e->getTraceAsString();
        }
        exit;
    }
}
