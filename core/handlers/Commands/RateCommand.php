<?php

namespace TGBot\Handlers\Commands;

use TGBot\App;
use TGBot\Database\BotRepository;
use TGBot\Database\UserRepository;
use TGBot\Database\MediaPackageRepository;

class RateCommand implements CommandInterface
{
    public function execute(App $app, array $message, array $parts): void
    {
        if (isset($app->bot['assigned_feature']) && $app->bot['assigned_feature'] !== 'rate') {
            $bot_repo = new BotRepository($app->pdo);
            $correct_bots = $bot_repo->findAllBotsByFeature('rate');
            $suggestion = "";
            if (!empty($correct_bots)) {
                $suggestion = "\n\nFitur ini tersedia di bot berikut:\n";
                foreach ($correct_bots as $bot) {
                    $suggestion .= "- @" . $bot['username'] . "\n";
                }
            }
            $app->telegram_api->sendMessage($app->chat_id, "Perintah `/rate` tidak tersedia di bot ini." . $suggestion);
            return;
        }

        $user_repo = new UserRepository($app->pdo, $app->bot['id']);
        $post_repo = new MediaPackageRepository($app->pdo);

        if ($post_repo->hasPendingPost($app->user['id'])) {
            $app->telegram_api->sendMessage($app->chat_id, "Anda masih memiliki kiriman yang sedang dalam proses moderasi. Harap tunggu hingga selesai sebelum mengirim yang baru.");
            return;
        }

        if (!isset($message['reply_to_message'])) {
            $app->telegram_api->sendMessage($app->chat_id, "Untuk memberi rate, silakan reply media yang ingin Anda beri rate dengan perintah /rate.");
            return;
        }

        $replied_message = $message['reply_to_message'];

        if (!isset($replied_message['photo']) && !isset($replied_message['video'])) {
            $app->telegram_api->sendMessage($app->chat_id, "⚠️ Gagal. Pastikan Anda me-reply pesan media (foto/video).");
            return;
        }

        $state_context = [
            'message_id' => $replied_message['message_id'],
            'chat_id' => $replied_message['chat']['id'],
            'from_id' => $replied_message['from']['id'],
        ];

        if ($message['from']['id'] !== $replied_message['from']['id']) {
            $app->telegram_api->sendMessage($app->chat_id, "⚠️ Gagal. Anda hanya bisa memberi rate pada media milik Anda sendiri.");
            return;
        }

        $user_repo->setUserState($app->user['id'], 'awaiting_rate_category', $state_context);

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'Cewek', 'callback_data' => 'rate_category_cewek'],
                    ['text' => 'Cowok', 'callback_data' => 'rate_category_cowok'],
                ]
            ]
        ];

        $app->telegram_api->sendMessage(
            $app->chat_id,
            "Silakan pilih kategori:",
            null,
            json_encode($keyboard),
            $replied_message['message_id']
        );
    }
}
