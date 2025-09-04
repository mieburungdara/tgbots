<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($data['page_title'] ?? 'Admin Login') ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; color: #333; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background-color: #f4f4f4; }
        .login-container { background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h1 { text-align: center; margin-bottom: 30px; }
        .error { background-color: #f2dede; border: 1px solid #ebccd1; color: #a94442; padding: 15px; border-radius: 4px; margin-bottom: 20px; text-align: center; }
        input[type="password"], input[type="submit"] { width: 100%; padding: 12px; margin-bottom: 20px; border-radius: 4px; border: 1px solid #ccc; box-sizing: border-box; font-size: 16px; }
        input[type="submit"] { background-color: #337ab7; color: white; border: none; cursor: pointer; transition: background-color 0.3s; }
        input[type="submit"]:hover { background-color: #286090; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Admin Panel Login</h1>
        <?php if (!empty($data['error'])): ?>
            <div class="error"><?= htmlspecialchars($data['error']) ?></div>
        <?php endif; ?>
        <form action="/xoradmin/login" method="post">
            <input type="password" name="password" placeholder="Password" required>
            <input type="submit" value="Login">
        </form>
    </div>
</body>
</html>
