<?php

/**
 * Membuat koneksi ke database menggunakan PDO.
 * Fungsi ini akan membaca konfigurasi dari file config.php.
 *
 * @return PDO|null Objek PDO jika koneksi berhasil, null jika gagal.
 */
function get_db_connection() {
    // Memerlukan file config yang akan dibuat oleh pengguna dari contoh.
    if (!file_exists(__DIR__ . '/../config.php')) {
        // Berhenti jika file konfigurasi tidak ditemukan.
        // Ini akan mencegah error lebih lanjut.
        error_log("Error: config.php tidak ditemukan. Harap salin dari config.php.example dan isi kredensialnya.");
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
        error_log("Koneksi database gagal: " . $e->getMessage());
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
        error_log("Error: file setup.sql tidak ditemukan.");
        return false;
    }

    try {
        $sql = file_get_contents($sql_file);
        $pdo->exec($sql);
        return true;
    } catch (PDOException $e) {
        error_log("Gagal menjalankan setup database: " . $e->getMessage());
        return false;
    }
}
