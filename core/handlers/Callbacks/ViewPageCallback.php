<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;
use TGBot\Database\MediaPackageRepository;
use TGBot\Database\SaleRepository;

class ViewPageCallback implements CallbackCommandInterface
{
    public function execute(App $app, array $callback_query, string $params): void
    {
        $package_repo = new MediaPackageRepository($app->pdo);
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
        $sale_details = $sale_repo->getSaleDetails($package['id'], $app->user['id']);

        // Check if it's a gift and needs claiming
        if ($sale_details && $sale_details['granted_to_user_id'] == $app->user['id'] && $sale_details['buyer_user_id'] != $app->user['id']) {
            // It's a gift
            if ($sale_details['claimed_at'] === null) {
                // Gift not yet claimed
                if ($sale_details['expires_at'] !== null && strtotime($sale_details['expires_at']) < time()) {
                    // Gift expired
                    $app->telegram_api->answerCallbackQuery($callback_query['id'], '⚠️ Hadiah ini sudah kadaluarsa dan tidak dapat diklaim.', true);
                    return;
                } else {
                    // Gift can be claimed
                    $app->telegram_api->answerCallbackQuery($callback_query['id'], '🎁 Hadiah belum diklaim. Klik "Klaim Hadiah" atau "Hadiah Ulang".', true);
                    $keyboard = [
                        ['text' => 'Klaim Hadiah 🎁', 'callback_data' => "claim_gift_{$package['public_id']}"],
                        ['text' => 'Hadiah Ulang 🔄', 'callback_data' => "re_gift_{$package['public_id']}"]
                    ];
                    $app->telegram_api->editMessageText(
                        $app->chat_id,
                        $callback_query['message']['message_id'],
                        "Anda menerima hadiah konten `{$package['public_id']}`. Silakan klaim hadiah Anda atau hadiah ulang kepada teman.",
                        'Markdown',
                        json_encode(['inline_keyboard' => [$keyboard]])
                    );
                    return;
                }
            }
        }

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
}
