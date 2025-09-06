<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;
use TGBot\Database\UserRepository;
use TGBot\Database\MediaPackageRepository;
use Exception;

class AdminBanCallback implements CallbackCommandInterface
{
    public function execute(App $app, array $callback_query, string $params): void
    {
        $parts = explode('_', $params);
        $user_to_ban_id = (int)$parts[0];
        $public_id = $parts[1] ?? null;
        $reason = $parts[2] ?? 'Pelanggaran berat terhadap aturan.';

        $user_repo = new UserRepository($app->pdo, $app->bot['id']);
        $post_repo = new MediaPackageRepository($app->pdo);

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
}
