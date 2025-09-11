<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;
use TGBot\Database\UserRepository;

class OfferCallback implements CallbackCommandInterface
{
    public function execute(App $app, array $callback_query, string $public_id): void
    {
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);

        $state_context = ['public_id' => $public_id];
        $user_repo->setUserState($app->user['id'], 'awaiting_offer_price', $state_context);

        $app->telegram_api->answerCallbackQuery($callback_query['id']);
        $app->telegram_api->sendMessage(
            $app->chat_id, 
            "Silakan masukkan harga penawaran Anda untuk item `{$public_id}`.",
            'Markdown'
        );
    }
}
