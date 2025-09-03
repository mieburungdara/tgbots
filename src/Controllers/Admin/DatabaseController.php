<?php

namespace TGBot\Controllers\Admin;



use Exception;
use PDO;
use Throwable;
use TGBot\Controllers\BaseController;

class DatabaseController extends BaseController
{
    /**
     * Menampilkan halaman manajemen database.
     */
    public function index()
    {
        try {
            $message = $_SESSION['flash_message'] ?? null;
            unset($_SESSION['flash_message']);

            $this->view('admin/database/index', [
                'page_title' => 'Manajemen Database',
                'message' => $message
            ], 'admin_layout');
        } catch (Exception $e) {
            \app_log('Error in DatabaseController/index: ' . $e->getMessage(), 'error');
            $this->view('admin/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading the database management page.'
            ], 'admin_layout');
        }
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

        $pdo = \get_db_connection();
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

            $pdo = \get_db_connection();
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

    public function checkSchema()
    {
        try {
            $pdo = \get_db_connection();
            $sql_file_path = BASE_PATH . '/updated_schema.sql';
            if (!file_exists($sql_file_path)) {
                throw new Exception("File skema `updated_schema.sql` tidak ditemukan.");
            }
            $sql_content = file_get_contents($sql_file_path);

            $file_schema = $this->parseSchemaFromFile($sql_content);
            $live_schema = $this->getLiveSchema($pdo);
            $report = $this->compareSchemas($file_schema, $live_schema);

            $this->view('admin/database/check', [
                'page_title' => 'Pemeriksa Skema Database',
                'report' => $report
            ], 'admin_layout');

        } catch (Exception $e) {
            $this->view('admin/database/check', [
                'page_title' => 'Pemeriksa Skema Database',
                'error' => 'Gagal memeriksa skema: ' . $e->getMessage(),
                'report' => []
            ], 'admin_layout');
        }
    }

    private function parseSchemaFromFile(string $sql_content): array
    {
        $schema = [];
        preg_match_all('/CREATE TABLE(?: IF NOT EXISTS)? `?(\w+)`?.*?\((.*?)\)\s*ENGINE=/s', $sql_content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $table_name = $match[1];
            $full_query = '';
            // Find the full CREATE TABLE statement for this table
            if (preg_match('/(CREATE TABLE(?: IF NOT EXISTS)? `?'.$table_name.'`?.*?);/s', $sql_content, $full_match)) {
                $full_query = $full_match[1];
            }

            $schema[$table_name] = [
                'columns' => [],
                'full_query' => $full_query
            ];

            $lines = explode("\n", $match[2]);
            foreach ($lines as $line) {
                $line = trim($line);
                if (preg_match('/^`?(\w+)`? /', $line, $column_match)) {
                    $column_name = $column_match[1];
                    // Store the full line definition for generating ALTER queries
                    $schema[$table_name]['columns'][$column_name] = rtrim($line, ',');
                }
            }
        }
        return $schema;
    }

    private function getLiveSchema(PDO $pdo): array
    {
        $schema = [];
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $schema[$table] = ['columns' => []];
            $columns = $pdo->query("DESCRIBE `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
            $schema[$table]['columns'] = array_fill_keys($columns, true);
        }
        return $schema;
    }

    private function compareSchemas(array $file_schema, array $live_schema): array
    {
        $report = [
            'missing_tables' => [],
            'missing_columns' => [],
        ];

        foreach ($file_schema as $table_name => $table_data) {
            if (!isset($live_schema[$table_name])) {
                $report['missing_tables'][] = [
                    'name' => $table_name,
                    'query' => $table_data['full_query']
                ];
            } else {
                foreach ($table_data['columns'] as $column_name => $column_definition) {
                    if (!isset($live_schema[$table_name]['columns'][$column_name])) {
                        $report['missing_columns'][$table_name][] = [
                            'name' => $column_name,
                            'query' => "ALTER TABLE `{$table_name}` ADD COLUMN {$column_definition};"
                        ];
                    }
                }
            }
        }
        return $report;
    }
}
