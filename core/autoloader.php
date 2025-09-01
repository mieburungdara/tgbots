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
    $base_dir = realpath(__DIR__ . '/../');

    // Controllers are in src/, other core classes are in core/
    if (strpos($relative_class, 'Controllers\\') === 0) {
        $file = $base_dir . '/src/' . str_replace('\\', '/', $relative_class) . '.php';
    } else {
        $file = $base_dir . '/core/' . str_replace('\\', '/', $relative_class) . '.php';
    }

    if (file_exists($file)) {
        require_once $file;
    } else {
        // Fallback for cases where the namespace doesn't match the directory
        $fallback_file = $base_dir . '/src/' . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($fallback_file)) {
            require_once $fallback_file;
        }
    }
});