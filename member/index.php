<?php
session_start();

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['member_chat_id'])) {
    header("Location: dashboard.php");
    exit;
}

require_once __DIR__ . '/../core/database.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['token']) && !empty($_POST['token'])) {
        $token = $_POST['token'];

        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT * FROM members WHERE login_token = ? AND token_used = 0");
        $stmt->execute([$token]);
        $member = $stmt->fetch();

        if ($member) {
            // Tandai token sebagai sudah digunakan
            $stmt = $pdo->prepare("UPDATE members SET token_used = 1 WHERE id = ?");
            $stmt->execute([$member['id']]);

            // Simpan informasi member di session
            $_SESSION['member_chat_id'] = $member['chat_id'];

            // Redirect ke dashboard
            header("Location: dashboard.php");
            exit;
        } else {
            $error_message = "Token tidak valid atau sudah digunakan.";
        }
    } else {
        $error_message = "Silakan masukkan token Anda.";
    }
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
        .login-container { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { text-align: center; }
        form { display: flex; flex-direction: column; }
        label { margin-bottom: 0.5rem; }
        input[type="text"] { padding: 0.5rem; margin-bottom: 1rem; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 0.7rem; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        .error { color: red; text-align: center; margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Login Panel Member</h1>
        <form action="index.php" method="POST">
            <label for="token">Token Login</label>
            <input type="text" id="token" name="token" required>
            <button type="submit">Login</button>
        </form>
        <?php if ($error_message): ?>
            <p class="error"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
