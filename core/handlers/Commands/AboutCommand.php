<?php

namespace TGBot\Handlers\Commands;

use TGBot\App;

class AboutCommand implements CommandInterface
{
    public function execute(App $app, array $message, array $parts): void
    {
        $about_text = "🤖 *Tentang Bot Ini*\n\n" .
                      "Bot ini dikembangkan oleh *Zidin Mitra Abadi*.\n\n" .
                      "Untuk pertanyaan atau peluang kerja sama, silakan hubungi kami melalui [link ini](https://t.me/your_contact_link_here)."; // Placeholder link

        $app->telegram_api->sendMessage($app->chat_id, $about_text, 'Markdown');
    }
}
