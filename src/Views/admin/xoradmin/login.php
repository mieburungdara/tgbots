<?php
// This view assumes $error is passed from the controller.
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XOR Admin Panel - Login</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; color: #333; max-width: 960px; margin: 20px auto; padding: 0 20px; background-color: #f4f4f4; }
        .container { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .warning { background-color: #fcf8e3; border: 1px solid #faebcc; color: #8a6d3b; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .error { background-color: #f2dede; border: 1px solid #ebccd1; color: #a94442; padding: 15px; border-radius: 4px; margin-top: 20px; }
        form { margin-top: 20px; }
        input[type="password"], input[type="submit"] { width: 100%; padding: 10px; margin-bottom: 15px; border-radius: 4px; border: 1px solid #ccc; box-sizing: border-box; }
        input[type="submit"] { background-color: #337ab7; color: white; font-size: 16px; border: none; cursor: pointer; transition: background-color 0.3s; }
        input[type="submit"]:hover { background-color: #286090; }
    </style>
</head>
<body>
    <div class="container">
        <h1>XOR Admin Panel</h1>

        <?php if (!empty($_SESSION['xor_error'])): ?>
            <div class="error"><?= htmlspecialchars($_SESSION['xor_error']) ?><?php unset($_SESSION['xor_error']); ?></div>
        <?php endif; ?>

        <div class="warning"><strong>Peringatan:</strong> Diperlukan otentikasi untuk melanjutkan.</div>
        <form action="/xoradmin/login" method="post">
            <label for="password">Masukkan Password:</label>
            <input type="password" id="password" name="password" required>
            <input type="submit" name="login" value="Otentikasi">
        </form>
    </div>
</body>
</html>
