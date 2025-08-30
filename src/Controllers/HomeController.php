<?php

require_once __DIR__ . '/AppController.php';

class HomeController extends AppController
{
    /**
     * Menampilkan halaman maintenance.
     */
    public function index()
    {
        // Set header 503 Service Unavailable
        http_response_code(503);

        // Tampilkan view tanpa layout
        $this->view('maintenance', [], null);
    }
}
