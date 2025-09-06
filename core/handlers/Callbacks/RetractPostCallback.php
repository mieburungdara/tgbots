<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;
use TGBot\Database\MediaPackageRepository;
use TGBot\Database\ChannelPostPackageRepository;
use Exception;

class RetractPostCallback implements CallbackCommandInterface
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
}
