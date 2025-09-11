<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;
use TGBot\Database\UserRepository;

class AskSellerCallback implements CallbackCommandInterface
{
    public function execute(App $app, array $callback_query, string $params): void
    {
        list($public_id, $seller_user_id) = explode('_', $params);

        $user_repo = new UserRepository($app->pdo, $app->bot['id']);

        // Set user state to await question
        $state_context = [
            'package_public_id' => $public_id,
            'seller_user_id' => (int)$seller_user_id,
            'buyer_user_id' => $app->user['id']
        ];
        $user_repo->setUserState($app->user['id'], 'awaiting_question', $state_context);

        $app->telegram_api->answerCallbackQuery($callback_query['id'], 'Silakan ketik pertanyaan Anda.', true);
        $app->telegram_api->editMessageText(
            $app->chat_id,
            $callback_query['message']['message_id'],
            "Anda akan bertanya tentang konten `{$public_id}` kepada penjual. Silakan ketik pertanyaan Anda sekarang.",
            'Markdown',
            json_encode(['inline_keyboard' => [[['text' => 'Batalkan ❌', 'callback_data' => "cancel_ask_seller_{$public_id}"]]]])
        );
    }
}
