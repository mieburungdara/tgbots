<?php

require_once __DIR__ . '/../database/PackageRepository.php';

class InlineQueryHandler
{
    private $pdo;
    private $telegram_api;
    private $package_repo;

    public function __construct(PDO $pdo, TelegramAPI $telegram_api)
    {
        $this->pdo = $pdo;
        $this->telegram_api = $telegram_api;
        $this->package_repo = new PackageRepository($pdo);
    }

    public function handle(array $inline_query)
    {
        $query_id = $inline_query['id'];
        $query_text = $inline_query['query'];

        $results = [];

        if (strlen($query_text) > 2) { // Only search if query is reasonably long
            $package = $this->package_repo->findByPublicId($query_text);

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

        $this->telegram_api->answerInlineQuery($query_id, $results);
    }
}
