<?php

namespace TGBot\Handlers\Commands;

use TGBot\App;

class BalanceCommand implements CommandInterface
{
    public function execute(App $app, array $message, array $parts): void
    {
        $balance = "Rp " . number_format($app->user['balance'], 2, ',', '.');
        $app->telegram_api->sendMessage($app->chat_id, "Saldo Anda saat ini: {$balance}");
    }
}
