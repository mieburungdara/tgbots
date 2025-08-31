<?php

// Ensure errors are not displayed to the user, but are fully reported for logging.
ini_set('display_errors', '0');
ini_set('log_errors', '1'); // This is often on by default, but let's be sure.
error_reporting(E_ALL);

// Load helpers first so app_log is available for our handlers.
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/database.php'; // For get_db_connection in app_log

// --- Global Error and Exception Handling ---

// This function will handle any uncaught exceptions.
set_exception_handler(function($exception) {
    $error_message = sprintf(
        "Uncaught Exception: %s in %s on line %d",
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    );
    app_log($error_message, 'critical', ['trace' => $exception->getTraceAsString()]);

    if (headers_sent() === false) {
        http_response_code(500);
        // Attempt to show a user-friendly error page.
        if (file_exists(__DIR__ . '/../src/Views/500.php')) {
            require __DIR__ . '/../src/Views/500.php';
        } else {
            echo "500 - Terjadi Kesalahan Internal Server.";
        }
    }
    exit();
});

// This function will handle non-fatal PHP errors (warnings, notices, etc.).
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return; // This error code is not included in error_reporting
    }
    app_log(
        "PHP Error: " . $message,
        'error',
        ['severity' => $severity, 'file' => $file, 'line' => $line]
    );
    // Do not execute the internal PHP error handler.
    return true;
});

// This function will be called at the end of the script, allowing us to catch fatal errors.
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        app_log(
            "Fatal Error: " . $error['message'],
            'critical',
            ['file' => $error['file'], 'line' => $error['line']]
        );
        // When a fatal error occurs, we can't render a nice view,
        // so we just ensure a generic message is shown if nothing has been sent.
        if (headers_sent() === false) {
             http_response_code(500);
             header('Content-Type: text/plain; charset=utf-8');
             echo "500 - Terjadi Kesalahan Internal Server. Silakan periksa log aplikasi untuk detail.";
        }
    }
});

// --- End of Error Handling ---


// Bootstrap the application
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
