<?php

class Router {
    protected $routes = [
        'GET' => [],
        'POST' => []
    ];

    public static function load($file) {
        $router = new static;
        // The $file will define the routes.
        // We will create this file later.
        require $file;
        return $router;
    }

    public function get($uri, $controller) {
        $this->routes['GET'][$uri] = $controller;
    }

    public function post($uri, $controller) {
        $this->routes['POST'][$uri] = $controller;
    }

    public function direct($uri, $requestType) {
        // Trim slashes and parse the URL
        $uri = trim($uri, '/');

        if (array_key_exists($uri, $this->routes[$requestType])) {
            try {
                return $this->callAction(
                    ...explode('@', $this->routes[$requestType][$uri])
                );
            } catch (Exception $e) {
                // Log the error message
                error_log("Router Error: " . $e->getMessage());
                // Show a generic error page
                http_response_code(500);
                require __DIR__ . '/../src/Views/500.php'; // We'll create this view
                exit();
            }
        }

        // Handle 404
        http_response_code(404);
        require __DIR__ . '/../src/Views/404.php'; // We'll create this view
        exit();
    }

    protected function callAction($controller, $action) {
        // Example controller string: 'Admin/DashboardController'
        $controllerFile = __DIR__ . "/../src/Controllers/{$controller}.php";

        if (!file_exists($controllerFile)) {
            throw new Exception("Controller file not found: {$controllerFile}");
        }

        require_once $controllerFile;

        // The controller class name is the last part of the path
        $parts = explode('/', $controller);
        $controllerClass = end($parts);

        if (!class_exists($controllerClass)) {
            throw new Exception("Controller class not found: {$controllerClass}");
        }

        $controllerInstance = new $controllerClass;

        if (!method_exists($controllerInstance, $action)) {
            throw new Exception("{$controllerClass} does not respond to the {$action} action.");
        }

        return $controllerInstance->$action();
    }
}
