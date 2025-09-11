<?php

namespace TGBot\Handlers\States;

use TGBot\App;
use TGBot\Database\UserRepository;

class AwaitingPriceState implements StateInterface
{
    public function handle(App $app, array $message, array $state_context): void
    {
        $text = $message['text'];
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);

        $price = filter_var($text, FILTER_VALIDATE_INT);
        if ($price === false || $price <= 0) {
            $app->telegram_api->sendMessage($app->chat_id, "Harga tidak valid. Harap masukkan angka bulat positif.");
            return;
        }

        // Hapus state lama agar pengguna tidak terjebak
        $user_repo->setUserState($app->user['id'], null, null);

        // Kirim pesan konfirmasi
        $this->sendConfirmationMessage($app, $price, $state_context);
    }

    public static function sendConfirmationMessage(App $app, int $price, array $media_context): void
    {
        $original_message = $media_context['media_messages'][0];
        $original_message_id = $original_message['message_id'];
        $original_chat_id = $original_message['chat_id'];

        $price_formatted = "Rp " . number_format($price, 0, ',', '.');
        $message_text = "✨ *Konfirmasi Penjualan* ✨\n\n";
        $message_text .= "Anda akan menjual item ini dengan harga *{$price_formatted}*.\n\n";
        $message_text .= "Silakan konfirmasi untuk melanjutkan atau batalkan.";

        $callback_data = "sell_confirm_{$price}_{$original_message_id}_{$original_chat_id}";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Konfirmasi & Jual', 'callback_data' => $callback_data],
                    ['text' => '❌ Batal', 'callback_data' => 'cancel_sell'] // Nanti bisa ditangani untuk hapus pesan
                ]
            ]
        ];

        $app->telegram_api->sendMessage($app->chat_id, $message_text, 'Markdown', json_encode($keyboard));
    }
}
