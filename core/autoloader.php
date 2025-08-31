<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers.php';

spl_autoload_register(function ($class) {
    $base_dir = __DIR__ . '/../';

    $file = $base_dir . str_replace('''', '/', $class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
