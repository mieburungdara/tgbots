<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;
use TGBot\Database\FeatureChannelRepository;

class ShowChannelSelectionCallback implements CallbackCommandInterface
{
    public function execute(App $app, array $callback_query, string $public_id): void
    {
        $callback_query_id = $callback_query['id'];
        $telegram_user_id = $app->user['id'];

        $feature_channel_repo = new FeatureChannelRepository($app->pdo);
        $channels = $feature_channel_repo->findAllByOwnerAndFeature($telegram_user_id, 'sell');

        if (empty($channels)) {
            $app->telegram_api->answerCallbackQuery($callback_query_id, '⚠️ Anda belum mendaftarkan channel jualan.', true);
            return;
        }

        if (count($channels) === 1) {
            // If only one channel, post directly
            $postToChannelCallback = new PostToChannelCallback();
            $postToChannelCallback->execute($app, $callback_query, $public_id . '_' . $channels[0]['id']);
            return;
        }

        // If multiple channels, show selection keyboard
        $keyboard_buttons = [];
        foreach ($channels as $channel) {
            $channel_name = $channel['name'] ?? ('Channel ID: ' . $channel['public_channel_id']);
            $keyboard_buttons[] = [['text' => $channel_name, 'callback_data' => "post_to_{$public_id}_{$channel['id']}"]];
        }

        $keyboard = ['inline_keyboard' => $keyboard_buttons];
        $app->telegram_api->sendMessage(
            $app->chat_id,
            "Pilih channel tujuan untuk mem-posting:",
            null,
            json_encode($keyboard)
        );
        $app->telegram_api->answerCallbackQuery($callback_query_id);
    }
}
