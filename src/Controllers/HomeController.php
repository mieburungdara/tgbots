<?php

namespace TGBot\Controllers;



use Exception;

class HomeController extends AppController
{
    /**
     * Menampilkan halaman maintenance.
     */
    public function index()
    {
        try {
            // Set header 503 Service Unavailable
            http_response_code(503);

            // Tampilkan view tanpa layout
            $this->view('maintenance', [], null);
        } catch (Exception $e) {
            // Catat error jika view tidak ditemukan atau ada masalah lain
            $this->logger->error('Error in HomeController: ' . $e->getMessage());

            // Tampilkan pesan error sederhana sebagai fallback
            http_response_code(500);
            // Tambahkan header untuk memastikan browser tidak meng-cache halaman error
            header('Content-Type: text/plain; charset=utf-8');
            echo "Terjadi kesalahan internal. Silakan coba lagi nanti.";
        }
    }
}
