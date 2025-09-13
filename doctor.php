<?php

// Health Check Script (doctor.php)
// Jalankan dari CLI: php doctor.php

// Helper function untuk output berwarna di CLI
function colorize($text, $status) {
    switch ($status) {
        case 'SUCCESS':
            $color = "\033[32m"; // Green
            break;
        case 'ERROR':
            $color = "\033[31m"; // Red
            break;
        case 'WARN':
            $color = "\033[33m"; // Yellow
            break;
        default:
            $color = "\033[0m"; // Normal
            break;
    }
    return $color . $text . "\033[0m";
}

// Helper untuk mencetak hasil pemeriksaan
function print_check($description, $status, $message = '') {
    $status_icon = ($status === 'SUCCESS') ? '✓' : '✗';
    $status_text = str_pad("[$status]", 9, ' ');
    echo colorize($status_icon . " " . $status_text, $status) . str_pad($description, 40) . ($message ? " -> " . $message : '') . PHP_EOL;
}

$error_count = 0;

// --- Mulai Pemeriksaan ---
echo "=========================================" . PHP_EOL;
echo "  Memulai Pemeriksaan Sistem Aplikasi..." . PHP_EOL;
echo "=========================================" . PHP_EOL . PHP_EOL;

// 1. Cek Versi PHP
$min_php_version = '8.0';
if (version_compare(PHP_VERSION, $min_php_version, '>=')) {
    print_check('Versi PHP', 'SUCCESS', 'Ditemukan: ' . PHP_VERSION);
} else {
    print_check('Versi PHP', 'ERROR', 'Diperlukan >= ' . $min_php_version . ', ditemukan ' . PHP_VERSION);
    $error_count++;
}

// 2. Cek Ekstensi PHP
$required_extensions = ['pdo_mysql', 'curl', 'json', 'mbstring', 'openssl'];
$missing_extensions = [];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing_extensions[] = $ext;
    }
}
if (empty($missing_extensions)) {
    print_check('Ekstensi PHP', 'SUCCESS', implode(', ', $required_extensions));
} else {
    print_check('Ekstensi PHP', 'ERROR', 'Ekstensi berikut tidak ditemukan: ' . implode(', ', $missing_extensions));
    $error_count++;
}

// 3. Cek Dependensi Composer
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    print_check('Dependensi Composer', 'SUCCESS', 'Direktori vendor/ ditemukan.');
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    print_check('Dependensi Composer', 'ERROR', 'Direktori vendor/ tidak ditemukan. Jalankan `composer install`.');
    $error_count++;
}

// 4. Cek File Konfigurasi (config.php)
$config_file = __DIR__ . '/config.php';
if (file_exists($config_file)) {
    if (is_readable($config_file)) {
        print_check('File config.php', 'SUCCESS', 'Ditemukan dan dapat dibaca.');
        require_once $config_file;
    } else {
        print_check('File config.php', 'ERROR', 'Ditemukan tapi tidak dapat dibaca (permission issue).');
        $error_count++;
    }
} else {
    print_check('File config.php', 'ERROR', 'Tidak ditemukan. Salin dari config.php.example.');
    $error_count++;
}

// 5. Cek Koneksi Database (hanya jika config.php ada)
if (file_exists($config_file) && is_readable($config_file)) {
    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            print_check('Koneksi Database', 'SUCCESS', 'Berhasil terhubung ke ' . DB_NAME . '@' . DB_HOST);
        } catch (PDOException $e) {
            print_check('Koneksi Database', 'ERROR', 'Gagal terhubung: ' . $e->getMessage());
            $error_count++;
        }
    } else {
        print_check('Koneksi Database', 'ERROR', 'Konstanta DB (DB_HOST, DB_NAME, etc.) tidak terdefinisi di config.php.');
        $error_count++;
    }
}

// 6. Cek Izin Tulis Direktori
$writable_dirs = ['logs', 'workflow'];
foreach ($writable_dirs as $dir) {
    $full_path = __DIR__ . '/' . $dir;
    if (is_dir($full_path) && is_writable($full_path)) {
        print_check("Izin Tulis: ./{$dir}", 'SUCCESS', 'Direktori dapat ditulis.');
    } else {
        $reason = !is_dir($full_path) ? 'tidak ditemukan' : 'tidak dapat ditulis';
        print_check("Izin Tulis: ./{$dir}", 'ERROR', "Direktori {$reason}.");
        $error_count++;
    }
}

// --- Ringkasan ---
echo PHP_EOL . "=========================================" . PHP_EOL;
if ($error_count === 0) {
    echo colorize("  ✓ Pemeriksaan selesai. Sistem Anda sehat!", 'SUCCESS') . PHP_EOL;
} else {
    echo colorize("  ✗ Pemeriksaan selesai. Ditemukan {$error_count} masalah.", 'ERROR') . PHP_EOL;
}
echo "=========================================" . PHP_EOL;

exit($error_count > 0 ? 1 : 0); // Exit with 0 on success, 1 on failure

?>
