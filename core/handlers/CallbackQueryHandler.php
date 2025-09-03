<?php

/**
 * This file is part of the TGBot package.
 *
 * (c) Zidin Mitra Abadi <zidinmitra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TGBot\Handlers;

use Exception;
use TGBot\App;
use TGBot\Handlers\HandlerInterface;
use TGBot\Database\PostPackageRepository;
use TGBot\Database\SaleRepository;
use TGBot\Database\UserRepository;
use TGBot\Database\SellerSalesChannelRepository;
use TGBot\Database\ChannelPostPackageRepository;
use TGBot\Handlers\RateHandler;
use TGBot\Handlers\TanyaHandler;

/**
 * Class CallbackQueryHandler
 * @package TGBot\Handlers
 */
class CallbackQueryHandler implements HandlerInterface
{
    /**
     * Handle a callback query.
     *
     * @param App $app
     * @param array $callback_query
     * @return void
     */
    public function handle(App $app, array $callback_query): void
    {
        $callback_data = $callback_query['data'];

        if (strpos($callback_data, 'rate_') === 0) {
            (new RateHandler())->handle($app, $callback_query);
        } elseif (strpos($callback_data, 'tanya_') === 0) {
            (new TanyaHandler())->handle($app, $callback_query);
        } elseif (strpos($callback_data, 'view_page_') === 0) {
            $this->handleViewPage($app, $callback_query, substr($callback_data, strlen('view_page_')));
        } elseif (strpos($callback_data, 'buy_') === 0) {
            $this->handleBuy($app, $callback_query, substr($callback_data, strlen('buy_')));
        } elseif ($callback_data === 'register_seller') {
            $this->handleRegisterSeller($app, $callback_query);
        } elseif (strpos($callback_data, 'post_channel_') === 0) {
            $this->handlePostToChannel($app, $callback_query, substr($callback_data, strlen('post_channel_')));
        } elseif (strpos($callback_data, 'retract_post_') === 0) {
            $this->handleRetractPost($app, $callback_query, substr($callback_data, strlen('retract_post_')));
        } elseif (strpos($callback_data, 'admin_approve_') === 0) {
            $this->handleAdminApproval($app, $callback_query, substr($callback_data, strlen('admin_approve_')));
        } elseif (strpos($callback_data, 'admin_reject_') === 0) {
            $this->handleAdminRejection($app, $callback_query, substr($callback_data, strlen('admin_reject_')));
        } elseif (strpos($callback_data, 'admin_ban_') === 0) {
            $this->handleAdminBan($app, $callback_query, substr($callback_data, strlen('admin_ban_')));
        } elseif ($callback_data === 'noop') {
            $app->telegram_api->answerCallbackQuery($callback_query['id']);
        }
    }

