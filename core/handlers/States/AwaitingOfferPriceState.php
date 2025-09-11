<?php

namespace TGBot\Handlers\States;

use TGBot\App;
use TGBot\Database\UserRepository;
use TGBot\Database\MediaPackageRepository;

class AwaitingOfferPriceState implements StateInterface
{
    public function handle(App $app, array $message, array $state_context): void
    {
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);
        $package_repo = new MediaPackageRepository($app->pdo);

        $offer_price = filter_var($message['text'], FILTER_VALIDATE_INT);
        $public_id = $state_context['public_id'];

        $package = $package_repo->findByPublicId($public_id);

        if (!$package) {
            $app->telegram_api->sendMessage($app->chat_id, "Error: Item yang Anda tawar tidak lagi tersedia.");
            $user_repo->setUserState($app->user['id'], null, null);
            return;
        }

        if ($offer_price === false || $offer_price <= 0 || $offer_price >= $package['price']) {
            $app->telegram_api->sendMessage($app->chat_id, "Harga penawaran tidak valid. Harap masukkan angka yang lebih rendah dari harga asli.");
            return; // Tetap dalam state yang sama untuk input baru
        }

        // Simpan penawaran ke database
        $stmt = $app->pdo->prepare(
            "INSERT INTO offers (package_id, buyer_user_id, seller_user_id, offer_price, status) VALUES (?, ?, ?, ?, 'pending')"
        );
        $stmt->execute([$package['id'], $app->user['id'], $package['seller_user_id'], $offer_price]);
        $offer_id = $app->pdo->lastInsertId();

        // Reset state penawar
        $user_repo->setUserState($app->user['id'], null, null);

        // Kirim notifikasi ke penjual
        $buyer_username = $message['from']['username'] ?? $message['from']['first_name'];
        $price_formatted = "Rp " . number_format($package['price'], 0, ',', '.');
        $offer_price_formatted = "Rp " . number_format($offer_price, 0, ',', '.');

        $notification_text = "🔔 *Penawaran Baru*\n\n";
        $notification_text .= "Pengguna `{$buyer_username}` telah menawar item Anda `{$public_id}` (Harga: {$price_formatted}) dengan harga *{$offer_price_formatted}*.";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Setuju', 'callback_data' => "accept_offer_{$offer_id}"],
                    ['text' => '❌ Tolak', 'callback_data' => "reject_offer_{$offer_id}"]
                ]
            ]
        ];

        $app->telegram_api->sendMessage($package['seller_user_id'], $notification_text, 'Markdown', json_encode($keyboard));

        // Kirim konfirmasi ke penawar
        $app->telegram_api->sendMessage($app->chat_id, "✅ Penawaran Anda sebesar {$offer_price_formatted} telah dikirim ke penjual. Harap tunggu responsnya.");
    }
}
