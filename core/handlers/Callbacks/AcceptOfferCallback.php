<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;

class AcceptOfferCallback implements CallbackCommandInterface
{
    public function execute(App $app, array $callback_query, string $offer_id): void
    {
        $stmt = $app->pdo->prepare("SELECT * FROM offers WHERE id = ? AND seller_user_id = ? AND status = 'pending'");
        $stmt->execute([$offer_id, $app->user['id']]);
        $offer = $stmt->fetch();

        if (!$offer) {
            $app->telegram_api->answerCallbackQuery($callback_query['id'], '⚠️ Penawaran tidak valid atau sudah direspons.', true);
            return;
        }

        // Update status penawaran
        $app->pdo->prepare("UPDATE offers SET status = 'accepted' WHERE id = ?")->execute([$offer_id]);

        // Kirim notifikasi ke pembeli
        $offer_price_formatted = "Rp " . number_format($offer['offer_price'], 0, ',', '.');
        $buy_callback_data = "buy_offer_{$offer_id}";
        $notification_text = "🎉 Selamat! Penawaran Anda untuk item #{$offer['package_id']} seharga {$offer_price_formatted} telah diterima.\n\nSilakan selesaikan pembelian Anda.";
        $keyboard = ['inline_keyboard' => [[['text' => 'Beli Sekarang', 'callback_data' => $buy_callback_data]]]];
        $app->telegram_api->sendMessage($offer['buyer_user_id'], $notification_text, null, json_encode($keyboard));

        // Edit pesan notifikasi penjual
        $app->telegram_api->editMessageText($app->chat_id, $callback_query['message']['message_id'], $callback_query['message']['text'] . "\n\n---\n✅ Anda menyetujui penawaran ini.");
        $app->telegram_api->answerCallbackQuery($callback_query['id'], 'Penawaran diterima.');
    }
}
