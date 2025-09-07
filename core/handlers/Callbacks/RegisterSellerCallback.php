<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;
use TGBot\Database\UserRepository;
use Exception;

class RegisterSellerCallback implements CallbackCommandInterface
{
    // The `params` argument is not used here, but is required by the interface.
    public function execute(App $app, array $callback_query, string $params): void
    {
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);

        if (!empty($app->user['public_seller_id'])) {
            $app->telegram_api->answerCallbackQuery($callback_query['id'], 'Anda sudah terdaftar sebagai penjual.', true);
            $app->telegram_api->deleteMessage($callback_query['message']['chat']['id'], $callback_query['message']['message_id']);
            return;
        }

        try {
            $public_id = $user_repo->setPublicId($app->user['id']);
            $message = "Selamat! Anda berhasil terdaftar sebagai penjual.\n\nID Penjual Publik Anda adalah: *" . $app->telegram_api->escapeMarkdown($public_id) . "*\n\nSekarang Anda dapat menggunakan perintah /sell ulang ke media yang anda ingin jual.";
            $app->telegram_api->sendMessage($app->chat_id, $message, 'Markdown');
            $app->telegram_api->answerCallbackQuery($callback_query['id']);
            $app->telegram_api->deleteMessage($callback_query['message']['chat']['id'], $callback_query['message']['message_id']);
        } catch (Exception $e) {
            $app->telegram_api->answerCallbackQuery($callback_query['id'], 'Terjadi kesalahan saat mendaftar. Coba lagi.', true);
            app_log("Gagal mendaftarkan penjual: " . $e->getMessage(), 'error');
        }
    }
}
