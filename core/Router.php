<?php

/**
 * This file is part of the TGBot package.
 *
 * (c) Zidin Mitra Abadi <zidinmitra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TGBot;

use Exception;
use ReflectionMethod;

/**
 * Class Router
 * @package TGBot
 */
class Router
{
    /**
     * @var array
     */
    protected array $routes = [
        'GET' => [],
        'POST' => []
    ];

    /**
     * Load a routes file.
     *
     * @param string $file
     * @return static
     */
    public static function load(string $file): static
    {
        $router = new static;
        require $file;
        return $router;
    }

    /**
     * Register a GET route.
     *
     * @param string $uri
     * @param string $controller
     * @return void
     */
    public function get(string $uri, string $controller): void
    {
        $this->routes['GET'][$uri] = $controller;
    }

    /**
     * Register a POST route.
     *
     * @param string $uri
     * @param string $controller
     * @return void
     */
    public function post(string $uri, string $controller): void
    {
        $this->routes['POST'][$uri] = $controller;
    }

    /**
     * Direct the request to the appropriate controller and action.
     *
     * @param string $uri
     * @param string $requestType
     * @return mixed
     */
    public function direct(string $uri, string $requestType)
    {
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

    /**
     * Call the action on the controller.
     *
     * @param string $controller
     * @param string $action
     * @param array $params
     * @return mixed
     * @throws Exception
     */
    protected function callAction(string $controller, string $action, array $params = [])
    {
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
