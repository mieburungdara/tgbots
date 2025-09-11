<?php

namespace TGBot\Handlers\Commands;

use TGBot\App;
use TGBot\Database\MediaPackageRepository;
use TGBot\Database\SaleRepository;
use TGBot\Database\SubscriptionRepository;
use TGBot\Database\FeatureChannelRepository;
use TGBot\Database\UserRepository;
use TGBot\Database\PackageViewRepository;

class KontenCommand implements CommandInterface
{
    public function execute(App $app, array $message, array $parts): void
    {
        $package_repo = new MediaPackageRepository($app->pdo);
        $sale_repo = new SaleRepository($app->pdo);
        $subscription_repo = new SubscriptionRepository($app->pdo);
        $feature_channel_repo = new FeatureChannelRepository($app->pdo);
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);
        $view_repo = new PackageViewRepository($app->pdo);

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

        // 1. Generate media summary and create the base description
        $media_files = $package_repo->getFilesByPackageId($package_id);
        $media_summary_str = $this->generateMediaSummary($media_files);
        $description = $package['description'];
        if (!empty($media_summary_str)) {
            $description = $media_summary_str . "\n" . $description;
        }

        // 2. Determine user type and prepare caption and keyboard
        $is_seller = ($package['seller_user_id'] == $app->user['id']);
        $caption = '';
        $keyboard = [];

        if ($is_seller) {
            try {
                // Existing report data
                $sales_count = $sale_repo->countSalesForPackage($package_id);
                $total_earnings = $sales_count * $package['price'];
                $price_formatted = "Rp " . number_format($package['price'], 0, ',', '.');
                $total_earnings_formatted = "Rp " . number_format($total_earnings, 0, ',', '.');
                $created_at = date('d M Y H:i', strtotime($package['created_at']));

                // New Analytics Data
                $views_count = $view_repo->countViews($package_id);
                $offers_count = $package_repo->countOffers($package_id);
                $conversion_rate = ($views_count > 0) ? round(($sales_count / $views_count) * 100, 2) : 0;

                $report = "✨ *Laporan Konten*\n\n" .
                    "ID Konten: `{$package['public_id']}`\n" .
                    "Deskripsi: {$description}\n" .
                    "Harga: {$price_formatted}\n" .
                    "Status: {$package['status']}\n" .
                    "Tanggal Dibuat: {$created_at}\n\n" .
                    "📈 *Statistik Penjualan*\n" .
                    "Jumlah Terjual: {$sales_count} kali\n" .
                    "Total Pendapatan: {$total_earnings_formatted}\n\n" .
                    "📊 *Analitik Pengguna*\n" .
                    "Dilihat oleh: {$views_count} pengguna unik\n" .
                    "Upaya tawar: {$offers_count} kali\n" .
                    "Tingkat Konversi: {$conversion_rate}%";

                $caption = $report;

                $keyboard_buttons = [[['text' => 'Lihat Selengkapnya 📂', 'callback_data' => "view_page_{$package['public_id']}_0"]]];
                $sales_channels = $feature_channel_repo->findAllByOwnerAndFeature($app->user['id'], 'sell');
                if (!empty($sales_channels)) {
                    $keyboard_buttons[0][] = ['text' => '📢 Post ke Channel', 'callback_data' => "post_channel_{$package['public_id']}"];
                } else {
                    $caption .= "\n\n*Anda belum mendaftarkan channel pribadi, silahkan daftarkan channel pribadi anda untuk berjualan di panel member pada /login*";
                }
                $keyboard = ['inline_keyboard' => $keyboard_buttons];

            } catch (
                app_log("Gagal membuat laporan konten untuk seller: " . $e->getMessage(), 'error', ['package_id' => $package_id]);
                $app->telegram_api->sendMessage($app->chat_id, "Terjadi kesalahan saat mengambil data laporan. Silakan coba lagi nanti.");
                return; // Stop execution if report fails
            }
        } else {
            $caption = $description;
            $is_admin = ($app->user['role'] === 'Admin');
            $has_purchased = $sale_repo->hasUserPurchased($package['id'], $app->user['id']);
            $has_subscribed = $subscription_repo->hasActiveSubscription($app->user['id'], $package['seller_user_id']);
            $has_access = $is_admin || $has_purchased || $has_subscribed;

            if ($has_access) {
                $keyboard_buttons = [[['text' => 'Lihat Selengkapnya 📂', 'callback_data' => "view_page_{$package['public_id']}_0"]]];
                $keyboard = ['inline_keyboard' => $keyboard_buttons];
            } elseif ($package['status'] === 'available') {
                $keyboard = ['inline_keyboard' => [[]]]; // Initialize empty row

                // Add one-time purchase button
                $price_formatted = "Rp " . number_format($package['price'], 0, ',', '.');
                $keyboard['inline_keyboard'][0][] = ['text' => "Beli ({$price_formatted}) 🛒", 'callback_data' => "buy_{$package['public_id']}"];
                $keyboard['inline_keyboard'][0][] = ['text' => '🎁 Hadiahkan', 'callback_data' => "gift_{$package['public_id']}"];

                // Add subscription button if seller offers it
                $seller = $user_repo->findUserByTelegramId($package['seller_user_id']);
                if ($seller && !empty($seller['subscription_price'])) {
                    $sub_price_formatted = "Rp " . number_format($seller['subscription_price'], 0, ',', '.');
                    $keyboard['inline_keyboard'][0][] = ['text' => "Langganan ({$sub_price_formatted}/bln) ⭐", 'callback_data' => "subscribe_{$package['seller_user_id']}"];
                }
            }
        }

        // Log the view event for analytics before showing the content
        if (!$is_seller) {
            $view_repo->logView($package_id, $app->user['id']);
        }

        $thumbnail = $package_repo->getThumbnailFile($package_id);
        if (!$thumbnail) {
            $app->telegram_api->sendMessage($app->chat_id, "Konten ini tidak memiliki media yang dapat ditampilkan atau semua media di dalamnya rusak. Silakan hubungi admin.");
            \app_log("Gagal menampilkan konten: Tidak ditemukan thumbnail yang valid untuk package_id: {$package_id}", 'warning', ['package' => $package]);
            return;
        }

        $reply_markup = !empty($keyboard) ? json_encode($keyboard) : null;
        $app->telegram_api->copyMessage($app->chat_id, $thumbnail['storage_channel_id'], $thumbnail['storage_message_id'], $caption, 'Markdown', $reply_markup);
    }

    private function generateMediaSummary(array $media_files): string
    {
        if (empty($media_files)) {
            return '';
        }

        $type_counts = [];
        $total_size = 0;
        foreach ($media_files as $file) {
            $type = $file['type'];
            $type_counts[$type] = ($type_counts[$type] ?? 0) + 1;
            $total_size += $file['file_size'];
        }

        $summary_parts = [];
        $order = ['photo', 'video', 'document', 'audio'];

        // Combine ordered types with any other existing types to maintain order
        $all_types = array_keys($type_counts);
        $sorted_types = array_unique(array_merge($order, $all_types));

        foreach ($sorted_types as $type) {
            if (isset($type_counts[$type])) {
                $initial = strtoupper(substr($type, 0, 1));
                $summary_parts[] = $type_counts[$type] . $initial;
            }
        }

        $size_in_mb = round($total_size / 1024 / 1024, 2);
        return '[' . implode('', $summary_parts) . ' ' . $size_in_mb . 'MB]';
    }
}
