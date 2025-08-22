<?php
/**
 * Handler Backend untuk Pengaturan Bot (Admin).
 *
 * File ini secara eksklusif menangani permintaan POST dari formulir pengaturan
 * di halaman `edit_bot.php`. Ini tidak menampilkan HTML apa pun dan hanya
 * berfungsi sebagai prosesor data.
 *
 * Logika:
 * 1. Validasi permintaan (harus POST, ID bot valid).
 * 2. Menggunakan daftar `allowed_keys` untuk keamanan, mencegah injeksi kunci tak dikenal.
 * 3. Loop melalui setiap kunci yang diizinkan dan menentukan nilainya (1 atau 0).
 * 4. Menggunakan query "UPSERT" (INSERT ... ON DUPLICATE KEY UPDATE) untuk
 *    membuat atau memperbarui pengaturan di database secara efisien.
 * 5. Semua operasi dibungkus dalam transaksi database untuk memastikan integritas data.
 * 6. Mengatur pesan status di session dan mengalihkan kembali ke halaman `edit_bot.php`.
 */
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';

// Hanya izinkan metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bots.php');
    exit;
}

// Validasi input
$bot_id = filter_input(INPUT_POST, 'bot_id', FILTER_VALIDATE_INT);
$telegram_bot_id = filter_input(INPUT_POST, 'telegram_bot_id', FILTER_VALIDATE_INT);
$settings_from_form = $_POST['settings'] ?? [];

if (!$bot_id || !$telegram_bot_id) {
    // Jika ID tidak valid, kembali ke daftar bot utama
    $_SESSION['status_message'] = 'Error: ID Bot tidak valid.';
    header('Location: bots.php');
    exit;
}

// Redirect kembali ke halaman edit bot jika terjadi error
$redirect_url = "edit_bot.php?id=" . $telegram_bot_id;

$pdo = get_db_connection();
if (!$pdo) {
    $_SESSION['status_message'] = 'Error: Koneksi database gagal.';
    header("Location: " . $redirect_url);
    exit;
}

// Daftar kunci pengaturan yang diizinkan untuk keamanan.
// Ini mencegah penyisipan kunci sembarangan ke database.
$allowed_keys = [
    'save_text_messages',
    'save_media_messages',
    'save_callback_queries',
    'save_edited_messages'
];

try {
    $pdo->beginTransaction();

    // Loop melalui semua kunci yang diizinkan
    foreach ($allowed_keys as $key) {
        // Jika checkbox dicentang di form, nilainya adalah '1'.
        // Jika tidak dicentang, nilainya menjadi '0'.
        $value = isset($settings_from_form[$key]) && $settings_from_form[$key] === '1' ? '1' : '0';

        // Gunakan INSERT ... ON DUPLICATE KEY UPDATE (UPSERT)
        // Ini akan membuat baris baru jika belum ada, atau memperbarui yang sudah ada.
        $sql = "INSERT INTO bot_settings (bot_id, setting_key, setting_value)
                VALUES (:bot_id, :setting_key, :setting_value)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':bot_id' => $bot_id,
            ':setting_key' => $key,
            ':setting_value' => $value
        ]);
    }

    $pdo->commit();
    $_SESSION['status_message'] = 'Pengaturan berhasil disimpan.';

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_log("Gagal menyimpan pengaturan bot (ID: {$bot_id}): " . $e->getMessage(), 'database');
    $_SESSION['status_message'] = 'Terjadi error saat menyimpan pengaturan. Silakan coba lagi.';
}

// Redirect kembali ke halaman edit dengan pesan status
header("Location: " . $redirect_url);
exit;