    /**
     * Handle a request to post a package preview to a sales channel.
     *
     * @param App $app
     * @param array $callback_query
     * @param string $public_id
     * @return void
     */
    private function handlePostToChannel(App $app, array $callback_query, string $public_id): void
    {
        $package_repo = new PostPackageRepository($app->pdo);
        $sales_channel_repo = new SellerSalesChannelRepository($app->pdo);
        $post_package_repo = new ChannelPostPackageRepository($app->pdo);

        $callback_query_id = $callback_query['id'];
        $telegram_user_id = $app->user['id'];

        $package = $package_repo->findByPublicId($public_id);
        if (!$package || $package['seller_user_id'] != $telegram_user_id) {
            $app->telegram_api->answerCallbackQuery($callback_query_id, '⚠️ Anda tidak memiliki izin untuk mem-posting konten ini.', true);
            return;
        }

        $sales_channel = $sales_channel_repo->findBySellerId($telegram_user_id);
        if (!$sales_channel) {
            $app->telegram_api->answerCallbackQuery($callback_query_id, '⚠️ Anda belum mendaftarkan channel jualan.', true);
            return;
        }
        $channel_id = $sales_channel['channel_id'];

        $thumbnail = $package_repo->getThumbnailFile($package['id']);
        if (!$thumbnail) {
            $app->telegram_api->answerCallbackQuery($callback_query_id, '⚠️ Gagal mendapatkan media pratinjau untuk konten ini.', true);
            return;
        }

        $price_formatted = "Rp " . number_format($package['price'], 0, ',', '.');
        $caption = sprintf(
            "✨ *Konten Baru Tersedia* ✨\n\n%s\n\nHarga: *%s*",
            $app->telegram_api->escapeMarkdown($package['description']),
            $app->telegram_api->escapeMarkdown($price_formatted)
        );

        try {
            $result = $app->telegram_api->copyMessage($channel_id, $thumbnail['chat_id'], $thumbnail['message_id'], $caption, 'Markdown', null, (bool)$package['protect_content']);
            if (!$result || ($result['ok'] === false)) throw new Exception($result['description'] ?? 'Unknown error');

            $post_package_repo->create($channel_id, $result['result']['message_id'], $package['id']);
            $app->telegram_api->answerCallbackQuery($callback_query_id, '✅ Berhasil di-posting!', false);

        } catch (Exception $e) {
            if (stripos($e->getMessage(), 'bot was kicked') !== false || stripos($e->getMessage(), 'not a member') !== false) {
                $sales_channel_repo->deactivate($telegram_user_id);
                $error_message = "❌ Gagal: Bot bukan lagi admin di channel. Channel Anda telah di-unregister.";
                app_log("Deactivating channel {$channel_id} for user {$telegram_user_id} due to being kicked.", 'bot_error');
            } else {
                $error_message = "❌ Gagal mem-posting. Pastikan bot adalah admin dengan izin yang benar.";
                app_log("Gagal post ke channel {$channel_id} untuk user {$telegram_user_id}: " . $e->getMessage(), 'bot_error');
            }
            $app->telegram_api->answerCallbackQuery($callback_query_id, $error_message, true);
        }
    }

    /**
     * Handle a request to view a specific page of a content package.
     *
     * @param App $app
     * @param array $callback_query
     * @param string $params
     * @return void
     */
    private function handleViewPage(App $app, array $callback_query, string $params): void
    {
        $package_repo = new PostPackageRepository($app->pdo);
        $sale_repo = new SaleRepository($app->pdo);

        $parts = explode('_', $params);
        if (count($parts) < 2) {
            $app->telegram_api->answerCallbackQuery($callback_query['id'], '⚠️ Format callback tidak valid.', true);
            return;
        }

        $page_index = (int)array_pop($parts);
        $public_id = implode('_', $parts);

        $package = $package_repo->findByPublicId($public_id);
        if (!$package) {
            $app->telegram_api->answerCallbackQuery($callback_query['id'], '⚠️ Paket tidak ditemukan.', true);
            return;
        }

        $is_seller = ($package['seller_user_id'] == $app->user['id']);
        $has_purchased = $sale_repo->hasUserPurchased($package['id'], $app->user['id']);

        if (!$is_seller && !$has_purchased) {
            $app->telegram_api->answerCallbackQuery($callback_query['id'], '⚠️ Anda tidak memiliki akses ke konten ini.', true);
            return;
        }

        $pages = $package_repo->getGroupedPackageContent($package['id']);
        $total_pages = count($pages);

        if (empty($pages) || !isset($pages[$page_index])) {
            $app->telegram_api->answerCallbackQuery($callback_query['id'], '⚠️ Konten tidak ditemukan atau halaman tidak valid.', true);
            return;
        }

        $keyboard_buttons = [];
        if ($total_pages > 1) {
            for ($i = 0; $i < $total_pages; $i++) {
                $keyboard_buttons[] = ['text' => ($i === $page_index ? "- " . ($i + 1) . " -" : (string)($i + 1)), 'callback_data' => ($i === $page_index ? 'noop' : "view_page_{$public_id}_{$i}")];
            }
        }
        $reply_markup = empty($keyboard_buttons) ? null : json_encode(['inline_keyboard' => [$keyboard_buttons]]);

        if (isset($callback_query['message']['message_id'])) {
            $app->telegram_api->deleteMessage($app->chat_id, $callback_query['message']['message_id']);
        }

        $current_page_content = $pages[$page_index];
        if (count($current_page_content) === 1) {
            $app->telegram_api->copyMessage($app->chat_id, $current_page_content[0]['storage_channel_id'], $current_page_content[0]['storage_message_id'], $package['description'], 'Markdown', $reply_markup, (bool)$package['protect_content']);
        } else {
            $message_ids = json_encode(array_column($current_page_content, 'storage_message_id'));
            $app->telegram_api->copyMessages($app->chat_id, $current_page_content[0]['storage_channel_id'], $message_ids, (bool)$package['protect_content']);
            $app->telegram_api->sendMessage($app->chat_id, $package['description'], 'Markdown', $reply_markup);
        }

        $app->telegram_api->answerCallbackQuery($callback_query['id']);
    }

