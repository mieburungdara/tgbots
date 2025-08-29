<?php

require_once __DIR__ . '/AppController.php';

abstract class BaseController extends AppController {

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
}
