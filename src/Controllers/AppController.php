<?php

abstract class AppController {

    /**
     * Renders a view file, optionally within a layout.
     *
     * @param string $view The view file path from src/Views (e.g., 'admin/dashboard/index').
     * @param array  $data Data to be extracted for the view.
     * @param string|null $layout The layout file to wrap the view in (e.g., 'admin_layout').
     */
    protected function view($view, $data = [], $layout = null) {
        // Start output buffering
        ob_start();

        // Construct the path to the view file and include it
        // This path needs to be relative from the `public/index.php` entry point.
        $viewPath = __DIR__ . "/../Views/{$view}.php";
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            ob_end_clean(); // Clean the buffer on error
            throw new Exception("View not found at path: {$viewPath}");
        }

        // Get the content from the buffer
        $content = ob_get_clean();

        // If a layout is specified, wrap the content in it
        if ($layout) {
            $layoutPath = __DIR__ . "/../Views/layouts/{$layout}.php";
            if (file_exists($layoutPath)) {
                // The $content variable will be available in the layout file
                require $layoutPath;
            } else {
                throw new Exception("Layout not found at path: {$layoutPath}");
            }
        } else {
            // If no layout, just echo the content
            echo $content;
        }
    }

    /**
     * Sends a JSON response.
     *
     * @param mixed $data The data to encode as JSON.
     * @param int $status_code The HTTP status code.
     */
    protected function jsonResponse($data, $status_code = 200) {
        http_response_code($status_code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
}
