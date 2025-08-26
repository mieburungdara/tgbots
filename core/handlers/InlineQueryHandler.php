<?php

require_once __DIR__ . '/../database/PackageRepository.php';
require_once __DIR__ . '/HandlerInterface.php';

/**
 * Menangani permintaan inline query dari pengguna.
 * Memungkinkan pengguna untuk mencari dan berbagi paket konten langsung dari chat manapun.
 */
class InlineQueryHandler implements HandlerInterface
{
    /**
     * Titik masuk utama untuk menangani inline query.
     *
     * @param App $app Wadah aplikasi (mungkin tidak sepenuhnya terisi untuk inline query).
     * @param array $inline_query Data inline query lengkap dari Telegram.
     */
    public function handle(App $app, array $inline_query): void
    {
        $package_repo = new PackageRepository($app->pdo);

        $query_id = $inline_query['id'];
        $query_text = $inline_query['query'];

        $results = [];

        if (strlen($query_text) > 2) { // Hanya cari jika query cukup panjang
            $package = $package_repo->findByPublicId($query_text);

            if ($package) {
                $price_formatted = "Rp " . number_format($package['price'], 0, ',', '.');
                $buy_url = "https://t.me/" . BOT_USERNAME . "?start=package_" . $package['public_id'];

                $results[] = [
                    'type' => 'article',
                    'id' => $package['public_id'],
                    'title' => "Konten: " . ($package['description'] ?: 'Tanpa Judul'),
                    'description' => "Harga: {$price_formatted}",
                    'input_message_content' => [
                        'message_text' => "Saya ingin berbagi konten ini:\n\n*{$package['description']}*\n\nHarga: *{$price_formatted}*",
                        'parse_mode' => 'Markdown'
                    ],
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [['text' => "Beli Konten Ini 🛒", 'url' => $buy_url]]
                        ]
                    ]
                ];
            }
        }

        $app->telegram_api->answerInlineQuery($query_id, $results);
    }
}
