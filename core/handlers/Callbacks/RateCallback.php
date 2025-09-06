<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;

class RateCallback implements CallbackCommandInterface
{
    public function execute(App $app, array $callback_query, string $params): void
    {
        // Logic for rate callback will go here
        $app->telegram_api->answerCallbackQuery($callback_query['id'], 'Rate callback diproses.');
    }
}
