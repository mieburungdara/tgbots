<?php
// This view assumes the following variables are available in the $data array:
// 'page_title', 'error_message', 'token_from_url'
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($data['page_title'] ?? 'Login Member') ?></title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f4f4f4; margin: 0; }
        .login-container { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; width: 90%; max-width: 400px;}
        h1 { text-align: center; margin-top: 0; }
        form { display: flex; flex-direction: column; margin-top: 1rem;}
        label { margin-bottom: 0.5rem; text-align: left; }
        input[type="text"] { padding: 0.7rem; margin-bottom: 1rem; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 0.7rem; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button:hover { background-color: #0056b3; }
        .error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 1rem; margin-top: 1rem; border-radius: 4px; }
        .info { color: #555; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Login Panel Member</h1>

        <p class="info">Masukkan token yang Anda terima dari bot, atau klik tautan login dari bot.</p>

        <form action="/member/login" method="POST">
            <label for="token">Token Login</label>
            <input type="text" id="token" name="token" value="<?= htmlspecialchars($data['token_from_url']); ?>" required>
            <button type="submit">Login</button>
        </form>

        <?php if (isset($data['error_message'])): ?>
            <p class="error"><?= htmlspecialchars($data['error_message']) ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
