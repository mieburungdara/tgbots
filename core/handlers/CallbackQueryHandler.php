<?php

require_once __DIR__ . '/../database/PackageRepository.php';
require_once __DIR__ . '/../database/SaleRepository.php';
require_once __DIR__ . '/../database/UserRepository.php';
require_once __DIR__ . '/../database/SellerSalesChannelRepository.php';
require_once __DIR__ . '/../database/ChannelPostPackageRepository.php';

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
    private $sales_channel_repo;
    private $post_package_repo;

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
        $this->sales_channel_repo = new SellerSalesChannelRepository($pdo);
        $this->post_package_repo = new ChannelPostPackageRepository($pdo);
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
        } elseif (strpos($callback_data, 'post_channel_') === 0) {
            $this->handlePostToChannel(substr($callback_data, strlen('post_channel_')));
        } elseif ($callback_data === 'noop') {
            // Button for display only, like page numbers. Just answer the callback.
            $this->telegram_api->answerCallbackQuery($this->callback_query['id']);
        }
    }

    private function handlePostToChannel(string $public_id)
    {
        $callback_query_id = $this->callback_query['id'];
        $internal_user_id = $this->current_user['id'];

        // 1. Get package and verify ownership
        $package = $this->package_repo->findByPublicId($public_id);
        if (!$package || $package['seller_user_id'] != $internal_user_id) {
            $this->telegram_api->answerCallbackQuery($callback_query_id, '⚠️ Anda tidak memiliki izin untuk mem-posting konten ini.', true);
            return;
        }

        // 2. Get seller's registered channel
        $sales_channel = $this->sales_channel_repo->findBySellerId($internal_user_id);
        if (!$sales_channel) {
            $this->telegram_api->answerCallbackQuery($callback_query_id, '⚠️ Anda belum mendaftarkan channel jualan.', true);
            return;
        }
        $channel_id = $sales_channel['channel_id'];

        // 3. Get thumbnail to post
        $thumbnail = $this->package_repo->getThumbnailFile($package['id']);
        if (!$thumbnail) {
            $this->telegram_api->answerCallbackQuery($callback_query_id, '⚠️ Gagal mendapatkan media pratinjau untuk konten ini.', true);
            return;
        }

        // 4. Format the message (without keyboard)
        $price_formatted = "Rp " . number_format($package['price'], 0, ',', '.');
        $escaped_description = $this->telegram_api->escapeMarkdown($package['description']);
        $escaped_price = $this->telegram_api->escapeMarkdown($price_formatted);

        $caption = "✨ *Konten Baru Tersedia* ✨\n\n" .
                   "{$escaped_description}\n\n" .
                   "Harga: *{$escaped_price}*";

        // 5. Post to the channel without a keyboard
        try {
            $result = $this->telegram_api->copyMessage(
                $channel_id,
                $thumbnail['chat_id'],
                $thumbnail['message_id'],
                $caption,
                'Markdown',
                null, // No reply_markup
                (bool)$package['protect_content']
            );

            if (!$result || ($result['ok'] === false)) {
                $error_description = $result['description'] ?? 'Unknown error';
                throw new Exception($error_description);
            }

            // 6. Save the mapping to the database
            $new_message_id = $result['result']['message_id'];
            $this->post_package_repo->create($channel_id, $new_message_id, $package['id']);

            $this->telegram_api->answerCallbackQuery($callback_query_id, '✅ Berhasil di-posting!', false);

        } catch (Exception $e) {
             // Check for a specific error message indicating the bot was kicked or permissions were lost
            if (stripos($e->getMessage(), 'bot was kicked') !== false || stripos($e->getMessage(), 'not a member') !== false) {
                $this->sales_channel_repo->deactivate($internal_user_id);
                $error_message = "❌ Gagal: Bot bukan lagi admin di channel. Channel Anda telah di-unregister secara otomatis.";
                $this->telegram_api->answerCallbackQuery($callback_query_id, $error_message, true);
                app_log("Deactivating channel {$channel_id} for user {$internal_user_id} due to being kicked.", 'bot_error');
            } else {
                $error_message = "❌ Gagal mem-posting ke channel. Pastikan bot adalah admin dan memiliki izin yang benar.";
                $this->telegram_api->answerCallbackQuery($callback_query_id, $error_message, true);
                app_log("Gagal post ke channel {$channel_id} untuk user {$internal_user_id}: " . $e->getMessage(), 'bot_error');
            }
        }
    }

    private function handleViewPage(string $params)
    {
        $parts = explode('_', $params);
        if (count($parts) < 2) {
            $this->telegram_api->answerCallbackQuery($this->callback_query['id'], '⚠️ Format callback tidak valid.', true);
            return;
        }

        // The last part is always the page index. The rest is the public_id.
        $page_index = (int)array_pop($parts);
        $public_id = implode('_', $parts);

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

        // Build the numbered pagination keyboard
        $keyboard_buttons = [];
        if ($total_pages > 1) {
            for ($i = 0; $i < $total_pages; $i++) {
                if ($i === $page_index) {
                    // Highlight the current page
                    $keyboard_buttons[] = ['text' => "- " . ($i + 1) . " -", 'callback_data' => 'noop'];
                } else {
                    $keyboard_buttons[] = ['text' => (string)($i + 1), 'callback_data' => "view_page_{$public_id}_{$i}"];
                }
            }
        }
        $reply_markup = empty($keyboard_buttons) ? null : json_encode(['inline_keyboard' => [$keyboard_buttons]]);

        // Build the caption text, without price for subsequent views
        $escaped_description = $this->telegram_api->escapeMarkdown($package['description'] ?? '');
        $info_text = $escaped_description;

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
                $info_text, // caption
                'Markdown', // parse_mode
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
            $this->telegram_api->sendMessage($this->chat_id, $info_text, 'Markdown', $reply_markup);
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
            $escaped_public_id = $this->telegram_api->escapeMarkdown($public_id);
            $message = "Selamat! Anda berhasil terdaftar sebagai penjual.\n\nID Penjual Publik Anda adalah: *{$escaped_public_id}*\n\nSekarang Anda dapat menggunakan perintah /sell dengan me-reply media yang ingin Anda jual.";
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

        // Jawab callback segera untuk menghindari timeout
        $this->telegram_api->answerCallbackQuery($callback_query_id, 'Memproses pembelian...');

        try {
            $package = $this->package_repo->findByPublicId($public_id);
            if (!$package || $package['status'] !== 'available') {
                throw new Exception('Paket tidak ditemukan atau sudah tidak tersedia.');
            }
            $package_id = $package['id'];

            if ($this->current_user['balance'] < $package['price']) {
                throw new Exception('Saldo Anda tidak cukup untuk melakukan pembelian ini.');
            }

            // createSale sekarang akan melempar Exception jika gagal, bukan mengembalikan false
            $this->sale_repo->createSale($package_id, $package['seller_user_id'], $internal_user_id, $package['price']);
            $this->package_repo->markAsSold($package_id);

            // Kirim pesan konfirmasi baru karena callback sudah dijawab
            $this->telegram_api->sendMessage($this->chat_id, '✅ Pembelian berhasil! Menampilkan konten Anda...');
            $this->handleViewPage("{$public_id}_0");

        } catch (Exception $e) {
            // Kirim pesan error karena callback sudah dijawab
            $error_message = "⚠️ Terjadi kesalahan saat memproses pembelian: " . $e->getMessage();
            $this->telegram_api->sendMessage($this->chat_id, $error_message);
            app_log("Gagal menangani pembelian untuk public_id {$public_id}: " . $e->getMessage(), 'error');
        }
    }
}
