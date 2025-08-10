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
