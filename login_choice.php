<?php
/**
 * Halaman Pemilihan Login untuk Admin.
 *
 * Halaman ini memvalidasi token login yang diberikan dan, jika valid,
 * memberikan admin pilihan untuk masuk ke Panel Admin atau Panel Member.
 */

require_once __DIR__ . '/core/database.php';
require_once __DIR__ . '/core/helpers.php';

$token = $_GET['token'] ?? null;
$error_message = '';
$is_token_valid = false;

if (!$token) {
    $error_message = 'Token login tidak ditemukan.';
} else {
    $pdo = get_db_connection();
    if (!$pdo) {
        $error_message = 'Gagal terhubung ke database.';
    } else {
        // Cari token dan pastikan belum digunakan dan tidak kedaluwarsa (misal: dalam 5 menit terakhir)
        $stmt = $pdo->prepare(
            "SELECT id FROM users WHERE login_token = ? AND token_used = 0 AND token_created_at >= NOW() - INTERVAL 5 MINUTE"
        );
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            // Sebelum menampilkan link, pastikan pengguna ini memang admin
            $stmt_check_admin = $pdo->prepare(
                "SELECT 1 FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ? AND r.name = 'Admin'"
            );
            $stmt_check_admin->execute([$user['id']]);
            if ($stmt_check_admin->fetch()) {
                $is_token_valid = true;
            } else {
                $error_message = 'Akses ditolak. Pengguna ini bukan admin.';
            }
        } else {
            $error_message = 'Tautan login tidak valid atau telah kedaluwarsa.';
        }
    }
}

// --- Tampilan HTML ---
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Panel Login</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f4f7f9;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            background: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
        }
        p {
            color: #7f8c8d;
            margin-bottom: 30px;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 15px;
            margin-bottom: 15px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            color: #fff;
            cursor: pointer;
            transition: background-color 0.3s ease;
            box-sizing: border-box;
        }
        .btn-admin {
            background-color: #3498db;
        }
        .btn-admin:hover {
            background-color: #2980b9;
        }
        .btn-member {
            background-color: #2ecc71;
        }
        .btn-member:hover {
            background-color: #27ae60;
        }
        .error {
            color: #e74c3c;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($is_token_valid): ?>
            <h1>Pilih Panel</h1>
            <p>Anda memiliki akses admin. Silakan pilih panel yang ingin Anda masuki.</p>
            <a href="admin/index.php?token=<?= htmlspecialchars($token) ?>" class="btn btn-admin">Masuk sebagai Admin</a>
            <a href="member/index.php?token=<?= htmlspecialchars($token) ?>" class="btn btn-member">Masuk sebagai Member</a>
        <?php else: ?>
            <h1 class="error">Akses Ditolak</h1>
            <p class="error"><?= htmlspecialchars($error_message) ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
