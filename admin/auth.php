<?php
/**
 * Penangan Autentikasi untuk Panel Admin.
 *
 * File ini melakukan dua hal utama:
 * 1. Memproses token login sekali pakai dari URL untuk membuat sesi admin.
 * 2. Memverifikasi sesi pada setiap pemuatan halaman untuk memastikan pengguna diautentikasi.
 *
 * Alur Kerja:
 * - session_start() dipanggil di awal.
 * - Jika `?token=` ada di URL:
 *   - Token divalidasi (ada, belum dipakai, tidak kedaluwarsa, milik admin).
 *   - Jika valid, token ditandai sebagai terpakai, sesi dibuat, dan pengguna di-redirect
 *     ke halaman yang sama tanpa token untuk membersihkan URL.
 *   - Jika tidak valid, sesi dihancurkan dan skrip dihentikan.
 * - Jika tidak ada token, file ini memeriksa apakah `$_SESSION['is_admin']` sudah ada dan valid.
 * - Jika sesi tidak valid, skrip dihentikan dengan pesan "Akses Ditolak".
 * - Jika sesi valid, skrip melanjutkan eksekusi halaman yang diminta.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/database.php';

// --- Bagian 1: Memproses Token Login dari URL ---
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $pdo = get_db_connection();

    if ($pdo) {
        $stmt = $pdo->prepare(
            "SELECT m.user_id, u.first_name
             FROM members m
             JOIN users u ON m.user_id = u.id
             JOIN user_roles ur ON u.id = ur.user_id
             JOIN roles r ON ur.role_id = r.id
             WHERE m.login_token = ? AND m.token_used = 0 AND m.token_created_at >= NOW() - INTERVAL 5 MINUTE AND r.name = 'Admin'"
        );
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            // Token valid: Buat sesi
            $pdo->prepare("UPDATE members SET token_used = 1 WHERE login_token = ?")->execute([$token]);

            session_regenerate_id(true); // Keamanan: cegah session fixation
            $_SESSION['is_admin'] = true;
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_first_name'] = $user['first_name'];

            // Redirect untuk membersihkan token dari URL
            header('Location: index.php');
            exit;
        }
    }

    // Jika token tidak valid atau ada masalah DB
    session_unset();
    session_destroy();
    die('Akses Ditolak: Tautan login tidak valid, kedaluwarsa, atau bukan untuk admin.');
}

// --- Bagian 2: Memverifikasi Sesi yang Ada pada Setiap Pemuatan Halaman ---
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    // Tampilkan halaman login sederhana jika tidak ada sesi
    die('Akses Ditolak. Silakan login melalui bot Telegram menggunakan perintah /login.');
}

// Jika lolos dari semua pemeriksaan, pengguna diautentikasi.
// Variabel sesi seperti $_SESSION['user_id'] tersedia untuk skrip lainnya.
