<?php

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    die("\nFATAL ERROR: config.php not found in project root. Phinx migrations cannot run.\n\n");
}
require_once $configFile;

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
