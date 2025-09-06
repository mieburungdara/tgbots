<?php

namespace TGBot\Handlers\Commands;

use TGBot\App;

class LoginCommand implements CommandInterface
{
    public function execute(App $app, array $message, array $parts): void
    {
        if (!defined('BASE_URL') || empty(BASE_URL)) {
            $app->telegram_api->sendMessage($app->chat_id, "Maaf, terjadi kesalahan teknis (ERR:CFG01).");
            return;
        }

        $login_token = bin2hex(random_bytes(32));
        $app->pdo->prepare("UPDATE users SET login_token = ?, token_created_at = NOW(), token_used = 0 WHERE id = ?")
             ->execute([$login_token, $app->user['id']]);

        $login_link = rtrim(BASE_URL, '/') . '/member/token-login?token=' . $login_token;
        $response = "Klik tombol di bawah ini untuk masuk ke Panel Anda. Tombol ini hanya dapat digunakan satu kali.";
        $keyboard = ['inline_keyboard' => [[['text' => 'Login ke Panel', 'url' => $login_link]]]];
        $app->telegram_api->sendMessage($app->chat_id, $response, null, json_encode($keyboard));
    }
}
