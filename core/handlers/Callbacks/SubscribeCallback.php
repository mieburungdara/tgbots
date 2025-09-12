<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;
use TGBot\Database\UserRepository;
use TGBot\Database\SubscriptionRepository;
use TGBot\Database\SaleRepository;
use DateTime;
use Exception;

class SubscribeCallback implements CallbackCommandInterface
{
    public function execute(App $app, array $callback_query, string $seller_id): void
    {
        $seller_id = (int)$seller_id;
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);
        $subscription_repo = new SubscriptionRepository($app->pdo);
        $sale_repo = new SaleRepository($app->pdo);

        $app->telegram_api->answerCallbackQuery($callback_query['id'], 'Memproses langganan...');

        try {
            $seller = $user_repo->findUserByTelegramId($seller_id);
            if (!$seller || empty($seller['subscription_price'])) {
                throw new Exception('Penjual ini tidak menawarkan langganan.');
            }

            if ($app->user['balance'] < $seller['subscription_price']) {
                throw new Exception('Saldo Anda tidak cukup untuk berlangganan.');
            }

            if ($subscription_repo->hasActiveSubscription($app->user['id'], $seller_id)) {
                throw new Exception('Anda sudah aktif berlangganan pada penjual ini.');
            }

            $endDate = new DateTime();
            $endDate->modify('+1 month');

            $subscription_repo->createSubscription($app->user['id'], $seller_id, $endDate->format('Y-m-d H:i:s'));
            
            // Create a sale record for financial tracking, linking to the seller, not a specific package
            $sale_repo->createSubscriptionSale($seller_id, $app->user['id'], $seller['subscription_price']);

            // Notify seller
            $price_formatted = "Rp " . number_format($seller['subscription_price'], 0, ',', '.');
            $seller_message = "🎉 Pelanggan Baru! Pengguna {$app->user['first_name']} telah berlangganan channel Anda seharga *{$price_formatted}/bulan*. Saldo Anda telah diperbarui.";
            $app->telegram_api->sendMessage($seller_id, $seller_message, 'Markdown');

            // Notify subscriber
            $app->telegram_api->sendMessage($app->chat_id, '✅ Anda berhasil berlangganan! Anda sekarang memiliki akses ke semua konten dari penjual ini.');

        } catch (Exception $e) {
            $error_message = "⚠️ Gagal: " . $e->getMessage();
            $app->telegram_api->sendMessage($app->chat_id, $error_message);
            app_log("Gagal memproses langganan ke seller {$seller_id} untuk user {$app->user['id']}: " . $e->getMessage(), 'error');
            throw $e;
        }
    }
}
