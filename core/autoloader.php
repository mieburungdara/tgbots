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
    // IMPORTANT: Convert to lowercase for case-sensitive filesystems (Linux)
    $relative_path = str_replace('\\', '/', $relative_class);

    if (strpos($relative_class, 'Controllers\\') === 0) {
        $file = $base_dir . '/src/' . $relative_path . '.php';
    } else {
        // Handle special cases for `database` and `handlers` directories
        if (strpos(strtolower($relative_path), 'database/') === 0 || strpos(strtolower($relative_path), 'handlers/') === 0) {
            $file = $base_dir . '/core/' . strtolower($relative_path) . '.php';
        } else {
            $file = $base_dir . '/core/' . $relative_path . '.php';
        }
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