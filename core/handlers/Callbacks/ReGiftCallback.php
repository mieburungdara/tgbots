<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;
use TGBot\Database\MediaPackageRepository;
use TGBot\Database\SaleRepository;
use TGBot\Database\UserRepository;

class ReGiftCallback implements CallbackCommandInterface
{
    public function execute(App $app, array $callback_query, string $public_id): void
    {
        $package_repo = new MediaPackageRepository($app->pdo);
        $sale_repo = new SaleRepository($app->pdo);
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);

        $package = $package_repo->findByPublicId($public_id);
        if (!$package) {
            $app->telegram_api->answerCallbackQuery($callback_query['id'], 'Error: Paket tidak ditemukan.', true);
            return;
        }

        $sale = $sale_repo->getSaleDetails($package['id'], $app->user['id']);

        if (!$sale || $sale['granted_to_user_id'] != $app->user['id'] || $sale['buyer_user_id'] == $app->user['id']) {
            $app->telegram_api->answerCallbackQuery($callback_query['id'], '⚠️ Anda tidak memiliki hadiah ini untuk dihadiahkan ulang.', true);
            return;
        }

        if ($sale['claimed_at'] !== null) {
            $app->telegram_api->answerCallbackQuery($callback_query['id'], '🎁 Hadiah ini sudah diklaim dan tidak dapat dihadiahkan ulang.', true);
            return;
        }

        if ($sale['expires_at'] !== null && strtotime($sale['expires_at']) < time()) {
            $app->telegram_api->answerCallbackQuery($callback_query['id'], '⚠️ Hadiah ini sudah kadaluarsa dan tidak dapat dihadiahkan ulang.', true);
            return;
        }

        // Set user state to await new recipient for re-gifting
        $state_context = [
            'package_id' => $package['id'],
            'sale_id' => $sale['id']
        ];
        $user_repo->setUserState($app->user['id'], 'awaiting_regift_recipient', $state_context);

        $message = "🔄 Anda akan menghadiahkan ulang paket `{$package['public_id']}`.\n\n";
        $message .= "Silakan masukkan `@username` teman yang ingin Anda beri hadiah ulang.";

        $keyboard = [
            ['text' => 'Batalkan Hadiah Ulang ❌', 'callback_data' => "cancel_regift_{$public_id}"]
        ];
        $reply_markup = json_encode(['inline_keyboard' => [$keyboard]]);

        $app->telegram_api->editMessageText(
            $app->chat_id,
            $callback_query['message']['message_id'],
            $message,
            'Markdown',
            $reply_markup
        );
        $app->telegram_api->answerCallbackQuery($callback_query['id'], 'Masukkan username penerima hadiah ulang');
    }
}
