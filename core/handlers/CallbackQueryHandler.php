<?php

require_once __DIR__ . '/../database/PackageRepository.php';
require_once __DIR__ . '/../database/SaleRepository.php';
require_once __DIR__ . '/../database/UserRepository.php'; // Tambahkan ini

class CallbackQueryHandler
{
    private $pdo;
    private $telegram_api;
    private $current_user;
    private $user_repo; // Tambahkan ini
    private $chat_id;
    private $callback_query;
    private $package_repo;
    private $sale_repo;

    public function __construct(PDO $pdo, TelegramAPI $telegram_api, UserRepository $user_repo, array $current_user, int $chat_id, array $callback_query)
    {
        $this->pdo = $pdo; // pdo can be removed after all queries are moved
        $this->telegram_api = $telegram_api;
        $this->user_repo = $user_repo; // Tambahkan ini
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
        } elseif ($callback_data === 'register_seller') {
            $this->handleRegisterSeller();
        }
    }

    private function handleViewFull(string $public_id)
    {
        $internal_user_id = $this->current_user['id'];
        $callback_query_id = $this->callback_query['id'];

        $package = $this->package_repo->findByPublicId($public_id);
        if (!$package) {
            $this->telegram_api->answerCallbackQuery($callback_query_id, '⚠️ Paket tidak ditemukan.', true);
            return;
        }
        $package_id = $package['id'];

        $is_seller = ($package['seller_user_id'] == $internal_user_id);
        $has_purchased = $this->sale_repo->hasUserPurchased($package_id, $internal_user_id);

        if ($is_seller || $has_purchased) {
            $this->telegram_api->answerCallbackQuery($callback_query_id, '✅ Akses diberikan. Mengirim konten lengkap...');

            $files = $this->package_repo->getPackageFiles($package_id);
            if (!empty($files)) {
                $from_chat_id = $files[0]['chat_id'];
                $message_ids = json_encode(array_column($files, 'message_id'));
                $protect_content = (bool) $package['protect_content'];

                // Kirim media menggunakan copyMessages
                $this->telegram_api->copyMessages(
                    $this->chat_id,
                    $from_chat_id,
                    $message_ids,
                    $protect_content
                );
            }
        } else {
            $this->telegram_api->answerCallbackQuery($callback_query_id, '⚠️ Anda tidak memiliki akses ke konten ini.', true);
        }
    }

    private function handleRegisterSeller()
    {
        $callback_query_id = $this->callback_query['id'];
        $user_id = $this->current_user['id'];

        // Cek lagi untuk memastikan pengguna belum punya ID
        if (!empty($this->current_user['public_seller_id'])) {
            $this->telegram_api->answerCallbackQuery($callback_query_id, 'Anda sudah terdaftar sebagai penjual.', true);
            return;
        }

        try {
            $public_id = $this->user_repo->setPublicId($user_id);
            $message = "Selamat! Anda berhasil terdaftar sebagai penjual.\n\nID Penjual Publik Anda adalah: *{$public_id}*\n\nSekarang Anda dapat menggunakan perintah /sell dengan me-reply media yang ingin Anda jual.";
            $this->telegram_api->sendMessage($this->chat_id, $message, 'Markdown');
            $this->telegram_api->answerCallbackQuery($callback_query_id);
        } catch (Exception $e) {
            $this->telegram_api->answerCallbackQuery($callback_query_id, 'Terjadi kesalahan saat mendaftar. Coba lagi.', true);
            app_log("Gagal mendaftarkan penjual: " . $e->getMessage(), 'error');
        }
    }

    private function handleBuy(string $public_id)
    {
        $internal_user_id = $this->current_user['id'];
        $callback_query_id = $this->callback_query['id'];

        $package = $this->package_repo->findByPublicId($public_id);
        if (!$package) {
            $this->telegram_api->answerCallbackQuery($callback_query_id, '⚠️ Paket tidak ditemukan.', true);
            return;
        }
        $package_id = $package['id'];

        // Lakukan pengecekan ulang untuk memastikan paket masih tersedia untuk dibeli
        $package_for_purchase = $this->package_repo->findForPurchase($package_id);

        if ($package_for_purchase && $this->current_user['balance'] >= $package['price']) {
            $sale_successful = $this->sale_repo->createSale($package_id, $package['seller_user_id'], $internal_user_id, $package['price']);

            if ($sale_successful) {
                $this->package_repo->markAsSold($package_id);

                $files = $this->package_repo->getPackageFiles($package_id);
                if (!empty($files)) {
                    $caption = "Terima kasih telah membeli!\n\n" . ($package['description'] ?? '');
                    $this->telegram_api->sendMessage($this->chat_id, $caption);

                    $from_chat_id = $files[0]['chat_id'];
                    $message_ids = json_encode(array_column($files, 'message_id'));
                    $protect_content = (bool) $package['protect_content'];

                    $this->telegram_api->copyMessages($this->chat_id, $from_chat_id, $message_ids, $protect_content);
                }
                $this->telegram_api->answerCallbackQuery($callback_query_id, 'Pembelian berhasil!');
            } else {
                $this->telegram_api->answerCallbackQuery($callback_query_id, 'Pembelian gagal karena kesalahan internal.', true);
            }
        } else {
            $this->telegram_api->answerCallbackQuery($callback_query_id, 'Pembelian gagal. Saldo tidak cukup atau item tidak tersedia.', true);
        }
    }
}
