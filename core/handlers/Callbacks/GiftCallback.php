<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;
use TGBot\Database\UserRepository;
use TGBot\Database\MediaPackageRepository;

class GiftCallback implements CallbackCommandInterface
{
    public function execute(App $app, array $callback_query, string $public_id): void
    {
        $package_repo = new MediaPackageRepository($app->pdo);
        $package = $package_repo->findByPublicId($public_id);

        if (!$package) {
            $app->telegram_api->answerCallbackQuery($callback_query['id'], 'Error: Paket tidak ditemukan.', true);
            return;
        }

        $user_repo = new UserRepository($app->pdo, $app->bot['id']);
        $state_context = ['package_id' => $package['id']];
        $user_repo->setUserState($app->user['id'], 'awaiting_gift_type_selection', $state_context);

        $message = "🎁 Anda akan menghadiahkan paket `{$package['public_id']}`.\n\n";
        $message .= "Bagaimana Anda ingin mengirim hadiah ini?";

        $keyboard = [
            [['text' => 'Kirim Anonim 👻', 'callback_data' => "select_gift_type_anonymous_{$public_id}"]],
            [['text' => 'Kirim dengan Nama 👋', 'callback_data' => "select_gift_type_named_{$public_id}"]],
            [['text' => 'Batalkan Hadiah ❌', 'callback_data' => "cancel_gift_{$public_id}"]]
        ];
        $reply_markup = json_encode(['inline_keyboard' => $keyboard]);

        $app->telegram_api->editMessageText($app->chat_id, $callback_query['message']['message_id'], $message, 'Markdown', $reply_markup);
        $app->telegram_api->answerCallbackQuery($callback_query['id'], 'Pilih tipe pengiriman hadiah');
    }
}
