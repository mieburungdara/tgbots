<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;

class ReviewSellerCallback implements CallbackCommandInterface
{
    public function execute(App $app, array $callback_query, string $seller_user_id): void
    {
        $app->telegram_api->answerCallbackQuery($callback_query['id'], 'Fitur ulasan penjual sedang dalam pengembangan.', true);
        $app->telegram_api->sendMessage($app->chat_id, "Anda memilih untuk mengulas penjual dengan ID {$seller_user_id}. Fitur ini akan segera hadir!");
    }
}