    /**
     * Handle a user's request to register as a seller.
     *
     * @param App $app
     * @param array $callback_query
     * @return void
     */
    private function handleRegisterSeller(App $app, array $callback_query): void
    {
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);

        if (!empty($app->user['public_seller_id'])) {
            $app->telegram_api->answerCallbackQuery($callback_query['id'], 'Anda sudah terdaftar sebagai penjual.', true);
            return;
        }

        try {
            $public_id = $user_repo->setPublicId($app->user['id']);
            $message = "Selamat! Anda berhasil terdaftar sebagai penjual.\n\nID Penjual Publik Anda adalah: *" . $app->telegram_api->escapeMarkdown($public_id) . "*\n\nSekarang Anda dapat menggunakan perintah /sell.";
            $app->telegram_api->sendMessage($app->chat_id, $message, 'Markdown');
            $app->telegram_api->answerCallbackQuery($callback_query['id']);
        } catch (Exception $e) {
            $app->telegram_api->answerCallbackQuery($callback_query['id'], 'Terjadi kesalahan saat mendaftar. Coba lagi.', true);
            app_log("Gagal mendaftarkan penjual: " . $e->getMessage(), 'error');
        }
    }

    /**
     * Handle a user's request to buy a package.
     *
     * @param App $app
     * @param array $callback_query
     * @param string $public_id
     * @return void
     */
    private function handleBuy(App $app, array $callback_query, string $public_id): void
    {
        $package_repo = new PostPackageRepository($app->pdo);
        $sale_repo = new SaleRepository($app->pdo);

        $app->telegram_api->answerCallbackQuery($callback_query['id'], 'Memproses pembelian...');

        try {
            $package = $package_repo->findByPublicId($public_id);
            if (!$package || $package['status'] !== 'available') {
                throw new Exception('Paket tidak ditemukan atau sudah tidak tersedia.');
            }

            if ($app->user['balance'] < $package['price']) {
                throw new Exception('Saldo Anda tidak cukup.');
            }

            $sale_repo->createSale($package['id'], $package['seller_user_id'], $app->user['id'], $package['price']);
            $package_repo->markAsSold($package['id']);

            $app->telegram_api->sendMessage($app->chat_id, '✅ Pembelian berhasil! Menampilkan konten Anda...');
            $this->handleViewPage($app, $callback_query, "{$public_id}_0");

        } catch (Exception $e) {
            $error_message = "⚠️ Gagal: " . $e->getMessage();
            $app->telegram_api->sendMessage($app->chat_id, $error_message);
            app_log("Gagal menangani pembelian untuk public_id {$public_id}: " . $e->getMessage(), 'error');
        }
    }

    /**
     * Handle a request to retract a post.
     *
     * @param App $app
     * @param array $callback_query
     * @param string $public_id
     * @return void
     */
    private function handleRetractPost(App $app, array $callback_query, string $public_id): void
    {
        $post_repo = new PostPackageRepository($app->pdo);
        $channel_post_repo = new ChannelPostPackageRepository($app->pdo);

        try {
            $app->pdo->beginTransaction();

            $post = $post_repo->findByPublicId($public_id);

            if (!$post) {
                throw new Exception("Post tidak ditemukan.");
            }

            if ($post['seller_user_id'] != $app->user['id']) {
                throw new Exception("Anda tidak memiliki izin untuk menarik post ini.");
            }

            $channel_post = $channel_post_repo->findByPackageId($post['id']);

            if ($channel_post) {
                $app->telegram_api->deleteMessage($channel_post['channel_id'], $channel_post['message_id']);
            }

            $post_repo->updateStatus($post['id'], 'deleted');

            $app->telegram_api->editMessageText(
                $app->chat_id,
                $callback_query['message']['message_id'],
                "✅ Post berhasil ditarik dari channel publik."
            );

            $app->pdo->commit();
            $app->telegram_api->answerCallbackQuery($callback_query['id'], "Post ditarik.");

        } catch (Exception $e) {
            if ($app->pdo->inTransaction()) {
                $app->pdo->rollBack();
            }
            $app->telegram_api->answerCallbackQuery($callback_query['id'], "⚠️ Gagal menarik post: " . $e->getMessage(), true);
        }
    }

    /**
     * Handle an admin's request to reject a post.
     *
     * @param App $app
     * @param array $callback_query
     * @param string $params
     * @return void
     */
    private function handleAdminRejection(App $app, array $callback_query, string $params): void
    {
        $parts = explode('_', $params);
        $public_id = $parts[0];
        $reason = $parts[1] ?? 'Tidak ada alasan spesifik';

        $post_repo = new PostPackageRepository($app->pdo);

        try {
            $app->pdo->beginTransaction();

            $post = $post_repo->findByPublicId($public_id);
            if (!$post) {
                throw new Exception("Post tidak ditemukan.");
            }

            if ($post['status'] !== 'pending') {
                throw new Exception("Post ini tidak lagi dalam status pending.");
            }

            $post_repo->updateStatus($post['id'], 'rejected');

            // Notify the user
            $user_id = $post['seller_user_id'];
            $notification_text = "Maaf, kiriman Anda dengan ID `{$public_id}` telah ditolak.\n\nAlasan: *{$reason}*";
            $app->telegram_api->sendMessage($user_id, $notification_text, 'Markdown');

            // Update the message in the admin channel
            $admin_username = $callback_query['from']['username'] ?? $callback_query['from']['first_name'];
            $new_admin_text = $callback_query['message']['text'] . "\n\n---\n*Ditolak oleh @{$admin_username}*\nAlasan: {$reason}";

            $app->telegram_api->editMessageText(
                $callback_query['message']['chat']['id'],
                $callback_query['message']['message_id'],
                $new_admin_text,
                'Markdown'
            );

            $app->pdo->commit();
            $app->telegram_api->answerCallbackQuery($callback_query['id'], "Post {$public_id} ditolak.");

        } catch (Exception $e) {
            if ($app->pdo->inTransaction()) {
                $app->pdo->rollBack();
            }
            $app->telegram_api->answerCallbackQuery($callback_query['id'], "⚠️ Gagal: " . $e->getMessage(), true);
        }
    }

    /**
     * Handle an admin's request to ban a user.
     *
     * @param App $app
     * @param array $callback_query
     * @param string $params
     * @return void
     */
    private function handleAdminBan(App $app, array $callback_query, string $params): void
    {
        $parts = explode('_', $params);
        $user_to_ban_id = (int)$parts[0];
        $public_id = $parts[1] ?? null;
        $reason = $parts[2] ?? 'Pelanggaran berat terhadap aturan.';

        $user_repo = new UserRepository($app->pdo, $app->bot['id']);
        $post_repo = new PostPackageRepository($app->pdo);

        try {
            $app->pdo->beginTransaction();

            // Ban the user
            $user_repo->updateUserStatusByTelegramId($user_to_ban_id, 'blocked');

            // Reject the associated post if there is one
            if ($public_id) {
                $post = $post_repo->findByPublicId($public_id);
                if ($post && $post['status'] === 'pending') {
                    $post_repo->updateStatus($post['id'], 'rejected');
                }
            }

            // Notify the user
            $notification_text = "Anda telah diblokir dari bot.\n\nAlasan: *{$reason}*";
            $app->telegram_api->sendMessage($user_to_ban_id, $notification_text, 'Markdown');

            // Update the message in the admin channel
            $admin_username = $callback_query['from']['username'] ?? $callback_query['from']['first_name'];
            $new_admin_text = $callback_query['message']['text'] . "\n\n---\n*Pengguna diblokir oleh @{$admin_username}*\nAlasan: {$reason}";

            $app->telegram_api->editMessageText(
                $callback_query['message']['chat']['id'],
                $callback_query['message']['message_id'],
                $new_admin_text,
                'Markdown'
            );

            $app->pdo->commit();
            $app->telegram_api->answerCallbackQuery($callback_query['id'], "Pengguna {$user_to_ban_id} telah diblokir.");

        } catch (Exception $e) {
            if ($app->pdo->inTransaction()) {
                $app->pdo->rollBack();
            }
            $app->telegram_api->answerCallbackQuery($callback_query['id'], "⚠️ Gagal: " . $e->getMessage(), true);
        }
    }

    /**
     * Handle an admin's request to approve a post.
     *
     * @param App $app
     * @param array $callback_query
     * @param string $public_id
     * @return void
     */
    private function handleAdminApproval(App $app, array $callback_query, string $public_id): void
    {
        $post_repo = new PostPackageRepository($app->pdo);
        $channel_post_repo = new ChannelPostPackageRepository($app->pdo);

        try {
            $app->pdo->beginTransaction();

            $post = $post_repo->findByPublicId($public_id);
            if (!$post) {
                throw new Exception("Post tidak ditemukan.");
            }

            if ($post['status'] !== 'pending') {
                throw new Exception("Post ini tidak lagi dalam status pending.");
            }

            // 1. Approve the post
            $post_repo->updateStatus($post['id'], 'available');

            // 2. Get public channel ID
            $post_type = $post['post_type'];
            $category = $post['category'];

            $stmt_settings = $app->pdo->prepare("SELECT setting_value FROM bot_settings WHERE bot_id = ? AND setting_key = ?");
            $setting_key = 'public_channel_' . $post_type . '_' . $category;
            $stmt_settings->execute([$app->bot['id'], $setting_key]);
            $public_channel_id = $stmt_settings->fetchColumn();

            if (!$public_channel_id) {
                throw new Exception("Public channel not configured for setting_key: " . $setting_key);
            }

            // 3. Forward/Send the post to the public channel
            $public_post = null;
            if ($post_type === 'rate') {
                $media_file = $post_repo->getThumbnailFile($post['id']);
                if ($media_file) {
                    $public_post = $app->telegram_api->copyMessage($public_channel_id, $media_file['storage_channel_id'], $media_file['storage_message_id']);
                }
            } else { // tanya
                $public_post = $app->telegram_api->sendMessage($public_channel_id, $post['description']);
            }

            // 4. Send notification to user
            if ($public_post && $public_post['ok']) {
                $user_id = $post['seller_user_id'];
                $public_post_link = "https://t.me/c/" . substr($public_channel_id, 4) . "/" . $public_post['result']['message_id'];
                $message = "🎉 Kabar baik! Kiriman Anda telah disetujui dan dipublikasikan.\n\nLihat di sini: " . $public_post_link;

                $keyboard = ['inline_keyboard' => [[['text' => 'Tarik Post', 'callback_data' => 'retract_post_' . $public_id]]]];

                $app->telegram_api->sendMessage($user_id, $message, null, json_encode($keyboard));

                // Link the public post to the package
                $channel_post_repo->create($public_channel_id, $public_post['result']['message_id'], $post['id']);
            }

            // 5. Update the message in the admin channel
            $admin_username = $callback_query['from']['username'] ?? $callback_query['from']['first_name'];
            $new_admin_text = $callback_query['message']['text'] . "\n\n---\n*Disetujui oleh @{$admin_username}*";

            $app->telegram_api->editMessageText(
                $callback_query['message']['chat']['id'],
                $callback_query['message']['message_id'],
                $new_admin_text,
                'Markdown'
            );

            $app->pdo->commit();
            $app->telegram_api->answerCallbackQuery($callback_query['id'], "Post {$public_id} disetujui.");

        } catch (Exception $e) {
            if ($app->pdo->inTransaction()) {
                $app->pdo->rollBack();
            }
            $app->telegram_api->answerCallbackQuery($callback_query['id'], "⚠️ Gagal: " . $e->getMessage(), true);
        }
    }
}
