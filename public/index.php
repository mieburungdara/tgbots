<?php

use TGBot\Router;
use TGBot\App;
use TGBot\LoggerFactory;

// Bootstrap the application
require_once __DIR__ . '/../vendor/autoload.php';

// --- Load Configuration ---
$configFile = __DIR__ . '/../config.php';
if (!file_exists($configFile)) {
    http_response_code(503); // Service Unavailable
    $errorTemplate = __DIR__ . '/../core/templates/setup_error.php';
    if (file_exists($errorTemplate)) {
        // Pass a specific message to the template
        $errorMessage = '<strong>Error:</strong> File konfigurasi <code>config.php</code> tidak ditemukan di root proyek. Harap salin dari <code>config.php.example</code> dan isi kredensial yang diperlukan.';
        include $errorTemplate;
    } else {
        die('<strong>Error:</strong> Konfigurasi aplikasi (config.php) tidak ditemukan. Harap salin dari <code>config.php.example</code> dan isi kredensial yang diperlukan.');
    }
    exit;
}
require_once $configFile;

require_once __DIR__ . '/../core/database.php'; // Include database connection functions

// Initialize and set the centralized logger
$logger = LoggerFactory::create('app', __DIR__ . '/../logs/app.log');
App::setLogger($logger);

// Get the request URI and remove the base path to isolate the application-specific URI
$basePath = rtrim(parse_url(BASE_URL, PHP_URL_PATH), '/');
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$uri = '/'; // Default URI
if (strpos($requestUri, $basePath) === 0) {
    $uri = substr($requestUri, strlen($basePath));
}
$uri = trim($uri, '/');

// Load the routes and direct the request
try {
    // The routes file will be loaded by the router and will have access to the $router instance.
    Router::load(__DIR__ . '/../routes.php')
        ->direct($uri, $_SERVER['REQUEST_METHOD']);
} catch (Exception $e) {
    // Log the error using the centralized logger
    App::getLogger()->critical("Front Controller Exception: " . $e->getMessage());

    // Display a generic error page
    http_response_code(500);
    require __DIR__ . '/../src/Views/500.php';
}
