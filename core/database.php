<?php

use TGBot\Logger;
use TGBot\App;

function get_db_connection() {
    $logger = App::getLogger();
    // Memerlukan file config yang akan dibuat oleh pengguna dari contoh.
    if (!file_exists(__DIR__ . '/../config.php')) {
        // Berhenti jika file konfigurasi tidak ditemukan.
        // Ini akan mencegah error lebih lanjut.
        $logger->error("Error: config.php tidak ditemukan. Harap salin dari config.php.example dan isi kredensialnya.");
        return null;
    }
    

    // DSN (Data Source Name) untuk koneksi PDO
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lemparkan exception jika ada error
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Hasil query sebagai associative array
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Gunakan native prepared statements
    ];

    try {
        // Buat instance PDO baru
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // Catat error ke log server jika koneksi gagal.
        // Jangan tampilkan detail error ke pengguna.
        $logger->critical("Koneksi database gagal: " . $e->getMessage());
        return null;
    }
}

/**
 * Menjalankan skrip setup database dari file setup.sql.
 *
 * @param PDO $pdo Objek koneksi PDO.
 * @return bool True jika berhasil, false jika gagal.
 */
function setup_database(PDO $pdo): bool {
    $logger = App::getLogger();
    $sql_file = __DIR__ . '/../setup.sql';
    if (!file_exists($sql_file)) {
        $logger->error("Error: file setup.sql tidak ditemukan.");
        return false;
    }

    try {
        $sql = file_get_contents($sql_file);
        $pdo->exec($sql);
        $logger->info("Setup database dari setup.sql berhasil dijalankan.");
        return true;
    } catch (PDOException $e) {
        $logger->error("Gagal menjalankan setup database: " . $e->getMessage());
        return false;
    }
}

/**
 * Memastikan tabel untuk melacak migrasi sudah ada di database.
 * Jika belum ada, tabel akan dibuat.
 *
 * @param PDO $pdo Objek koneksi PDO.
 * @return void
 */
function ensure_migrations_table_exists(PDO $pdo): void {
    $logger = App::getLogger();
    try {
        // Query untuk membuat tabel jika belum ada
        $sql = "
        CREATE TABLE IF NOT EXISTS `migrations` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `migration_file` varchar(255) NOT NULL,
          `executed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `migration_file` (`migration_file`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $pdo->exec($sql);
    } catch (PDOException $e) {
        // Jika terjadi error, catat dan hentikan eksekusi
        $logger->error("Gagal membuat tabel migrasi: " . $e->getMessage());
        die("Fatal Error: Tidak dapat membuat tabel migrasi. Periksa log server.");
    }
}

/**
 * Menghapus semua data transaksional dari database.
 *
 * @param PDO $pdo Objek koneksi PDO.
 * @return void
 * @throws Exception Jika terjadi error saat proses truncate.
 */
function clean_transactional_data(PDO $pdo): void {
    $logger = App::getLogger();
    $tables_to_truncate = [
        'sales',
        'media_files',
        'media_packages',
        'messages',
        'rel_user_bot',
        'bot_settings',
        'bot_channel_usage',
        'users' // 'users' terakhir karena tabel lain memiliki foreign key ke sini
    ];

    $pdo->beginTransaction();
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');
        foreach ($tables_to_truncate as $table) {
            $pdo->exec("TRUNCATE TABLE `{$table}`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');
        $pdo->commit();
        $logger->info("Semua data transaksional telah dibersihkan oleh admin.");
    } catch (Exception $e) {
        $pdo->rollBack();
        $logger->error("Gagal membersihkan data transaksional: " . $e->getMessage());
        throw $e; // Lemparkan kembali untuk ditangani oleh pemanggil
    }
}
