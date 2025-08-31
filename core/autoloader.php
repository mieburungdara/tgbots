<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers.php';

spl_autoload_register(function ($class) {
    $prefix = 'TGBot\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $base_dir = __DIR__ . '/../';

    // Map TGBot\Router to core/Router.php
    if ($relative_class === 'Router') {
        $file = $base_dir . 'core/Router.php';
    } else {
        $file = $base_dir . 'src/' . str_replace('\\', '/', $relative_class) . '.php';
    }

    if (file_exists($file)) {
        require_once $file;
    }
});