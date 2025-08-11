<?php

/**
 * Memeriksa apakah tabel-tabel dasar yang diperlukan oleh aplikasi sudah ada.
 * Fungsi ini memeriksa keberadaan tabel 'bots' sebagai indikator.
 *
 * @param PDO $pdo Objek koneksi PDO.
 * @return bool True jika tabel ada, false jika tidak.
 */
function check_tables_exist(PDO $pdo) {
    try {
        // Coba jalankan query sederhana ke tabel 'bots'.
        // Jika gagal dengan exception (khususnya kode untuk tabel tidak ditemukan),
        // berarti tabelnya belum dibuat.
        $pdo->query("SELECT 1 FROM `bots` LIMIT 1");
    } catch (PDOException $e) {
        // Kode '42S02' adalah standar SQLSTATE untuk "base table or view not found".
        if ($e->getCode() === '42S02') {
            return false;
        }
        // Lemparkan kembali exception lain jika bukan error "tabel tidak ditemukan".
        throw $e;
    }
    return true;
}

/**
 * Mencatat pesan ke file log yang ditentukan.
 *
 * @param string $message Pesan yang akan dicatat.
 * @param string $level Tipe log (misal: 'database', 'bot', 'error'). Ini akan menjadi nama file log.
 * @return void
 */
function app_log(string $message, string $level = 'app'): void {
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    $log_file = $log_dir . '/' . $level . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $formatted_message = "[{$timestamp}] " . $message . PHP_EOL;

    // Gunakan FILE_APPEND untuk menambahkan ke file, dan LOCK_EX untuk mencegah penulisan bersamaan.
    file_put_contents($log_file, $formatted_message, FILE_APPEND | LOCK_EX);
}
