<?php

// Muat file konfigurasi aplikasi untuk mendapatkan kredensial database
// Ini memungkinkan Phinx menggunakan koneksi yang sama dengan aplikasi utama
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    // Jika config.php tidak ada, gunakan variabel lingkungan atau fallback ke nilai default
    // Ini berguna untuk lingkungan CI/CD atau jika Anda tidak ingin membuat config.php
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_NAME', getenv('DB_NAME') ?: '');
    define('DB_USER', getenv('DB_USER') ?: '');
    define('DB_PASS', getenv('DB_PASS') ?: '');
}

return
[
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'production',
        'production' => [
            'adapter' => 'mysql',
            'host' => DB_HOST,
            'name' => DB_NAME,
            'user' => DB_USER,
            'pass' => DB_PASS,
            'port' => 3306,
            'charset' => 'utf8mb4',
        ]
    ],
    'version_order' => 'creation'
];