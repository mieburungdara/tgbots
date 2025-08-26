<?php

declare(strict_types=1);

require_once __DIR__ . '/../App.php';

/**
 * Interface HandlerInterface
 *
 * Mendefinisikan kontrak untuk semua kelas handler pembaruan.
 * Setiap handler harus mengimplementasikan metode `handle` untuk memproses
 * jenis pembaruan spesifiknya.
 */
interface HandlerInterface
{
    /**
     * Menangani pembaruan yang masuk.
     *
     * @param App $app Wadah aplikasi yang berisi resource bersama.
     * @param array $update Bagian spesifik dari data pembaruan yang relevan untuk handler ini
     *                      (misalnya, $update['message'] atau $update['callback_query']).
     * @return void
     */
    public function handle(App $app, array $update): void;
}
