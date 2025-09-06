<?php

namespace TGBot\Handlers\Commands;

use TGBot\App;
use TGBot\Database\MediaPackageRepository;
use TGBot\Database\SaleRepository;
use TGBot\Database\FeatureChannelRepository;

class KontenCommand implements CommandInterface
{
    public function execute(App $app, array $message, array $parts): void
    {
        $package_repo = new MediaPackageRepository($app->pdo);
        $sale_repo = new SaleRepository($app->pdo);
        $feature_channel_repo = new FeatureChannelRepository($app->pdo);

        if (count($parts) !== 2) {
            $app->telegram_api->sendMessage($app->chat_id, "Format perintah salah. Gunakan: /konten <ID Konten>");
            return;
        }

        $public_id = $parts[1];
        $package = $package_repo->findByPublicId($public_id);

        if (!$package) {
            $app->telegram_api->sendMessage($app->chat_id, "Konten dengan ID `{$public_id}` tidak ditemukan.", 'Markdown');
            return;
        }
        $package_id = $package['id'];

        $thumbnail = $package_repo->getThumbnailFile($package_id);

        if (!$thumbnail) {
            $app->telegram_api->sendMessage($app->chat_id, "Konten ini tidak memiliki media yang dapat ditampilkan atau semua media di dalamnya rusak. Silakan hubungi admin.");
            \app_log("Gagal menampilkan konten: Tidak ditemukan thumbnail yang valid untuk package_id: {$package_id}", 'warning', ['package' => $package]);
            return;
        }

        $is_admin = ($app->user['role'] === 'Admin');
        $is_seller = ($package['seller_user_id'] == $app->user['id']);
        $has_purchased = $sale_repo->hasUserPurchased($package_id, $app->user['id']);
        $has_access = $is_admin || $is_seller || $has_purchased;

        $keyboard = [];
        if ($has_access) {
            $keyboard_buttons = [[['text' => 'Lihat Selengkapnya 📂', 'callback_data' => "view_page_{$package['public_id']}_0"]]];
            if ($is_seller) {
                $sales_channels = $feature_channel_repo->findAllByOwnerAndFeature($app->user['id'], 'sell');
                if (!empty($sales_channels)) {
                    $keyboard_buttons[0][] = ['text' => '📢 Post ke Channel', 'callback_data' => "post_channel_{$package['public_id']}"];
                }
            }
            $keyboard = ['inline_keyboard' => $keyboard_buttons];
        } elseif ($package['status'] === 'available') {
            $price_formatted = "Rp " . number_format($package['price'], 0, ',', '.');
            $keyboard = ['inline_keyboard' => [[['text' => "Beli Konten Ini ({$price_formatted}) 🛒", 'callback_data' => "buy_{$package['public_id']}"]]]];
        }

        $caption = $package['description'];
        $reply_markup = !empty($keyboard) ? json_encode($keyboard) : null;

        $app->telegram_api->copyMessage($app->chat_id, $thumbnail['storage_channel_id'], $thumbnail['storage_message_id'], $caption, null, $reply_markup);
    }
}
