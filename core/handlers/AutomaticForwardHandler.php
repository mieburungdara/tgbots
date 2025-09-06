<?php

namespace TGBot\Handlers;

use TGBot\App;
use TGBot\Database\ChannelPostPackageRepository;

class AutomaticForwardHandler
{
    public function handle(App $app, array $message): void
    {
        $post_package_repo = new ChannelPostPackageRepository($app->pdo);
        $forward_origin = $message['forward_origin'] ?? null;

        if (!$forward_origin) return;

        $original_channel_id = $forward_origin['chat']['id'] ?? null;
        $original_message_id = $forward_origin['message_id'] ?? null;

        if (!$original_channel_id || !$original_message_id) return;

        $package = $post_package_repo->findByChannelAndMessage($original_channel_id, $original_message_id);

        if (!$package || $package['status'] !== 'available') return;

        $start_payload = "package_{$package['public_id']}";
        $bot_username = BOT_USERNAME; // Assuming this constant is available
        $url = "https://t.me/{$bot_username}?start={$start_payload}";

        $keyboard = ['inline_keyboard' => [[['text' => 'Beli Sekarang', 'url' => $url]]]];
        $reply_markup = json_encode($keyboard);
        $reply_parameters = ['message_id' => $message['message_id']];
        $reply_text = "Klik tombol di bawah untuk membeli";

        $app->telegram_api->sendMessage($app->chat_id, $reply_text, null, $reply_markup, null, $reply_parameters);
    }
}
