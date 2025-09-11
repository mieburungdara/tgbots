<?php

namespace TGBot\Handlers\States;

use TGBot\App;
use TGBot\Database\UserRepository;
use TGBot\Database\MediaPackageRepository;
use TGBot\Database\SaleRepository;
use Exception;

class AwaitingRegiftRecipientState implements StateInterface
{
    public function handle(App $app, array $message, array $state_context): void
    {
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);
        $package_repo = new MediaPackageRepository($app->pdo);
        $sale_repo = new SaleRepository($app->pdo);

        $username = ltrim($message['text'], '@');
        $package_id = $state_context['package_id'];
        $original_sale_id = $state_context['sale_id'];

        try {
            // 1. Find recipient by username
            $recipient = $user_repo->findUserByUsername($username);
            if (!$recipient) {
                throw new Exception("Pengguna dengan username `@{$username}` tidak ditemukan di database bot ini.");
            }

            // 2. Validate that recipient has interacted with this bot before
            if (!$user_repo->hasUserInteractedWithBot($recipient['id'], $app->bot['id'])) {
                throw new Exception("Pengguna `@{$username}` belum pernah berinteraksi dengan bot ini, sehingga hadiah ulang tidak dapat dikirim.");
            }

            $package = $package_repo->find($package_id);
            if (!$package) {
                throw new Exception("Paket yang akan dihadiahkan ulang tidak lagi valid.");
            }

            // 3. Get original sale details to ensure it's still valid for re-gifting
            $original_sale = $sale_repo->getSaleDetailsById($original_sale_id);
            if (!$original_sale || $original_sale['granted_to_user_id'] != $app->user['id'] || $original_sale['claimed_at'] !== null || ($original_sale['expires_at'] !== null && strtotime($original_sale['expires_at']) < time())) {
                throw new Exception("Hadiah ini tidak lagi valid untuk dihadiahkan ulang.");
            }

            // 4. Re-gift the sale
            if ($sale_repo->reGiftSale($original_sale_id, $recipient['id'])) {
                // 5. Notify new recipient
                $gift_message = "🎁 Selamat! Anda menerima hadiah konten `{$package['public_id']}` dari `{$app->user['first_name']}` (@{$app->user['username']}).\n\n";
                $gift_message .= "Deskripsi: *{$package['description']}*\n\n";
                $gift_message .= "Klik tombol di bawah untuk melihatnya.";
                $keyboard = ['inline_keyboard' => [[['text' => 'Lihat Hadiah 📂', 'callback_data' => "view_page_{$package['public_id']}_0"]]]];
                $app->telegram_api->sendMessage($recipient['id'], $gift_message, 'Markdown', json_encode($keyboard));

                // 6. Notify current user & clear state
                $app->telegram_api->sendMessage($app->chat_id, "✅ Hadiah berhasil dihadiahkan ulang ke `@{$username}`!");
                $user_repo->setUserState($app->user['id'], null, null);
            } else {
                throw new Exception("Gagal menghadiahkan ulang hadiah. Silakan coba lagi.");
            }

        } catch (Exception $e) {
            $app->telegram_api->sendMessage($app->chat_id, "⚠️ Gagal: " . $e->getMessage());
            // Don't clear state on failure, let them try another username
        }
    }
}
