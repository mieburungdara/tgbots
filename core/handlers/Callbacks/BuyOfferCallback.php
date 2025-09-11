<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;
use TGBot\Database\SaleRepository;
use TGBot\Database\MediaPackageRepository;
use Exception;

class BuyOfferCallback implements CallbackCommandInterface
{
    public function execute(App $app, array $callback_query, string $offer_id): void
    {
        $app->telegram_api->answerCallbackQuery($callback_query['id'], 'Memproses pembelian dengan harga penawaran...');

        $sale_repo = new SaleRepository($app->pdo);
        $package_repo = new MediaPackageRepository($app->pdo);

        try {
            $stmt = $app->pdo->prepare("SELECT * FROM offers WHERE id = ? AND buyer_user_id = ? AND status = 'accepted'");
            $stmt->execute([$offer_id, $app->user['id']]);
            $offer = $stmt->fetch();

            if (!$offer) {
                throw new Exception('Penawaran tidak valid, sudah digunakan, atau kedaluwarsa.');
            }

            if ($app->user['balance'] < $offer['offer_price']) {
                throw new Exception('Saldo Anda tidak cukup untuk harga penawaran.');
            }

            // Gunakan metode createSale yang sudah ada dengan harga penawaran
            $sale_repo->createSale($offer['package_id'], $offer['seller_user_id'], $offer['buyer_user_id'], $offer['offer_price']);
            $package_repo->markAsSold($offer['package_id']);

            // Kirim notifikasi ke penjual
            $package = $package_repo->find($offer['package_id']);
            $price_formatted = "Rp " . number_format($offer['offer_price'], 0, ',', '.');
            $seller_message = "🎉 Selamat! Item Anda `{$package['public_id']}` telah terjual melalui penawaran seharga *{$price_formatted}*. Saldo Anda telah diperbarui.";
            $app->telegram_api->sendMessage($offer['seller_user_id'], $seller_message, 'Markdown');

            // Tandai penawaran sebagai selesai
            $app->pdo->prepare("UPDATE offers SET status = 'completed' WHERE id = ?")->execute([$offer_id]);

            $app->telegram_api->editMessageText($app->chat_id, $callback_query['message']['message_id'], '✅ Pembelian dengan harga penawaran berhasil! Menampilkan konten Anda...');

            // Delegasikan ke ViewPageCallback untuk menampilkan konten
            $package = $package_repo->find($offer['package_id']);
            $viewPageCallback = new ViewPageCallback();
            $viewPageCallback->execute($app, $callback_query, "{$package['public_id']}_0");

        } catch (Exception $e) {
            $error_message = "⚠️ Gagal: " . $e->getMessage();
            $app->telegram_api->sendMessage($app->chat_id, $error_message);
            app_log("Gagal menangani pembelian via penawaran untuk offer_id {$offer_id}: " . $e->getMessage(), 'error');
            throw $e; // Lemparkan kembali agar transaksi di-rollback oleh dispatcher
        }
    }
}
