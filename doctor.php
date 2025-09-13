<?php

// Health Check Script (doctor.php)
// Jalankan dari CLI: php doctor.php
// Untuk output JSON: php doctor.php --format=json

// --- Argument Parsing ---
$options = getopt("", ["format::"]);
$is_json_output = isset($options['format']) && $options['format'] === 'json';

$results = [];
$error_count = 0;

// --- Helper Functions ---
function colorize($text, $status) {
    // Hanya warnai jika output bukan JSON
    global $is_json_output;
    if ($is_json_output) {
        return $text;
    }
    switch ($status) {
        case 'SUCCESS': return "\033[32m" . $text . "\033[0m"; // Green
        case 'ERROR': return "\033[31m" . $text . "\033[0m"; // Red
        case 'WARN': return "\033[33m" . $text . "\033[0m"; // Yellow
        default: return "\033[0m" . $text . "\033[0m"; // Normal
    }
}

function add_check_result($description, $status, $message = '') {
    global $results, $is_json_output, $error_count;

    $results[] = [
        'description' => $description,
        'status' => $status,
        'message' => $message
    ];

    if ($status !== 'SUCCESS') {
        $error_count++;
    }

    if (!$is_json_output) {
        $status_icon = ($status === 'SUCCESS') ? '✓' : '✗';
        $status_text = str_pad("[$status]", 9, ' ');
        echo colorize($status_icon . " " . $status_text, $status) . str_pad($description, 40) . ($message ? " -> " . $message : '') . PHP_EOL;
    }
}

// --- Header untuk CLI ---
if (!$is_json_output) {
    echo "=========================================" . PHP_EOL;
    echo "  Memulai Pemeriksaan Sistem Aplikasi..." . PHP_EOL;
    echo "=========================================" . PHP_EOL . PHP_EOL;
}

// --- Mulai Pemeriksaan ---

// 1. Cek Versi PHP
$min_php_version = '8.0';
if (version_compare(PHP_VERSION, $min_php_version, '>=')) {
    add_check_result('Versi PHP', 'SUCCESS', 'Ditemukan: ' . PHP_VERSION);
} else {
    add_check_result('Versi PHP', 'ERROR', 'Diperlukan >= ' . $min_php_version . ', ditemukan ' . PHP_VERSION);
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
    add_check_result('Ekstensi PHP', 'SUCCESS', implode(', ', $required_extensions));
} else {
    add_check_result('Ekstensi PHP', 'ERROR', 'Ekstensi berikut tidak ditemukan: ' . implode(', ', $missing_extensions));
}

// 3. Cek Dependensi Composer
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    add_check_result('Dependensi Composer', 'SUCCESS', 'Direktori vendor/ ditemukan.');
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    add_check_result('Dependensi Composer', 'ERROR', 'Direktori vendor/ tidak ditemukan. Jalankan `composer install`.');
}

// 4. Cek File Konfigurasi (config.php)
$config_file = __DIR__ . '/config.php';
$config_loaded = false;
if (file_exists($config_file)) {
    if (is_readable($config_file)) {
        add_check_result('File config.php', 'SUCCESS', 'Ditemukan dan dapat dibaca.');
        require_once $config_file;
        $config_loaded = true;
    } else {
        add_check_result('File config.php', 'ERROR', 'Ditemukan tapi tidak dapat dibaca (permission issue).');
    }
} else {
    add_check_result('File config.php', 'ERROR', 'Tidak ditemukan. Salin dari config.php.example.');
}

// 5. Cek Koneksi Database
if ($config_loaded) {
    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            add_check_result('Koneksi Database', 'SUCCESS', 'Berhasil terhubung ke ' . DB_NAME . '@' . DB_HOST);
        } catch (PDOException $e) {
            add_check_result('Koneksi Database', 'ERROR', 'Gagal terhubung: ' . substr($e->getMessage(), 0, 100) . '...');
        }
    } else {
        add_check_result('Koneksi Database', 'WARN', 'Konstanta DB (DB_HOST, etc.) tidak terdefinisi di config.php.');
    }
} else {
    add_check_result('Koneksi Database', 'WARN', 'Dilewati karena config.php tidak dimuat.');
}

// 6. Cek Izin Tulis Direktori
$writable_dirs = ['logs', 'workflow'];
foreach ($writable_dirs as $dir) {
    $full_path = __DIR__ . '/' . $dir;
    if (is_dir($full_path)) {
        if (is_writable($full_path)) {
            add_check_result("Izin Tulis: ./{$dir}", 'SUCCESS', 'Direktori dapat ditulis.');
        } else {
            add_check_result("Izin Tulis: ./{$dir}", 'ERROR', 'Direktori tidak dapat ditulis.');
        }
    } else {
        add_check_result("Izin Tulis: ./{$dir}", 'ERROR', 'Direktori tidak ditemukan.');
    }
}

// --- Final Output ---
if ($is_json_output) {
    header('Content-Type: application/json');
    echo json_encode($results);
} else {
    echo PHP_EOL . "=========================================" . PHP_EOL;
    if ($error_count === 0) {
        echo colorize("  ✓ Pemeriksaan selesai. Sistem Anda sehat!", 'SUCCESS') . PHP_EOL;
    } else {
        echo colorize("  ✗ Pemeriksaan selesai. Ditemukan {$error_count} masalah.", 'ERROR') . PHP_EOL;
    }
    echo "=========================================" . PHP_EOL;
}

exit($error_count > 0 ? 1 : 0); // Exit with 0 on success, 1 on failure

?>