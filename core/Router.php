<?php

class Router {
    protected $routes = [
        'GET' => [],
        'POST' => []
    ];

    public static function load($file) {
        $router = new static;
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
        $uri = trim($uri, '/');

        foreach ($this->routes[$requestType] as $route => $controller) {
            // Convert route with params like {id} to a regex
            $routeRegex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?<${1}>[^/]+)', $route);
            $routeRegex = '#^' . $routeRegex . '$#';

            // Check if the current request URI matches the route regex
            if (preg_match($routeRegex, $uri, $matches)) {
                try {
                    // Extract named capture groups into the params array
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                    return $this->callAction(
                        ...explode('@', $controller),
                        $params
                    );
                } catch (Exception $e) {
                    error_log("Router Error: " . $e->getMessage());
                    http_response_code(500);
                    require __DIR__ . '/../src/Views/500.php';
                    exit();
                }
            }
        }

        // Handle 404
        http_response_code(404);
        require __DIR__ . '/../src/Views/404.php';
        exit();
    }

    protected function callAction($controller, $action, $params = []) {
        $controllerFile = __DIR__ . "/../src/Controllers/{$controller}.php";

        if (!file_exists($controllerFile)) {
            throw new Exception("Controller file not found: {$controllerFile}");
        }

        require_once $controllerFile;

        $parts = explode('/', $controller);
        $controllerClass = end($parts);

        if (!class_exists($controllerClass)) {
            throw new Exception("Controller class not found: {$controllerClass}");
        }

        $controllerInstance = new $controllerClass;

        if (!method_exists($controllerInstance, $action)) {
            throw new Exception("{$controllerClass} does not respond to the {$action} action.");
        }

        // Call the controller action with the extracted parameters
        return $controllerInstance->$action($params);
    }
}
