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
use ReflectionClass;
use TGBot\Logger;

/**
 * Class Router
 * @package TGBot
 *
 * @purpose Kelas ini bertanggung jawab untuk mengatur dan mengarahkan permintaan HTTP 
 * (misalnya, saat Anda membuka URL di browser) ke controller dan metode yang sesuai. 
 * Ini adalah inti dari sistem routing aplikasi.
 */
class Router
{
    protected Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @var array
     */
    protected array $routes = [
        'GET' => [],
        'POST' => []
    ];

    /**
     * Memuat file yang berisi definisi rute.
     *
     * @param string $file Path ke file rute.
     * @return static
     */
    public static function load(string $file, Logger $logger): static
    {
        $router = new static($logger);
        require $file;
        return $router;
    }

    /**
     * Mendaftarkan rute GET.
     */
    public function get(string $uri, string $controller): void
    {
        $this->routes['GET'][$uri] = $controller;
    }

    /**
     * Mendaftarkan rute POST.
     */
    public function post(string $uri, string $controller): void
    {
        $this->routes['POST'][$uri] = $controller;
    }

    /**
     * Mengarahkan permintaan ke controller dan aksi yang sesuai.
     */
    public function direct(string $uri, string $requestType)
    {
        $uri = trim($uri, '/');

        // Jika metode HTTP tidak didukung
        if (!isset($this->routes[$requestType])) {
            http_response_code(404);
            require __DIR__ . '/../src/Views/404.php';
            exit();
        }

        foreach ($this->routes[$requestType] as $route => $controller) {
            // Allow numbers in placeholder names
            $routeRegex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?<$1>[^/]+)', $route);
            $routeRegex = '#^' . $routeRegex . '$#';

            if (preg_match($routeRegex, $uri, $matches)) {
                try {
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                    $parts = explode('@', $controller);

                    if (count($parts) !== 2 || empty(trim($parts[0])) || empty(trim($parts[1]))) {
                        throw new Exception("Format controller tidak valid pada definisi rute: '{$controller}'. Diharapkan format 'Controller@action'.");
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
     * Memanggil aksi pada controller.
     */
    protected function callAction(string $controller, string $action, array $params = [])
    {
        // Path file controller
        $controllerFile = __DIR__ . "/../src/Controllers/" . str_replace('\\', '/', $controller) . ".php";

        if (!file_exists($controllerFile)) {
            throw new Exception("Controller file not found: {$controllerFile}");
        }

        require_once $controllerFile;

        // Fully qualified class name
        $className = "TGBot\\Controllers\\" . str_replace('/', '\\', $controller);

        if (!class_exists($className)) {
            throw new Exception("Controller class not found: {$className}");
        }

        $reflectionClass = new ReflectionClass($className);
        $constructor = $reflectionClass->getConstructor();

        $controllerInstance = null;
        if ($constructor && $constructor->getNumberOfParameters() > 0) {
            $paramsToPass = [];
            foreach ($constructor->getParameters() as $param) {
                if ($param->getType() && $param->getType()->getName() === Logger::class) {
                    $paramsToPass[] = $this->logger;
                } else {
                    throw new Exception("Unsupported constructor parameter type for controller {$className}: {$param->getName()}");
                }
            }
            $controllerInstance = $reflectionClass->newInstanceArgs($paramsToPass);
        } else {
            $controllerInstance = $reflectionClass->newInstance();
        }

        if (!method_exists($controllerInstance, $action)) {
            throw new Exception("{$className} does not respond to the {$action} action.");
        }

        // Reflection untuk handle parameter action
        $reflection = new ReflectionMethod($controllerInstance, $action);
        if ($reflection->getNumberOfParameters() === 0) {
            return $controllerInstance->$action();
        }

        return $controllerInstance->$action($params);
    }
}
