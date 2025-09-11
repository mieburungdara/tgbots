<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;

class PromoteContentCallback implements CallbackCommandInterface
{
    public function execute(App $app, array $callback_query, string $public_id): void
    {
        $app->telegram_api->answerCallbackQuery($callback_query['id'], 'Fitur promosi konten sedang dalam pengembangan.', true);
        $app->telegram_api->sendMessage($app->chat_id, "Anda memilih untuk mempromosikan konten `{$public_id}`. Fitur ini akan segera hadir!");
    }
}
