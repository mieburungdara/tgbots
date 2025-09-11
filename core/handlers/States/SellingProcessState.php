<?php

namespace TGBot\Handlers\States;

use TGBot\App;
use TGBot\Database\UserRepository;

class SellingProcessState implements StateInterface
{
    public function handle(App $app, array $message, array $state_context): void
    {
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);

        // Prioritas 1: Cek jika ada media baru yang ditambahkan
        if (isset($message['photo']) || isset($message['video']) || isset($message['document'])) {
            $new_media_message = ['message_id' => $message['message_id'], 'chat_id' => $message['chat']['id']];
            $state_context['media_messages'][] = $new_media_message;

            // Update state dengan media baru
            $user_repo->setUserState($app->user['id'], 'selling_process', $state_context);

            $app->telegram_api->sendMessage($app->chat_id, "✅ Media ditambahkan. Kirim media lain, atau kirimkan harga untuk melanjutkan.");
            return;
        }

        // Prioritas 2: Cek jika ini adalah input harga
        if (isset($message['text'])) {
            $price = filter_var($message['text'], FILTER_VALIDATE_INT);
            if ($price === false || $price <= 0) {
                $app->telegram_api->sendMessage($app->chat_id, "Input tidak valid. Harap kirim media lain, atau masukkan harga (angka bulat positif) untuk melanjutkan.");
                return;
            }

            // Harga valid, lanjutkan ke konfirmasi
            $state_context['price'] = $price;
            $user_repo->setUserState($app->user['id'], 'awaiting_sell_confirmation', $state_context);

            $this->sendConfirmationMessage($app, $price, $state_context);
        }
    }

    private function sendConfirmationMessage(App $app, int $price, array $state_context): void
    {
        $media_count = count($state_context['media_messages']);
        $price_formatted = "Rp " . number_format($price, 0, ',', '.');
        
        $message_text = "✨ *Konfirmasi Penjualan* ✨\n\n";
        $message_text .= "Anda akan membuat paket berisi *{$media_count} media* dengan harga *{$price_formatted}*.\n\n";
        $message_text .= "Silakan konfirmasi untuk melanjutkan.";

        // Konteks sekarang ada di state, jadi callback hanya perlu memicu aksi
        $callback_data = "sell_confirm"; 

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Konfirmasi & Jual', 'callback_data' => $callback_data],
                    ['text' => '❌ Batal', 'callback_data' => 'cancel_sell']
                ]
            ]
        ];

        $app->telegram_api->sendMessage($app->chat_id, $message_text, 'Markdown', json_encode($keyboard));
    }
}
