<?php

namespace TGBot\Handlers\Commands;

use TGBot\App;
use TGBot\Database\UserRepository;

class SetPriceCommand implements CommandInterface
{
    public function execute(App $app, array $message, array $parts): void
    {
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);

        if (count($parts) < 2) {
            $current_price = $app->user['subscription_price'];
            $price_info = $current_price > 0 ? "Harga langganan Anda saat ini adalah Rp " . number_format($current_price, 0, ',', '.')."/bulan." : "Anda saat ini belum mengaktifkan fitur langganan.";

            $reply_text = "Gunakan format: `/setprice <harga>` untuk mengatur harga langganan bulanan Anda.\n";
            $reply_text .= "Contoh: `/setprice 50000`\n";
            $reply_text .= "Untuk menonaktifkan, gunakan: `/setprice 0`\n\n";
            $reply_text .= $price_info;
            $app->telegram_api->sendMessage($app->chat_id, $reply_text, 'Markdown');
            return;
        }

        $price = filter_var($parts[1], FILTER_VALIDATE_INT);

        if ($price === false || $price < 0) {
            $app->telegram_api->sendMessage($app->chat_id, "Harga tidak valid. Harap masukkan angka bulat positif, atau 0 untuk menonaktifkan.");
            return;
        }

        try {
            $user_repo->setSubscriptionPrice($app->user['id'], $price > 0 ? $price : null);
            if ($price > 0) {
                $price_formatted = number_format($price, 0, ',', '.');
                $reply_text = "✅ Harga langganan bulanan Anda berhasil diatur ke *Rp {$price_formatted}*. Pengguna sekarang dapat berlangganan channel Anda.";
            } else {
                $reply_text = "✅ Fitur langganan telah dinonaktifkan.";
            }
            $app->telegram_api->sendMessage($app->chat_id, $reply_text, 'Markdown');
        } catch (\Exception $e) {
            $app->telegram_api->sendMessage($app->chat_id, "Terjadi kesalahan saat mengatur harga.");
            app_log("Failed to set subscription price for user {$app->user['id']}: " . $e->getMessage(), 'error');
        }
    }
}
