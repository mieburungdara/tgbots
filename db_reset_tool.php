<?php
// WARNING: This script is highly destructive and will wipe your entire database.
// Use with extreme caution. It is recommended to delete this file after use.

session_start();

// --- Konfigurasi ---
$correct_password = 'sup3r4dmin';
$sql_schema_file = 'updated_schema.sql';
$message = '';
$error = '';

// --- Logika Proses ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['password']) && $_POST['password'] === $correct_password) {
        $_SESSION['is_authenticated'] = true;

        if (isset($_POST['confirm_reset'])) {
            try {
                // 1. Sertakan file database untuk mendapatkan koneksi PDO
                require_once __DIR__ . '/core/database.php';
                $pdo = get_db_connection();

                if (!$pdo) {
                    throw new Exception("Gagal terhubung ke database.");
                }

                $message .= "Berhasil terhubung ke database.<br>";

                // 2. Nonaktifkan foreign key checks
                $pdo->exec('SET FOREIGN_KEY_CHECKS=0;');
                $message .= "Pemeriksaan foreign key dinonaktifkan.<br>";

                // 3. Dapatkan semua nama tabel
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

                // 4. Hapus semua tabel
                if (empty($tables)) {
                    $message .= "Tidak ada tabel untuk dihapus.<br>";
                } else {
                    foreach ($tables as $table) {
                        $pdo->exec("DROP TABLE IF EXISTS `$table`");
                        $message .= "Tabel `$table` berhasil dihapus.<br>";
                    }
                     $message .= "<b>Semua tabel berhasil dihapus.</b><br>";
                }

                // 5. Baca dan jalankan skema SQL baru
                if (!file_exists($sql_schema_file)) {
                    throw new Exception("File skema '$sql_schema_file' tidak ditemukan.");
                }
                $sql_script = file_get_contents($sql_schema_file);
                if ($sql_script === false) {
                    throw new Exception("Gagal membaca file skema '$sql_schema_file'.");
                }

                $pdo->exec($sql_script);
                $message .= "<b>Skema database berhasil dibuat ulang dari '$sql_schema_file'.</b><br>";

                // 6. Aktifkan kembali foreign key checks
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1;');
                $message .= "Pemeriksaan foreign key diaktifkan kembali.<br>";

                $message .= "<br><b style='color: green;'>PROSES RESET DATABASE SELESAI.</b>";

            } catch (Exception $e) {
                $error = "Terjadi kesalahan: " . $e->getMessage();
            }
        } else {
            $message = "Password benar. Silakan konfirmasi untuk melanjutkan.";
        }
    } else {
        $error = "Password salah!";
        unset($_SESSION['is_authenticated']);
    }
}

$is_authenticated = isset($_SESSION['is_authenticated']) && $_SESSION['is_authenticated'];

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alat Reset Database</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 20px auto; padding: 0 20px; background-color: #f4f4f4; }
        .container { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1, h2 { color: #d9534f; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .warning { background-color: #fcf8e3; border: 1px solid #faebcc; color: #8a6d3b; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .message { background-color: #dff0d8; border: 1px solid #d6e9c6; color: #3c763d; padding: 15px; border-radius: 4px; margin-top: 20px; white-space: pre-wrap; word-wrap: break-word; }
        .error { background-color: #f2dede; border: 1px solid #ebccd1; color: #a94442; padding: 15px; border-radius: 4px; margin-top: 20px; }
        form { margin-top: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; }
        input[type="password"], input[type="submit"] { width: 100%; padding: 10px; margin-bottom: 15px; border-radius: 4px; border: 1px solid #ccc; box-sizing: border-box; }
        input[type="submit"] { background-color: #d9534f; color: white; font-size: 16px; border: none; cursor: pointer; transition: background-color 0.3s; }
        input[type="submit"]:hover { background-color: #c9302c; }
        .logout-form { text-align: right; }
        .logout-form input[type="submit"] { width: auto; background-color: #5bc0de; }
        .logout-form input[type="submit"]:hover { background-color: #31b0d5; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Alat Reset Database</h1>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!$is_authenticated): ?>
            <div class="warning">
                <strong>PERINGATAN:</strong> Tindakan ini akan MENGHAPUS SEMUA TABEL di database dan membuatnya kembali dari file skema. Semua data akan hilang secara permanen.
            </div>
            <form action="" method="post">
                <label for="password">Masukkan Password:</label>
                <input type="password" id="password" name="password" required>
                <input type="submit" value="Otentikasi">
            </form>
        <?php else: ?>
            <h2>Otentikasi Berhasil</h2>
            <form action="" method="post" class="logout-form">
                <input type="hidden" name="logout" value="1">
                <input type="submit" value="Logout" onclick="<?php session_destroy(); ?>">
            </form>

            <?php if ($message): ?>
                <div class="message"><?php echo $message; // Already contains HTML, no need to escape ?></div>
            <?php endif; ?>

            <form action="" method="post" onsubmit="return confirm('APAKAH ANDA YAKIN INGIN MERESET DATABASE? SEMUA DATA AKAN HILANG!');">
                <input type="hidden" name="password" value="<?php echo htmlspecialchars($correct_password); ?>">
                <input type="hidden" name="confirm_reset" value="1">
                <input type="submit" value="HAPUS DAN RESET DATABASE SEKARANG">
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
