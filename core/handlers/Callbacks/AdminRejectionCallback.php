<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;
use TGBot\Database\MediaPackageRepository;
use Exception;

class AdminRejectionCallback implements CallbackCommandInterface
{
    public function execute(App $app, array $callback_query, string $params): void
    {
        $parts = explode('_', $params);
        $public_id = $parts[0];
        $reason = $parts[1] ?? 'Tidak ada alasan spesifik';

        $post_repo = new MediaPackageRepository($app->pdo);

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
}
