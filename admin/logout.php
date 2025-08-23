<?php
// Mulai sesi untuk mengakses variabel sesi.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Hapus semua variabel sesi.
$_SESSION = [];

// Hancurkan sesi.
session_destroy();

// Redirect ke halaman login (atau halaman utama admin yang akan menampilkan pesan akses ditolak).
header('Location: index.php');
exit;
