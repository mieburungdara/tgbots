<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;

class CancelSellCallback implements CallbackCommandInterface
{
    public function execute(App $app, array $callback_query, string $params): void
    {
        $chat_id = $callback_query['message']['chat']['id'];
        $message_id = $callback_query['message']['message_id'];

        try {
            // Hapus pesan konfirmasi
            $app->telegram_api->deleteMessage($chat_id, $message_id);
            // Jawab callback untuk menghilangkan status loading
            $app->telegram_api->answerCallbackQuery($callback_query['id'], 'Operasi dibatalkan.');
        } catch (\Exception $e) {
            // Jika gagal menghapus (misal, pesan sudah lama), cukup jawab callback
            $app->telegram_api->answerCallbackQuery($callback_query['id'], 'Operasi dibatalkan.');
            app_log("Gagal menghapus pesan saat membatalkan penjualan: " . $e->getMessage(), 'warning');
        }
    }
}
