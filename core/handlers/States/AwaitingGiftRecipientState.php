<?php

namespace TGBot\Handlers\States;

use TGBot\App;
use TGBot\Database\UserRepository;
use TGBot\Database\MediaPackageRepository;
use TGBot\Database\SaleRepository;
use Exception;

class AwaitingGiftRecipientState implements StateInterface
{
    public function handle(App $app, array $message, array $state_context): void
    {
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);
        $package_repo = new MediaPackageRepository($app->pdo);
        $sale_repo = new SaleRepository($app->pdo);

        $username = ltrim($message['text'], '@');
        $package_id = $state_context['package_id'];

        try {
            // 1. Find recipient by username
            $recipient = $user_repo->findUserByUsername($username);
            if (!$recipient) {
                throw new Exception("Pengguna dengan username `@{$username}` tidak ditemukan di database bot ini.");
            }

            // 2. Validate that recipient has interacted with this bot before
            if (!$user_repo->hasUserInteractedWithBot($recipient['id'], $app->bot['id'])) {
                throw new Exception("Pengguna `@{$username}` belum pernah berinteraksi dengan bot ini, sehingga hadiah tidak dapat dikirim.");
            }

            $package = $package_repo->find($package_id);
            if (!$package) {
                throw new Exception("Paket yang akan dihadiahkan tidak lagi valid.");
            }

            // 3. Check gifter's balance
            if ($app->user['balance'] < $package['price']) {
                throw new Exception("Saldo Anda tidak cukup untuk memberikan hadiah ini.");
            }

            // 4. Process the sale/gift
            $expires_at = date('Y-m-d H:i:s', strtotime('+7 days')); // Gift expires in 7 days
            $sale_repo->createSale(
                $package['id'], 
                $package['seller_user_id'], 
                $app->user['id'], // The buyer is the gifter
                $recipient['id'],   // The person granted access is the recipient
                $package['price'],
                $expires_at // Pass the expiration date
            );

            // 5. Notify recipient
            $gift_message = "🎁 Selamat! Anda menerima hadiah konten `{$package['public_id']}` dari `{$app->user['first_name']}` (@{$app->user['username']}).\n\n";
            $gift_message .= "Deskripsi: *{$package['description']}*\n\n";
            $gift_message .= "Klik tombol di bawah untuk melihatnya.";
            $keyboard = ['inline_keyboard' => [[['text' => 'Lihat Hadiah 📂', 'callback_data' => "view_page_{$package['public_id']}_0"]]]];
            $app->telegram_api->sendMessage($recipient['id'], $gift_message, 'Markdown', json_encode($keyboard));

            // 6. Notify gifter & clear state
            $app->telegram_api->sendMessage($app->chat_id, "✅ Hadiah berhasil dikirim ke `@{$username}`!");
            $user_repo->setUserState($app->user['id'], null, null);

        } catch (Exception $e) {
            $app->telegram_api->sendMessage($app->chat_id, "⚠️ Gagal: " . $e->getMessage());
            // Don't clear state on failure, let them try another username
        }
    }
}
