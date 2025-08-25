<?php
/**
 * Halaman Login Panel Anggota.
 *
 * Halaman ini berfungsi sebagai gerbang masuk ke area anggota.
 * Login dilakukan menggunakan token sekali pakai yang didapatkan pengguna
 * dari bot melalui perintah `/login`.
 *
 * Logika:
 * - Jika pengguna sudah login, langsung alihkan ke `dashboard.php`.
 * - Jika token ada di parameter URL, coba login otomatis.
 * - Jika tidak, tampilkan formulir untuk memasukkan token secara manual.
 * - Setelah login berhasil, token ditandai sebagai sudah digunakan (`token_used = 1`)
 *   dan ID pengguna disimpan di session.
 */
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

/**
 * Memproses token login yang diberikan.
 *
 * @param string $token Token yang akan divalidasi.
 * @param PDO $pdo Objek koneksi database.
 * @return string|void Mengembalikan pesan error jika gagal, atau mengalihkan jika berhasil.
 */
function process_login_token($token, $pdo) {
    if (empty($token)) {
        app_log("Upaya login gagal: Token tidak diberikan.", 'member');
        return "Silakan masukkan token Anda.";
    }

    // Cari token yang valid dan belum digunakan
    $stmt = $pdo->prepare("SELECT * FROM members WHERE login_token = ? AND token_used = 0");
    $stmt->execute([$token]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    // Cek apakah token kedaluwarsa (lebih dari 5 menit)
    if ($member && isset($member['token_created_at']) && strtotime($member['token_created_at']) < time() - (5 * 60)) {
        app_log("Upaya login gagal: Token kedaluwarsa. Token: {$token}", 'member');
        return "Token yang Anda gunakan sudah kedaluwarsa. Silakan minta yang baru dari bot.";
    }

    if ($member) {
        $user_telegram_id = $member['user_id']; // Kolom user_id sekarang berisi telegram_id

        // Jika valid, tandai token sebagai sudah digunakan untuk mencegah replay attack.
        $update_stmt = $pdo->prepare("UPDATE members SET token_used = 1, login_token = NULL WHERE user_id = ?");
        $update_stmt->execute([$user_telegram_id]);

        // Atur session untuk menandai pengguna sebagai sudah login.
        $_SESSION['member_user_id'] = $user_telegram_id;

        // Ambil info pengguna untuk logging yang lebih baik
        $user_info_stmt = $pdo->prepare("SELECT first_name, username FROM users WHERE telegram_id = ?");
        $user_info_stmt->execute([$user_telegram_id]);
        $user_info = $user_info_stmt->fetch(PDO::FETCH_ASSOC);

        $log_message = "Login member berhasil: ";
        if ($user_info) {
            $log_message .= "Name: {$user_info['first_name']}, Username: @{$user_info['username']}, TelegramID: {$user_telegram_id}";
        } else {
            $log_message .= "telegram_id = {$user_telegram_id} (Info pengguna tidak ditemukan)";
        }
        app_log($log_message, 'member');

        // Alihkan ke dasbor.
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
