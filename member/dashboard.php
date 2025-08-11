<?php
session_start();

// Jika belum login, redirect ke halaman login
if (!isset($_SESSION['member_user_id'])) {
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/../core/database.php';

$pdo = get_db_connection();

// Ambil informasi pengguna dari tabel users
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['member_user_id']]);
$user_info = $stmt->fetch();

if (!$user_info) {
    // Jika data pengguna tidak ditemukan, hancurkan session dan redirect
    session_destroy();
    header("Location: index.php");
    exit;
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Member</title>
    <style>
        body { font-family: sans-serif; background-color: #f4f4f4; margin: 0; padding: 2rem; }
        .dashboard-container { max-width: 800px; margin: auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { border-bottom: 1px solid #ccc; padding-bottom: 0.5rem; }
        .info-grid { display: grid; grid-template-columns: 150px 1fr; gap: 1rem; }
        .info-grid strong { font-weight: bold; }
        .logout-link { display: inline-block; margin-top: 2rem; padding: 0.7rem 1.5rem; background-color: #dc3545; color: white; text-decoration: none; border-radius: 4px; }
        .logout-link:hover { background-color: #c82333; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <h1>Selamat Datang, <?= htmlspecialchars($user_info['first_name'] ?? '') ?>!</h1>

        <h2>Informasi Akun Anda</h2>
        <div class="info-grid">
            <strong>First Name:</strong>
            <span><?= htmlspecialchars($user_info['first_name'] ?? '') ?></span>

            <strong>Username:</strong>
            <span>@<?= htmlspecialchars($user_info['username'] ?? 'Tidak ada') ?></span>

            <strong>Telegram ID:</strong>
            <span><?= htmlspecialchars($user_info['telegram_id']) ?></span>

            <strong>Terdaftar pada:</strong>
            <span><?= htmlspecialchars(date('d F Y H:i', strtotime($user_info['created_at']))) ?></span>
        </div>

        <a href="?action=logout" class="logout-link">Logout</a>
    </div>
</body>
</html>
