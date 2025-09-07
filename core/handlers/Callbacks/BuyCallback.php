<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;
use TGBot\Database\MediaPackageRepository;
use TGBot\Database\SaleRepository;
use Exception;

class BuyCallback implements CallbackCommandInterface
{
    public function execute(App $app, array $callback_query, string $public_id): void
    {
        $package_repo = new MediaPackageRepository($app->pdo);
        $sale_repo = new SaleRepository($app->pdo);

        $app->telegram_api->answerCallbackQuery($callback_query['id'], 'Memproses pembelian...');

        try {
            $app->pdo->beginTransaction();

            $package = $package_repo->findByPublicId($public_id);
            if (!$package || $package['status'] !== 'available') {
                throw new Exception('Paket tidak ditemukan atau sudah tidak tersedia.');
            }

            if ($app->user['balance'] < $package['price']) {
                throw new Exception('Saldo Anda tidak cukup.');
            }

            $sale_repo->createSale($package['id'], $package['seller_user_id'], $app->user['id'], $package['price']);
            $package_repo->markAsSold($package['id']);

            $app->pdo->commit();

            $app->telegram_api->sendMessage($app->chat_id, '✅ Pembelian berhasil! Menampilkan konten Anda...');

            // Delegate to ViewPageCallback to show the content
            $viewPageCallback = new ViewPageCallback();
            $viewPageCallback->execute($app, $callback_query, "{$public_id}_0");

        } catch (Exception $e) {
            $app->pdo->rollBack();
            $error_message = "⚠️ Gagal: " . $e->getMessage();
            $app->telegram_api->sendMessage($app->chat_id, $error_message);
            app_log("Gagal menangani pembelian untuk public_id {$public_id}: " . $e->getMessage(), 'error');
        }
    }
}
