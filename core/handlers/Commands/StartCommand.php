<?php

namespace TGBot\Handlers\Commands;

use TGBot\App;
use TGBot\Database\MediaPackageRepository;
use TGBot\Database\SaleRepository;
use TGBot\Database\FeatureChannelRepository;

class StartCommand implements CommandInterface
{
    public function execute(App $app, array $message, array $parts): void
    {
        if (count($parts) > 1 && strpos($parts[1], 'package_') === 0) {
            $public_id = substr($parts[1], strlen('package_'));
            $this->handleStartWithPayload($app, $message, $public_id);
        }
        else {
            $welcome_message = "👋 *Selamat Datang di Bot Marketplace!* 🤖\n\n" .
                               "Gunakan perintah `/help` untuk melihat daftar perintah yang tersedia.";
            $app->telegram_api->sendMessage($app->chat_id, $welcome_message, 'Markdown');
        }
    }

    private function handleStartWithPayload(App $app, array $message, string $public_id): void
    {
        $package_repo = new MediaPackageRepository($app->pdo);
        $sale_repo = new SaleRepository($app->pdo);
        $feature_channel_repo = new FeatureChannelRepository($app->pdo);

        $package = $package_repo->findByPublicId($public_id);

        if (!$package) {
            $app->telegram_api->sendMessage($app->chat_id, "Maaf, item ini tidak ditemukan.");
            return;
        }

        $package_id = $package['id'];
        $telegram_user_id = $app->user['id'];

        $is_seller = ($package['seller_user_id'] == $telegram_user_id);
        $has_purchased = $sale_repo->hasUserPurchased($package_id, $telegram_user_id);

        if ($is_seller) {
            $sales_count = $sale_repo->countSalesForPackage($package_id);
            $total_profit = $sales_count * $package['price'];

            $response_text = sprintf(
                "✨ <b>Detail Konten Anda</b> ✨\n\n<b>ID Konten:</b> <code>%s</code>\n\n<b>Deskripsi:</b>\n<blockquote expandable>%s</blockquote>\n\n<b>Harga:</b> Rp %s\n<b>Terjual:</b> %d kali\n<b>Total Keuntungan:</b> Rp %s\n\n",
                $public_id,
                $app->telegram_api->escapeHtml($package['description']),
                number_format($package['price'], 0, ',', '.'),
                $sales_count,
                number_format($total_profit, 0, ',', '.')
            );

            $keyboard_buttons = [[['text' => 'Lihat Selengkapnya 📂', 'callback_data' => "view_page_{$public_id}_0"]]];
            $sales_channels = $feature_channel_repo->findAllByOwnerAndFeature($telegram_user_id, 'sell');
            if (!empty($sales_channels)) {
                $keyboard_buttons[0][] = ['text' => '📢 Post ke Channel', 'callback_data' => "post_channel_{$public_id}"];
            }
            $keyboard = ['inline_keyboard' => $keyboard_buttons];

            $thumbnail = $package_repo->getThumbnailFile($package_id);

            if ($thumbnail && !empty($thumbnail['storage_channel_id']) && !empty($thumbnail['storage_message_id'])) {
                $app->telegram_api->copyMessage($app->chat_id, $thumbnail['storage_channel_id'], $thumbnail['storage_message_id'], $response_text, 'HTML', json_encode($keyboard));
            } else {
                $app->telegram_api->sendMessage($app->chat_id, $response_text, 'HTML', json_encode($keyboard));
            }
            
            return;
        }

        if ($has_purchased) {
            $response = "Anda sudah memiliki item ini. Klik tombol di bawah untuk melihatnya.";
            $keyboard = ['inline_keyboard' => [[['text' => 'Lihat Konten 📂', 'callback_data' => "view_page_{$public_id}_0"]]]];
            $app->telegram_api->sendMessage($app->chat_id, $response, 'Markdown', json_encode($keyboard));
            return;
        }

        if ($package['status'] === 'available') {
            $price_formatted = "Rp " . number_format($package['price'], 0, ',', '.');
            $balance_formatted = "Rp " . number_format($app->user['balance'], 0, ',', '.');
            $escaped_description = $app->telegram_api->escapeMarkdown($package['description']);
            $caption = "Anda tertarik dengan item berikut:

*Deskripsi:* {$escaped_description}
*Harga:* {$price_formatted}

Saldo Anda saat ini: {$balance_formatted}.";
            $keyboard = ['inline_keyboard' => [[['text' => "Beli Sekarang ({$price_formatted})", 'callback_data' => "buy_{$public_id}"]]]];
            $reply_markup = json_encode($keyboard);

            $thumbnail = $package_repo->getThumbnailFile($package_id);

            if ($thumbnail && !empty($thumbnail['storage_channel_id']) && !empty($thumbnail['storage_message_id'])) {
                $app->telegram_api->copyMessage($app->chat_id, $thumbnail['storage_channel_id'], $thumbnail['storage_message_id'], $caption, 'Markdown', $reply_markup);
            } else {
                $app->telegram_api->sendMessage($app->chat_id, $caption, 'Markdown', $reply_markup);
            }
            return;
        }

        $app->telegram_api->sendMessage($app->chat_id, "Maaf, item ini sudah tidak tersedia.");
    }
}
