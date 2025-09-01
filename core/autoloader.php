<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/database.php';

spl_autoload_register(function ($class) {
    $prefix = 'TGBot\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $base_dir = __DIR__ . '/../';

    $core_classes = [
        'TelegramAPI',
        'Router',
        'UpdateDispatcher',
        'App',
    ];

    $file = '';
    if (in_array($relative_class, $core_classes)) {
        $file = $base_dir . '/core/' . $relative_class . '.php';
    } else {
        $file = $base_dir . '/src/' . str_replace('\\', '/', $relative_class) . '.php';
    }

    if (file_exists($file)) {
        require_once $file;
    }
});