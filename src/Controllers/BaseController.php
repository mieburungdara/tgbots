<?php

abstract class BaseController {

    public function __construct() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // This check protects all controllers that extend BaseController.
        // The login token logic will be handled by a dedicated LoginController.
        if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
            http_response_code(403);

            // Inisialisasi variabel bot_username
            $bot_username = null;

            // Coba ambil bot default atau bot pertama dari database
            try {
                $pdo = get_db_connection();
                if ($pdo) {
                    // Query untuk mengambil username dari bot pertama yang ditemukan
                    // Ini asumsi, bisa disesuaikan jika ada logika bot "default"
                    $stmt = $pdo->query("SELECT username FROM bots ORDER BY id LIMIT 1");
                    $bot = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($bot && !empty($bot['username'])) {
                        $bot_username = $bot['username'];
                    }
                }
            } catch (Exception $e) {
                // Biarkan bot_username null jika ada error database
            }

            // Render halaman akses ditolak
            $this->view('auth/access_denied', ['bot_username' => $bot_username]);
            exit();
        }
    }

    /**
     * Renders a view file, optionally within a layout.
     *
     * @param string $view The view file path from src/Views (e.g., 'admin/dashboard/index').
     * @param array  $data Data to be extracted for the view.
     * @param string|null $layout The layout file to wrap the view in (e.g., 'admin_layout').
     */
    protected function view($view, $data = [], $layout = null) {
        // Make variables from the $data array available to the view
        extract($data);

        // Start output buffering
        ob_start();

        // Construct the path to the view file and include it
        $viewPath = __DIR__ . "/../../src/Views/{$view}.php";
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
            $layoutPath = __DIR__ . "/../../src/Views/layouts/{$layout}.php";
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
