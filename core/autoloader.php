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
    $relative_path = str_replace('\\', '/', $relative_class) . '.php';

    if (strpos($relative_class, 'Controllers\\') === 0) {
        $file = $base_dir . '/src/' . $relative_path;
    } else {
        // First, try a direct mapping (PSR-4 style)
        $file = $base_dir . '/core/' . $relative_path;

        // If that fails, handle the inconsistent directory casing
        if (!file_exists($file)) {
            $parts = explode('/', $relative_path);
            // Lowercase the first segment (e.g., 'Database' -> 'database', 'Handlers' -> 'handlers')
            $parts[0] = strtolower($parts[0]);
            $alt_file_path = implode('/', $parts);
            $file = $base_dir . '/core/' . $alt_file_path;
        }
    }

    if (file_exists($file)) {
        require_once $file;
    }
});