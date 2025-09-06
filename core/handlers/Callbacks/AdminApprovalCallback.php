<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;
use TGBot\Database\MediaPackageRepository;
use TGBot\Database\ChannelPostPackageRepository;
use Exception;

class AdminApprovalCallback implements CallbackCommandInterface
{
    public function execute(App $app, array $callback_query, string $public_id): void
    {
        $post_repo = new MediaPackageRepository($app->pdo);
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

            $post_repo->updateStatus($post['id'], 'available');

            $post_type = $post['post_type'];
            $category = $post['category'];

            $stmt_settings = $app->pdo->prepare("SELECT setting_value FROM bot_settings WHERE bot_id = ? AND setting_key = ?");
            $setting_key = 'public_channel_' . $post_type . '_' . $category;
            $stmt_settings->execute([$app->bot['id'], $setting_key]);
            $public_channel_id = $stmt_settings->fetchColumn();

            if (!$public_channel_id) {
                throw new Exception("Public channel not configured for setting_key: " . $setting_key);
            }

            $public_post = null;
            if ($post_type === 'rate') {
                $media_file = $post_repo->getThumbnailFile($post['id']);
                if ($media_file) {
                    $public_post = $app->telegram_api->copyMessage($public_channel_id, $media_file['storage_channel_id'], $media_file['storage_message_id']);
                }
            } else { // tanya
                $public_post = $app->telegram_api->sendMessage($public_channel_id, $post['description']);
            }

            if ($public_post && $public_post['ok']) {
                $user_id = $post['seller_user_id'];
                $public_post_link = "https://t.me/c/" . substr($public_channel_id, 4) . "/" . $public_post['result']['message_id'];
                $message = "🎉 Kabar baik! Kiriman Anda telah disetujui dan dipublikasikan.\n\nLihat di sini: " . $public_post_link;

                $keyboard = ['inline_keyboard' => [[['text' => 'Tarik Post', 'callback_data' => 'retract_post_' . $public_id]]]];

                $app->telegram_api->sendMessage($user_id, $message, null, json_encode($keyboard));

                $channel_post_repo->create($public_channel_id, $public_post['result']['message_id'], $post['id']);
            }

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
