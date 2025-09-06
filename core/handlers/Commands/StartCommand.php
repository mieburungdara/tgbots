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
        $package_repo = new MediaPackageRepository($app->pdo);
        $sale_repo = new SaleRepository($app->pdo);
        $feature_channel_repo = new FeatureChannelRepository($app->pdo);

        if (count($parts) > 1 && strpos($parts[1], 'package_') === 0) {
            $public_id = substr($parts[1], strlen('package_'));
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
                $response = "Anda adalah pemilik konten ini. Anda dapat melihat atau mem-postingnya ke channel.";
                $keyboard_buttons = [[['text' => 'Lihat Selengkapnya 📂', 'callback_data' => "view_page_{$public_id}_0"]]];
                $sales_channels = $feature_channel_repo->findAllByOwnerAndFeature($telegram_user_id, 'sell');
                if (!empty($sales_channels)) {
                    $keyboard_buttons[0][] = ['text' => '📢 Post ke Channel', 'callback_data' => "post_channel_{$public_id}"];
                }
                $keyboard = ['inline_keyboard' => $keyboard_buttons];
                $app->telegram_api->sendMessage($app->chat_id, $response, 'Markdown', json_encode($keyboard));
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
                $caption = "Anda tertarik dengan item berikut:\n\n*Deskripsi:* {$escaped_description}\n*Harga:* {$price_formatted}\n\nSaldo Anda saat ini: {$balance_formatted}.";
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

        } else {
            $welcome_message = "👋 *Selamat Datang di Bot Marketplace!* 🤖\n\n" .
                               "Gunakan perintah `/help` untuk melihat daftar perintah yang tersedia.";
            $app->telegram_api->sendMessage($app->chat_id, $welcome_message, 'Markdown');
        }
    }
}
