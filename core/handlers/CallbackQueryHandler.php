<?php

require_once __DIR__ . '/../database/PackageRepository.php';
require_once __DIR__ . '/../database/SaleRepository.php';
require_once __DIR__ . '/../database/UserRepository.php';

class CallbackQueryHandler
{
    private $pdo;
    private $telegram_api;
    private $current_user;
    private $user_repo;
    private $chat_id;
    private $callback_query;
    private $package_repo;
    private $sale_repo;

    public function __construct(PDO $pdo, TelegramAPI $telegram_api, UserRepository $user_repo, array $current_user, int $chat_id, array $callback_query)
    {
        $this->pdo = $pdo;
        $this->telegram_api = $telegram_api;
        $this->user_repo = $user_repo;
        $this->current_user = $current_user;
        $this->chat_id = $chat_id;
        $this->callback_query = $callback_query;
        $this->package_repo = new PackageRepository($pdo);
        $this->sale_repo = new SaleRepository($pdo);
    }

    public function handle()
    {
        $callback_data = $this->callback_query['data'];

        if (strpos($callback_data, 'view_page_') === 0) {
            $this->handleViewPage(substr($callback_data, strlen('view_page_')));
        } elseif (strpos($callback_data, 'buy_') === 0) {
            $this->handleBuy(substr($callback_data, strlen('buy_')));
        } elseif ($callback_data === 'register_seller') {
            $this->handleRegisterSeller();
        } elseif ($callback_data === 'noop') {
            // Button for display only, like page numbers. Just answer the callback.
            $this->telegram_api->answerCallbackQuery($this->callback_query['id']);
        }
    }

    private function handleViewPage(string $params)
    {
        $parts = explode('_', $params);
        if (count($parts) !== 2) {
            $this->telegram_api->answerCallbackQuery($this->callback_query['id'], '⚠️ Format callback tidak valid.', true);
            return;
        }
        $public_id = $parts[0];
        $page_index = (int)$parts[1];

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

        if (!$is_seller && !$has_purchased) {
            $this->telegram_api->answerCallbackQuery($callback_query_id, '⚠️ Anda tidak memiliki akses ke konten ini.', true);
            return;
        }

        $pages = $this->package_repo->getGroupedPackageContent($package_id);
        $total_pages = count($pages);

        if (empty($pages) || !isset($pages[$page_index])) {
            $this->telegram_api->answerCallbackQuery($callback_query_id, '⚠️ Konten tidak ditemukan atau halaman tidak valid.', true);
            return;
        }

        // Build the navigation keyboard
        $keyboard_buttons = [];
        if ($page_index > 0) {
            $keyboard_buttons[] = ['text' => '◀️ Sebelumnya', 'callback_data' => "view_page_{$public_id}_" . ($page_index - 1)];
        }

        $keyboard_buttons[] = ['text' => ($page_index + 1) . " / {$total_pages}", 'callback_data' => 'noop'];

        if ($page_index < $total_pages - 1) {
            $keyboard_buttons[] = ['text' => 'Selanjutnya ▶️', 'callback_data' => "view_page_{$public_id}_" . ($page_index + 1)];
        }
        $reply_markup = json_encode(['inline_keyboard' => [$keyboard_buttons]]);

        // Send the content for the current page
        $current_page_content = $pages[$page_index];
        $from_chat_id = $current_page_content[0]['storage_channel_id'];
        $protect_content = (bool) $package['protect_content'];

        // Hapus pesan navigasi sebelumnya jika ada
        if (isset($this->callback_query['message']['message_id'])) {
            $this->telegram_api->deleteMessage($this->chat_id, $this->callback_query['message']['message_id']);
        }

        if (count($current_page_content) === 1) {
            // Single media item, kirim dengan keyboard navigasi
            $this->telegram_api->copyMessage(
                $this->chat_id,
                $from_chat_id,
                $current_page_content[0]['storage_message_id'],
                null, // caption
                $reply_markup,
                $protect_content
            );
        } else {
            // Media group / album
            $message_ids = json_encode(array_column($current_page_content, 'storage_message_id'));
            $this->telegram_api->copyMessages(
                $this->chat_id,
                $from_chat_id,
                $message_ids,
                $protect_content
            );
            // Kirim keyboard navigasi dalam pesan terpisah untuk album
            $this->telegram_api->sendMessage($this->chat_id, "Navigasi Konten:", null, $reply_markup);
        }

        $this->telegram_api->answerCallbackQuery($callback_query_id);
    }

    private function handleRegisterSeller()
    {
        $callback_query_id = $this->callback_query['id'];
        $user_id = $this->current_user['id'];

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

        $package_for_purchase = $this->package_repo->findForPurchase($package_id);

        if ($package_for_purchase && $this->current_user['balance'] >= $package['price']) {
            $sale_successful = $this->sale_repo->createSale($package_id, $package['seller_user_id'], $internal_user_id, $package['price']);

            if ($sale_successful) {
                $this->package_repo->markAsSold($package_id);

                // Setelah membeli, langsung tampilkan konten dengan pagination
                $this->telegram_api->answerCallbackQuery($callback_query_id, 'Pembelian berhasil! Menampilkan konten...');
                $this->handleViewPage("{$public_id}_0");
            } else {
                $this->telegram_api->answerCallbackQuery($callback_query_id, 'Pembelian gagal karena kesalahan internal.', true);
            }
        } else {
            $this->telegram_api->answerCallbackQuery($callback_query_id, 'Pembelian gagal. Saldo tidak cukup atau item tidak tersedia.', true);
        }
    }
}
