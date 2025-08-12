<?php

require_once __DIR__ . '/../database/PackageRepository.php';
require_once __DIR__ . '/../database/SaleRepository.php';

class CallbackQueryHandler
{
    private $pdo;
    private $telegram_api;
    private $current_user;
    private $chat_id;
    private $callback_query;
    private $package_repo;
    private $sale_repo;

    public function __construct(PDO $pdo, TelegramAPI $telegram_api, array $current_user, int $chat_id, array $callback_query)
    {
        $this->pdo = $pdo; // pdo can be removed after all queries are moved
        $this->telegram_api = $telegram_api;
        $this->current_user = $current_user;
        $this->chat_id = $chat_id;
        $this->callback_query = $callback_query;
        $this->package_repo = new PackageRepository($pdo);
        $this->sale_repo = new SaleRepository($pdo);
    }

    public function handle()
    {
        $callback_data = $this->callback_query['data'];

        if (strpos($callback_data, 'view_full_') === 0) {
            $this->handleViewFull(substr($callback_data, strlen('view_full_')));
        } elseif (strpos($callback_data, 'buy_') === 0) {
            $this->handleBuy(substr($callback_data, strlen('buy_')));
        }
    }

    private function handleViewFull(string $package_id)
    {
        $internal_user_id = $this->current_user['id'];
        $callback_query_id = $this->callback_query['id'];

        $package = $this->package_repo->find($package_id);
        if (!$package) {
            $this->telegram_api->apiRequest('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '⚠️ Paket tidak ditemukan.', 'show_alert' => true]);
            return;
        }

        $is_seller = ($package['seller_user_id'] == $internal_user_id);
        $has_purchased = $this->sale_repo->hasUserPurchased($package_id, $internal_user_id);

        if ($is_seller || $has_purchased) {
            $this->telegram_api->apiRequest('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '✅ Akses diberikan. Mengirim konten lengkap...']);

            $files = $this->package_repo->getPackageFiles($package_id);
            if (!empty($files)) {
                $media_group = array_map(fn($file) => ['type' => $file['type'], 'media' => $file['file_id']], $files);
                $this->telegram_api->sendMediaGroup($this->chat_id, json_encode($media_group));
            }
        } else {
            $this->telegram_api->apiRequest('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '⚠️ Anda tidak memiliki akses ke konten ini.', 'show_alert' => true]);
        }
    }

    private function handleBuy(string $package_id)
    {
        $internal_user_id = $this->current_user['id'];
        $callback_query_id = $this->callback_query['id'];

        $package = $this->package_repo->findForPurchase($package_id);

        if ($package && $this->current_user['balance'] >= $package['price']) {
            $sale_successful = $this->sale_repo->createSale($package_id, $package['seller_user_id'], $internal_user_id, $package['price']);

            if ($sale_successful) {
                $this->package_repo->markAsSold($package_id);

                $files = $this->package_repo->getPackageFiles($package_id);
                if (!empty($files)) {
                    $full_package_details = $this->package_repo->find($package_id);
                    $media_group = array_map(fn($file) => ['type' => $file['type'], 'media' => $file['file_id']], $files);
                    $media_group[0]['caption'] = "Terima kasih telah membeli!\n\n" . ($full_package_details['description'] ?? '');
                    $this->telegram_api->sendMediaGroup($this->chat_id, json_encode($media_group));
                }
                $this->telegram_api->apiRequest('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'Pembelian berhasil!']);
            } else {
                $this->telegram_api->apiRequest('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'Pembelian gagal karena kesalahan internal.', 'show_alert' => true]);
            }
        } else {
            $this->telegram_api->apiRequest('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'Pembelian gagal. Saldo tidak cukup atau item tidak tersedia.', 'show_alert' => true]);
        }
    }
}
