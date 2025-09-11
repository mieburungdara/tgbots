<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;
use TGBot\Database\MediaPackageRepository;
use TGBot\Database\SaleRepository;
use TGBot\Database\SubscriptionRepository;
use DateTime;
use Exception;

class BuyCallback implements CallbackCommandInterface
{
    public function execute(App $app, array $callback_query, string $public_id): void
    {
        $package_repo = new MediaPackageRepository($app->pdo);
        $sale_repo = new SaleRepository($app->pdo);
        $subscription_repo = new SubscriptionRepository($app->pdo);

        $app->telegram_api->answerCallbackQuery($callback_query['id'], 'Memproses pembelian...');

        try {
            $package = $package_repo->findByPublicId($public_id);
            if (!$package || $package['status'] !== 'available') {
                throw new Exception('Paket tidak ditemukan atau sudah tidak tersedia.');
            }

            if ($package['package_type'] === 'subscription') {
                $this->handleSubscriptionPurchase($app, $package, $subscription_repo, $sale_repo);
            } else {
                $this->handleOneTimePurchase($app, $package, $sale_repo, $package_repo);
            }

            // Common success message and content view
            $app->telegram_api->sendMessage($app->chat_id, '✅ Pembelian berhasil! Menampilkan konten Anda...');
            $viewPageCallback = new ViewPageCallback();
            $viewPageCallback->execute($app, $callback_query, "{$public_id}_0");

        } catch (Exception $e) {
            // Rollback will be handled by UpdateDispatcher
            $error_message = "⚠️ Gagal: " . $e->getMessage();
            $app->telegram_api->sendMessage($app->chat_id, $error_message);
            app_log("Gagal menangani pembelian untuk public_id {$public_id}: " . $e->getMessage(), 'error');
            // Re-throw the exception to ensure the dispatcher rolls back the transaction
            throw $e;
        }
    }

    private function handleOneTimePurchase(App $app, array $package, SaleRepository $sale_repo, MediaPackageRepository $package_repo): void
    {
        if ($app->user['balance'] < $package['price']) {
            throw new Exception('Saldo Anda tidak cukup.');
        }

        $sale_repo->createSale($package['id'], $package['seller_user_id'], $app->user['id'], $package['price']);
        $package_repo->markAsSold($package['id']);

        // Kirim notifikasi ke penjual
        $price_formatted = "Rp " . number_format($package['price'], 0, ',', '.');
        $seller_message = "🎉 Selamat! Item Anda `{$package['public_id']}` telah terjual seharga *{$price_formatted}*. Saldo Anda telah diperbarui.";
        $app->telegram_api->sendMessage($package['seller_user_id'], $seller_message, 'Markdown');
    }

    private function handleSubscriptionPurchase(App $app, array $package, SubscriptionRepository $subscription_repo, SaleRepository $sale_repo): void
    {
        if ($app->user['balance'] < $package['monthly_price']) {
            throw new Exception('Saldo Anda tidak cukup untuk berlangganan.');
        }

        if ($subscription_repo->hasActiveSubscription($app->user['id'], $package['id'])) {
            throw new Exception('Anda sudah memiliki langganan aktif untuk paket ini.');
        }

        $endDate = new DateTime();
        $endDate->modify('+1 month');

        $subscription_repo->createSubscription($app->user['id'], $package['id'], $endDate->format('Y-m-d H:i:s'));
        
        // We still create a sale record for financial tracking
        $sale_repo->createSale($package['id'], $package['seller_user_id'], $app->user['id'], $package['monthly_price']);

        // Kirim notifikasi ke penjual
        $price_formatted = "Rp " . number_format($package['monthly_price'], 0, ',', '.');
        $seller_message = "🎉 Pelanggan Baru! Seseorang telah berlangganan paket `{$package['public_id']}` Anda seharga *{$price_formatted}/bulan*. Saldo Anda telah diperbarui.";
        $app->telegram_api->sendMessage($package['seller_user_id'], $seller_message, 'Markdown');
    }
}