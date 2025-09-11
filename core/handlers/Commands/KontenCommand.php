<?php

namespace TGBot\Handlers\Commands;

use TGBot\App;
use TGBot\Database\MediaPackageRepository;
use TGBot\Database\SaleRepository;
use TGBot\Database\SubscriptionRepository;
use TGBot\Database\FeatureChannelRepository;
use TGBot\Database\UserRepository;
use TGBot\Database\PackageViewRepository;
use TGBot\Logger;
use Exception;

class KontenCommand implements CommandInterface
{
    private MediaPackageRepository $packageRepo;
    private SaleRepository $saleRepo;
    private SubscriptionRepository $subscriptionRepo;
    private FeatureChannelRepository $featureChannelRepo;
    private UserRepository $userRepo;
    private PackageViewRepository $viewRepo;
    private Logger $logger;

    public function __construct(
        MediaPackageRepository $packageRepo,
        SaleRepository $saleRepo,
        SubscriptionRepository $subscriptionRepo,
        FeatureChannelRepository $featureChannelRepo,
        UserRepository $userRepo,
        PackageViewRepository $viewRepo,
        Logger $logger
    ) {
        $this->packageRepo = $packageRepo;
        $this->saleRepo = $saleRepo;
        $this->subscriptionRepo = $subscriptionRepo;
        $this->featureChannelRepo = $featureChannelRepo;
        $this->userRepo = $userRepo;
        $this->viewRepo = $viewRepo;
        $this->logger = $logger;
    }

