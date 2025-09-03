<?php

/**
 * This file is part of the TGBot package.
 *
 * (c) Zidin Mitra Abadi <zidinmitra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TGBot\Handlers;

use TGBot\App;
use TGBot\Handlers\HandlerInterface;
use TGBot\Database\PostPackageRepository;

/**
 * Class InlineQueryHandler
 * @package TGBot\Handlers
 */
class InlineQueryHandler implements HandlerInterface
{
    /**
     * Handle an inline query.
     *
     * @param App $app
     * @param array $inline_query
     * @return void
     */
    public function handle(App $app, array $inline_query): void
    {
        $package_repo = new PostPackageRepository($app->pdo);

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
