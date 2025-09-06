<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;

class TanyaCallback implements CallbackCommandInterface
{
    public function execute(App $app, array $callback_query, string $params): void
    {
        // Logic for tanya callback will go here
        $app->telegram_api->answerCallbackQuery($callback_query['id'], 'Tanya callback diproses.');
    }
}
