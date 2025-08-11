<?php

/**
 * Membuat koneksi ke database menggunakan PDO.
 * Fungsi ini akan membaca konfigurasi dari file config.php.
 *
 * @return PDO|null Objek PDO jika koneksi berhasil, null jika gagal.
 */
require_once __DIR__ . '/helpers.php';

function get_db_connection() {
    // Memerlukan file config yang akan dibuat oleh pengguna dari contoh.
    if (!file_exists(__DIR__ . '/../config.php')) {
        // Berhenti jika file konfigurasi tidak ditemukan.
        // Ini akan mencegah error lebih lanjut.
        app_log("Error: config.php tidak ditemukan. Harap salin dari config.php.example dan isi kredensialnya.", 'error');
        return null;
    }
    require_once __DIR__ . '/../config.php';

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
        app_log("Koneksi database gagal: " . $e->getMessage(), 'database');
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
    $sql_file = __DIR__ . '/../setup.sql';
    if (!file_exists($sql_file)) {
        app_log("Error: file setup.sql tidak ditemukan.", 'error');
        return false;
    }

    try {
        $sql = file_get_contents($sql_file);
        $pdo->exec($sql);
        app_log("Setup database dari setup.sql berhasil dijalankan.", 'database');
        return true;
    } catch (PDOException $e) {
        app_log("Gagal menjalankan setup database: " . $e->getMessage(), 'database');
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
        app_log("Gagal membuat tabel migrasi: " . $e->getMessage(), 'database');
        die("Fatal Error: Tidak dapat membuat tabel migrasi. Periksa log server.");
    }
}
