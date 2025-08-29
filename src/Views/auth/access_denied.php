<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akses Ditolak</title>
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
            text-align: center;
        }
        .container {
            background: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            max-width: 450px;
            width: 90%;
        }
        h1 {
            color: #e74c3c;
            margin-bottom: 20px;
        }
        p {
            color: #7f8c8d;
            margin-bottom: 30px;
            font-size: 1.1em;
        }
        .telegram-button {
            display: inline-block;
            padding: 15px 25px;
            border-radius: 5px;
            background-color: #0088cc;
            color: #fff;
            text-decoration: none;
            font-weight: bold;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }
        .telegram-button:hover {
            background-color: #0077b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Akses Ditolak</h1>
        <p>Sesi Anda telah berakhir atau Anda tidak memiliki hak akses untuk halaman ini.</p>
        <p>Silakan login kembali melalui bot Telegram dengan menekan tombol di bawah ini dan kirim perintah <code>/login</code>.</p>
        <?php if (!empty($bot_username)): ?>
            <a href="https://t.me/<?php echo htmlspecialchars($bot_username); ?>" target="_blank" class="telegram-button">Login via Telegram</a>
        <?php else: ?>
            <p><strong>Login bot tidak tersedia saat ini.</strong></p>
        <?php endif; ?>
    </div>
</body>
</html>
