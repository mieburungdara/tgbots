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

        // Fix #2: Handle unregistered HTTP methods
        if (!isset($this->routes[$requestType])) {
            http_response_code(404);
            require __DIR__ . '/../src/Views/404.php';
            exit();
        }

        foreach ($this->routes[$requestType] as $route => $controller) {
            // Fix #3: Allow numbers in placeholder names
            $routeRegex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?<${1}>[^/]+)', $route);
            $routeRegex = '#^' . $routeRegex . '$#';

            if (preg_match($routeRegex, $uri, $matches)) {
                try {
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                    $parts = explode('@', $controller);

                    if (count($parts) !== 2 || empty(trim($parts[0])) || empty(trim($parts[1]))) {
                        throw new Exception("Format controller tidak valid pada definisi rute: '{$controller}'. Diharapkan format 'Controller@action' dengan bagian controller dan action yang tidak kosong.");
                    }

                    return $this->callAction($parts[0], $parts[1], $params);
                } catch (Exception $e) {
                    error_log("Router Error: " . $e->getMessage());
                    http_response_code(500);
                    require __DIR__ . '/../src/Views/500.php';
                    exit();
                }
            }
        }

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

        // Fix #1: Use Reflection to call method correctly based on its signature
        $reflection = new ReflectionMethod($controllerInstance, $action);
        if ($reflection->getNumberOfParameters() === 0) {
            return $controllerInstance->$action();
        }

        return $controllerInstance->$action($params);
    }
}
