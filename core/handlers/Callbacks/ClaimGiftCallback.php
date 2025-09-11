<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;
use TGBot\Database\MediaPackageRepository;
use TGBot\Database\SaleRepository;

class ClaimGiftCallback implements CallbackCommandInterface
{
    public function execute(App $app, array $callback_query, string $public_id): void
    {
        $package_repo = new MediaPackageRepository($app->pdo);
        $sale_repo = new SaleRepository($app->pdo);

        $package = $package_repo->findByPublicId($public_id);
        if (!$package) {
            $app->telegram_api->answerCallbackQuery($callback_query['id'], '⚠️ Paket tidak ditemukan.', true);
            return;
        }

        $sale = $sale_repo->getSaleDetails($package['id'], $app->user['id']);

        if (!$sale || $sale['granted_to_user_id'] != $app->user['id'] || $sale['buyer_user_id'] == $app->user['id']) {
            $app->telegram_api->answerCallbackQuery($callback_query['id'], '⚠️ Anda tidak memiliki hadiah ini untuk diklaim.', true);
            return;
        }

        if ($sale['claimed_at'] !== null) {
            $app->telegram_api->answerCallbackQuery($callback_query['id'], '🎁 Hadiah ini sudah diklaim sebelumnya.', true);
            // Proceed to show content if already claimed
            $view_page_callback = new ViewPageCallback();
            $view_page_callback->execute($app, $callback_query, $public_id . '_0');
            return;
        }

        if ($sale['expires_at'] !== null && strtotime($sale['expires_at']) < time()) {
            $app->telegram_api->answerCallbackQuery($callback_query['id'], '⚠️ Hadiah ini sudah kadaluarsa dan tidak dapat diklaim.', true);
            return;
        }

        // Mark as claimed
        if ($sale_repo->markSaleAsClaimed($sale['id'])) {
            $app->telegram_api->answerCallbackQuery($callback_query['id'], '✅ Hadiah berhasil diklaim!', true);
            // Now show the content
            $view_page_callback = new ViewPageCallback();
            $view_page_callback->execute($app, $callback_query, $public_id . '_0');
        } else {
            $app->telegram_api->answerCallbackQuery($callback_query['id'], '❌ Gagal mengklaim hadiah. Silakan coba lagi.', true);
        }
    }
}
