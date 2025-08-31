<?php

use TGBot\Router;

// Bootstrap the application
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/Router.php';

// Get the request URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// This simple logic assumes the app is in the web root.
// A more robust solution would calculate the base path dynamically.
$uri = trim($uri, '/');

// Load the routes and direct the request
try {
    // The routes file will be loaded by the router and will have access to the $router instance.
    Router::load(__DIR__ . '/../routes.php')
        ->direct($uri, $_SERVER['REQUEST_METHOD']);
} catch (Exception $e) {
    // Log the error
    error_log("Front Controller Exception: " . $e->getMessage());

    // Display a generic error page
    http_response_code(500);
    require __DIR__ . '/../src/Views/500.php';
}
