<?php

namespace TGBot\Handlers\States;

use TGBot\App;
use TGBot\Database\UserRepository;
use TGBot\Database\MediaPackageRepository;
use TGBot\Handlers\States\AwaitingGiftRecipientState;

class AwaitingGiftTypeSelectionState implements StateInterface
{
    public function handle(App $app, array $message, array $state_context): void
    {
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);
        $package_repo = new MediaPackageRepository($app->pdo);

        $package_id = $state_context['package_id'];
        $package = $package_repo->find($package_id);

        if (!$package) {
            $app->telegram_api->sendMessage($app->chat_id, 'Error: Paket tidak ditemukan.');
            $user_repo->setUserState($app->user['id'], null, null);
            return;
        }

        $callback_data = $message['callback_query']['data'] ?? '';
        $is_anonymous = false;

        if (strpos($callback_data, 'select_gift_type_anonymous_') === 0) {
            $is_anonymous = true;
        } elseif (strpos($callback_data, 'select_gift_type_named_') === 0) {
            $is_anonymous = false;
        } else {
            $app->telegram_api->sendMessage($app->chat_id, 'Pilihan tidak valid. Silakan pilih Kirim Anonim atau Kirim dengan Nama.');
            return;
        }

        // Update state context with anonymity choice
        $state_context['is_anonymous'] = $is_anonymous;
        $user_repo->setUserState($app->user['id'], 'awaiting_gift_recipient', $state_context);

        $message_text = "🎁 Anda akan menghadiahkan paket `{$package['public_id']}`.\n\n";
        $message_text .= "Silakan masukkan `@username` teman yang ingin Anda beri hadiah.";

        $keyboard = [
            ['text' => 'Batalkan Hadiah ❌', 'callback_data' => "cancel_gift_{$package['public_id']}"]
        ];
        $reply_markup = json_encode(['inline_keyboard' => [$keyboard]]);

        $app->telegram_api->editMessageText(
            $app->chat_id,
            $message['callback_query']['message']['message_id'],
            $message_text,
            'Markdown',
            $reply_markup
        );
        $app->telegram_api->answerCallbackQuery($message['callback_query']['id'], 'Masukkan username penerima');
    }
}
