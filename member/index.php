<?php
session_start();

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['member_user_id'])) {
    header("Location: dashboard.php");
    exit;
}

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php'; // Pastikan helpers di-include untuk app_log

$error_message = '';
$token_from_url = isset($_GET['token']) ? trim($_GET['token']) : '';

function process_login_token($token, $pdo) {
    if (empty($token)) {
        app_log("Upaya login gagal: Token tidak diberikan.", 'member');
        return "Silakan masukkan token Anda.";
    }

    $stmt = $pdo->prepare("SELECT * FROM members WHERE login_token = ? AND token_used = 0");
    $stmt->execute([$token]);
    $member = $stmt->fetch();

    if ($member) {
        // Tandai token sebagai sudah digunakan
        $stmt = $pdo->prepare("UPDATE members SET token_used = 1, login_token = NULL WHERE id = ?");
        $stmt->execute([$member['id']]);

        // Simpan informasi member di session menggunakan user_id
        $_SESSION['member_user_id'] = $member['user_id'];

        app_log("Login member berhasil: user_id = {$member['user_id']}", 'member');

        // Redirect ke dashboard
        header("Location: dashboard.php");
        exit;
    } else {
        app_log("Upaya login gagal: Token tidak valid atau sudah digunakan. Token: {$token}", 'member');
        return "Token tidak valid, sudah digunakan, atau kedaluwarsa.";
    }
}

// Coba login otomatis jika token ada di URL
if (!empty($token_from_url)) {
    $error_message = process_login_token($token_from_url, get_db_connection());
}

// Handle login dari form manual
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token_from_post = isset($_POST['token']) ? trim($_POST['token']) : '';
    $error_message = process_login_token($token_from_post, get_db_connection());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Member</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f4f4f4; }
        .login-container { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        h1 { text-align: center; }
        form { display: flex; flex-direction: column; margin-top: 1rem;}
        label { margin-bottom: 0.5rem; }
        input[type="text"] { padding: 0.5rem; margin-bottom: 1rem; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 0.7rem; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        .error { color: red; margin-top: 1rem; }
        .info { color: #555; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Login Panel Member</h1>

        <?php if (!empty($token_from_url) && $error_message): ?>
            <p class="info">Login otomatis gagal. Silakan coba masukkan token secara manual.</p>
        <?php else: ?>
            <p class="info">Masukkan token yang Anda terima dari bot.</p>
        <?php endif; ?>

        <form action="index.php" method="POST">
            <label for="token">Token Login</label>
            <input type="text" id="token" name="token" value="<?= htmlspecialchars($token_from_url); ?>" required>
            <button type="submit">Login</button>
        </form>

        <?php if ($error_message): ?>
            <p class="error"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