    public function execute(App $app, array $message, array $parts): void
    {

        if (count($parts) !== 2) {
            $app->telegram_api->sendMessage($app->chat_id, "Format perintah salah. Gunakan: /konten <ID Konten>");
            return;
        }

        $public_id = $parts[1];
        $package = $this->packageRepo->findByPublicId($public_id);

        if (!$package) {
            $app->telegram_api->sendMessage($app->chat_id, "Konten dengan ID `{$public_id}` tidak ditemukan.", 'Markdown');
            return;
        }
        $package_id = $package['id'];

        // 1. Generate media summary and create the base description
        $media_files = $this->packageRepo->getFilesByPackageId($package_id);
        $media_summary_str = $this->generateMediaSummary($media_files);
        $description = $package['description'];
        if (!empty($media_summary_str)) {
            $description = $media_summary_str . "\n" . $description;
        }

        // 2. Determine user type and prepare caption and keyboard
        $is_seller = ($package['seller_user_id'] == $app->user['id']);
        $caption = '';
        $keyboard = [];

        try {
            if ($is_seller) {
                list($caption, $keyboard) = $this->handleSellerView($app, $package, $description);
            } else {
                $is_admin = ($app->user['role'] === 'Admin');
                $has_purchased = $this->saleRepo->hasUserPurchased($package['id'], $app->user['id']);
                $has_subscribed = $this->subscriptionRepo->hasActiveSubscription($app->user['id'], $package['seller_user_id']);
                $has_access = $is_admin || $has_purchased || $has_subscribed;

                if ($has_access) {
                    list($caption, $keyboard) = $this->handlePurchasedOrAdminView($app, $package, $description);
                } else {
                    list($caption, $keyboard) = $this->handleVisitorView($app, $package, $description);
                    if ($caption === null) { // Indicates that visitor view decided to stop execution
                        return;
                    }
                }
            }
        } catch (Exception $e) {
            $this->logger->error("Gagal memproses tampilan konten: " . $e->getMessage(), ['package_id' => $package['id'], 'user_id' => $app->user['id']]);
            $app->telegram_api->sendMessage($app->chat_id, "Terjadi kesalahan saat menyiapkan tampilan konten. Silakan coba lagi nanti.");
            return;
        }

        // Log the view event for analytics before showing the content
        if (!$is_seller) {
            $this->viewRepo->logView($package_id, $app->user['id']);
        }

        $thumbnail = $this->packageRepo->getThumbnailFile($package_id);
        if (!$thumbnail) {
            $app->telegram_api->sendMessage($app->chat_id, "Konten ini tidak memiliki media yang dapat ditampilkan atau semua media di dalamnya rusak. Silakan hubungi admin.");
            $this->logger->warning("Gagal menampilkan konten: Tidak ditemukan thumbnail yang valid untuk package_id: {$package_id}", ['package' => $package]);
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

    private function generateSellerReport(App $app, array $package, string $description): array
    {
        $cache_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgbots_cache';
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0777, true);
        }
        $cache_key = 'seller_report_' . $package['id'];
        $cache_file = $cache_dir . DIRECTORY_SEPARATOR . $cache_key . '.json';
        $cache_duration = 300; // 5 minutes

        // Try to load from cache
        if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_duration)) {
            $cached_data = json_decode(file_get_contents($cache_file), true);
            if ($cached_data) {
                return $cached_data;
            }
        }

        

        try {
            // Existing report data
            $analytics = $this->saleRepo->getAnalyticsForPackage($package['id']);
            $sales_count = $analytics['sales_count'];
            $views_count = $analytics['views_count'];
            $offers_count = $analytics['offers_count'];

            $total_earnings = $sales_count * $package['price'];
            $price_formatted = "Rp " . number_format($package['price'], 0, ',', '.');
            $total_earnings_formatted = "Rp " . number_format($total_earnings, 0, ',', '.');
            $created_at = date('d M Y H:i', strtotime($package['created_at']));

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
            $sales_channels = $this->featureChannelRepo->findAllByOwnerAndFeature($app->user['id'], 'sell');
            if (!empty($sales_channels)) {
                $keyboard_buttons[0][] = ['text' => '📢 Post ke Channel', 'callback_data' => "post_channel_{$package['public_id']}"];
            } else {
                $caption .= "\n\n*Anda belum mendaftarkan channel pribadi, silahkan daftarkan channel pribadi anda untuk berjualan di panel member pada /login*";
            }
            // Add "Promosikan Konten" button
            $keyboard_buttons[] = [['text' => '🚀 Promosikan Konten', 'callback_data' => "promote_content_{$package['public_id']}"]];

            $keyboard = ['inline_keyboard' => $keyboard_buttons];

            $data_to_cache = [$caption, $keyboard];
            file_put_contents($cache_file, json_encode($data_to_cache));

            return $data_to_cache;
        } catch (Exception $e) {
            $this->logger->error("Gagal membuat laporan konten untuk seller: " . $e->getMessage(), ['package_id' => $package['id']]);
            $app->telegram_api->sendMessage($app->chat_id, "Terjadi kesalahan saat mengambil data laporan. Silakan coba lagi nanti.");
            return [null, null]; // Indicate error
        }
    }

    private function handleSellerView(App $app, array $package, string $description): array
    {
        return $this->generateSellerReport($app, $package, $description);
    }

    private function handlePurchasedOrAdminView(App $app, array $package, string $description): array
    {
        $caption = $description;
        $keyboard_buttons = [[['text' => 'Lihat Selengkapnya 📂', 'callback_data' => "view_page_{$package['public_id']}_0"]]];
        $keyboard = ['inline_keyboard' => $keyboard_buttons];
        return [$caption, $keyboard];
    }

    private function handleVisitorView(App $app, array $package, string $description): array
    {
        

        $caption = $description;
        $keyboard = [];

        if ($package['status'] === 'available') {
            $keyboard = ['inline_keyboard' => [[]]]; // Initialize empty row

            // Add one-time purchase button
            $price_formatted = "Rp " . number_format($package['price'], 0, ',', '.');
            $keyboard['inline_keyboard'][0][] = ['text' => "Beli ({$price_formatted}) 🛒", 'callback_data' => "buy_{$package['public_id']}"];
            $keyboard['inline_keyboard'][0][] = ['text' => '🎁 Hadiahkan', 'callback_data' => "gift_{$package['public_id']}"];

            // Add subscription button if seller offers it and user is not already subscribed
            $seller = $this->userRepo->findUserByTelegramId($package['seller_user_id']);
            $has_subscribed = $this->subscriptionRepo->hasActiveSubscription($app->user['id'], $package['seller_user_id']);
            if ($seller && !empty($seller['subscription_price']) && !$has_subscribed) {
                $sub_price_formatted = "Rp " . number_format($seller['subscription_price'], 0, ',', '.');
                $keyboard['inline_keyboard'][0][] = ['text' => "Langganan ({$sub_price_formatted}/bln) ⭐", 'callback_data' => "subscribe_{$package['seller_user_id']}"];
            }
            // Add "Tanya Penjual" button
            $keyboard['inline_keyboard'][] = [['text' => '💬 Tanya Penjual', 'callback_data' => "ask_seller_{$package['public_id']}_{$package['seller_user_id']}"]];

        } else {
            // Handle other package statuses for visitors
            $status_message = '';
            switch ($package['status']) {
                case 'sold':
                    $status_message = "Konten ini sudah terjual.";
                    break;
                case 'retracted':
                    $status_message = "Konten ini telah ditarik oleh penjual.";
                    break;
                case 'pending':
                    $status_message = "Konten ini masih dalam proses moderasi.";
                    break;
                case 'deleted':
                    $status_message = "Konten ini telah dihapus.";
                    break;
                default:
                        $status_message = "Konten ini tidak tersedia.";
                        break;
                }
                $app->telegram_api->sendMessage($app->chat_id, "⚠️ {$status_message}");
                return [null, null]; // Indicate that execution should stop
            }
        return [$caption, $keyboard];
    }
}
