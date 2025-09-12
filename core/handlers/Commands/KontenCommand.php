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
use TGBot\SellerReportGenerator;
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
    private SellerReportGenerator $sellerReportGenerator;

    public function __construct(
        MediaPackageRepository $packageRepo,
        SaleRepository $saleRepo,
        SubscriptionRepository $subscriptionRepo,
        FeatureChannelRepository $featureChannelRepo,
        UserRepository $userRepo,
        PackageViewRepository $viewRepo,
        Logger $logger,
        \TGBot\SellerReportGenerator $sellerReportGenerator
    ) {
        $this->packageRepo = $packageRepo;
        $this->saleRepo = $saleRepo;
        $this->subscriptionRepo = $subscriptionRepo;
        $this->featureChannelRepo = $featureChannelRepo;
        $this->userRepo = $userRepo;
        $this->viewRepo = $viewRepo;
        $this->logger = $logger;
        $this->sellerReportGenerator = $sellerReportGenerator;
    }

    public function execute(App $app, array $message, array $parts): void
    {
        $package = $this->validateCommandAndGetPackage($app, $parts);
        if (!$package) {
            return;
        }

        $description = $this->prepareDescription($package['id'], $package['description']);

        list($caption, $keyboard) = $this->handleUserView($app, $package, $description);
        if ($caption === null) { // Indicates that view handler decided to stop execution
            return;
        }

        $this->logPackageView($package['id'], $app->user['id']);

        $this->sendContentMessage($app, $package, $caption, $keyboard);
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

    private function handleSellerView(App $app, array $package, string $description): array
    {
        return $this->sellerReportGenerator->generateReport($app, $package, $description);
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
                return [null, null];
            }
        return [$caption, $keyboard];
    }

    private function validateCommandAndGetPackage(App $app, array $parts): ?array
    {
        if (count($parts) !== 2) {
            $app->telegram_api->sendMessage($app->chat_id, "Format perintah salah. Gunakan: /konten <ID Konten>");
            return null;
        }

        $public_id = $parts[1];
        $package = $this->packageRepo->findByPublicId($public_id);

        if (!$package) {
            $app->telegram_api->sendMessage($app->chat_id, "Konten dengan ID `{$public_id}` tidak ditemukan.", 'Markdown');
            return null;
        }
        return $package;
    }

    private function prepareDescription(int $package_id, string $initial_description): string
    {
        $media_files = $this->packageRepo->getFilesByPackageId($package_id);
        $media_summary_str = $this->generateMediaSummary($media_files);
        $description = $initial_description;
        if (!empty($media_summary_str)) {
            $description = $media_summary_str . "\n" . $description;
        }
        return $description;
    }

    private function handleUserView(App $app, array $package, string $description): array
    {
        $caption = '';
        $keyboard = [];

        try {
            $is_seller = ($package['seller_user_id'] == $app->user['id']);
            if ($is_seller) {
                list($caption, $keyboard) = $this->sellerReportGenerator->generateReport($app, $package, $description);
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
                        return [null, null];
                    }
                }
            }
        } catch (Exception $e) {
            $this->logger->error("Gagal memproses tampilan konten: " . $e->getMessage(), ['package_id' => $package['id'], 'user_id' => $app->user['id']]);
            $app->telegram_api->sendMessage($app->chat_id, "Terjadi kesalahan saat menyiapkan tampilan konten. Silakan coba lagi nanti.");
            return [null, null];
        }
        return [$caption, $keyboard];
    }

    private function logPackageView(int $package_id, int $user_id): void
    {
        // Check if the user is the seller. If so, do not log the view.
        $package = $this->packageRepo->find($package_id);
        if ($package && $package['seller_user_id'] != $user_id) {
            $this->viewRepo->logView($package_id, $user_id);
        }
    }

    private function sendContentMessage(App $app, array $package, string $caption, array $keyboard): void
    {
        $thumbnail = $this->packageRepo->getThumbnailFile($package['id']);
        if (!$thumbnail) {
            $app->telegram_api->sendMessage($app->chat_id, "Konten ini tidak memiliki media yang dapat ditampilkan atau semua media di dalamnya rusak. Silakan hubungi admin.");
            $this->logger->warning("Gagal menampilkan konten: Tidak ditemukan thumbnail yang valid untuk package_id: {$package['id']}", ['package' => $package]);
            return;
        }

        $reply_markup = !empty($keyboard) ? json_encode($keyboard) : null;
        $app->telegram_api->copyMessage($app->chat_id, $thumbnail['storage_channel_id'], $thumbnail['storage_message_id'], $caption, 'Markdown', $reply_markup);
    }
}