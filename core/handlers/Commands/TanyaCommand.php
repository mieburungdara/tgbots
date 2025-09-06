<?php

namespace TGBot\Handlers\Commands;

use TGBot\App;
use TGBot\Database\BotRepository;
use TGBot\Database\UserRepository;
use TGBot\Database\MediaPackageRepository;

class TanyaCommand implements CommandInterface
{
    public function execute(App $app, array $message, array $parts): void
    {
        if (isset($app->bot['assigned_feature']) && $app->bot['assigned_feature'] !== 'tanya') {
            $bot_repo = new BotRepository($app->pdo);
            $correct_bots = $bot_repo->findAllBotsByFeature('tanya');
            $suggestion = "";
            if (!empty($correct_bots)) {
                $suggestion = "\n\nFitur ini tersedia di bot berikut:\n";
                foreach ($correct_bots as $bot) {
                    $suggestion .= "- @" . $bot['username'] . "\n";
                }
            }
            $app->telegram_api->sendMessage($app->chat_id, "Perintah `/tanya` tidak tersedia di bot ini." . $suggestion);
            return;
        }

        $user_repo = new UserRepository($app->pdo, $app->bot['id']);
        $post_repo = new MediaPackageRepository($app->pdo);

        if ($post_repo->hasPendingPost($app->user['id'])) {
            $app->telegram_api->sendMessage($app->chat_id, "Anda masih memiliki kiriman yang sedang dalam proses moderasi. Harap tunggu hingga selesai sebelum mengirim yang baru.");
            return;
        }

        if (!isset($message['reply_to_message'])) {
            $app->telegram_api->sendMessage($app->chat_id, "Untuk bertanya, silakan reply pesan yang ingin Anda tanyakan dengan perintah /tanya.");
            return;
        }

        $replied_message = $message['reply_to_message'];

        $state_context = [
            'message_id' => $replied_message['message_id'],
            'chat_id' => $replied_message['chat']['id'],
            'from_id' => $replied_message['from']['id'],
            'text' => $replied_message['text'] ?? $replied_message['caption'] ?? ''
        ];

        if ($message['from']['id'] !== $replied_message['from']['id']) {
            $app->telegram_api->sendMessage($app->chat_id, "⚠️ Gagal. Anda hanya bisa bertanya pada pesan milik Anda sendiri.");
            return;
        }

        $user_repo->setUserState($app->user['id'], 'awaiting_tanya_category', $state_context);

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'Mutualan', 'callback_data' => 'tanya_category_mutualan'],
                    ['text' => 'Tanya', 'callback_data' => 'tanya_category_tanya'],
                    ['text' => 'Dll', 'callback_data' => 'tanya_category_dll'],
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
