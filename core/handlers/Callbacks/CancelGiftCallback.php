<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;
use TGBot\Database\UserRepository;

class CancelGiftCallback implements CallbackCommandInterface
{
    public function execute(App $app, array $callback_query, string $public_id): void
    {
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);

        // Clear the user's state
        $user_repo->setUserState($app->user['id'], null, null);

        $app->telegram_api->editMessageText(
            $app->chat_id,
            $callback_query['message']['message_id'],
            "✅ Proses hadiah untuk paket `{$public_id}` telah dibatalkan.",
            'Markdown'
        );
        $app->telegram_api->answerCallbackQuery($callback_query['id'], 'Proses hadiah dibatalkan.');
    }
}
