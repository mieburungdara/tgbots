<?php

namespace TGBot\Handlers;

use TGBot\App;
use TGBot\Database\UserRepository;
use TGBot\Database\MediaPackageRepository;

class RateHandler
{
    public function handle(App $app, array $callback_query)
    {
        $data = $callback_query['data'];

        if (strpos($data, 'rate_category_') === 0) {
            $this->handleCategorySelection($app, $callback_query);
        } elseif (strpos($data, 'rate_confirm_') === 0) {
            $this->handleConfirmation($app, $callback_query);
        } elseif ($data === 'rate_cancel') {
            $this->handleCancellation($app, $callback_query);
        }
    }

    private function handleCategorySelection(App $app, array $callback_query)
    {
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);
        $state_context = json_decode($app->user['state_context'] ?? '{}', true);
        $category = substr($callback_query['data'], strlen('rate_category_'));
        $state_context['category'] = $category;
        $user_repo->setUserState($app->user['id'], 'awaiting_rate_confirmation', $state_context);

        $message_id = $state_context['message_id'];
        $chat_id = $state_context['chat_id'];

        // Get media info
        $stmt = $app->pdo->prepare("SELECT * FROM media_files WHERE message_id = ? AND chat_id = ?");
        $stmt->execute([$message_id, $chat_id]);
        $media_file = $stmt->fetch();

        $media_type = $media_file['file_type'];
        $media_count = 1;
        if ($media_file['media_group_id']) {
            $stmt = $app->pdo->prepare("SELECT COUNT(*) FROM media_files WHERE media_group_id = ?");
            $stmt->execute([$media_file['media_group_id']]);
            $media_count = $stmt->fetchColumn();
        }

        $media_info = "{$media_count}" . ($media_type === 'photo' ? 'P' : 'V');


        $text = "<b>Preview Post</b>\n\n";
        $text .= "<b>Kategori:</b> " . ucfirst($category) . "\n";
        $text .= "<b>Info:</b> <code>" . $app->user['id'] . "</code> | <code>" . $media_info . "</code>\n\n";
        $text .= "Silakan konfirmasi untuk melanjutkan.";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Konfirmasi', 'callback_data' => 'rate_confirm_' . $category],
                    ['text' => '❌ Batal', 'callback_data' => 'rate_cancel'],
                ]
            ]
        ];

        // First, delete the category selection message
        $app->telegram_api->deleteMessage($app->chat_id, $callback_query['message']['message_id']);

        // Then, send the confirmation message as a reply to the original media
        $app->telegram_api->sendMessage(
            $app->chat_id,
            $text,
            'HTML',
            json_encode($keyboard),
            $state_context['message_id']
        );
    }

    private function handleConfirmation(App $app, array $callback_query)
    {
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);
        $post_repo = new MediaPackageRepository($app->pdo);
        $state_context = json_decode($app->user['state_context'] ?? '{}', true);

        $category = $state_context['category'];
        $message_id = $state_context['message_id'];
        $chat_id = $state_context['chat_id'];

        // 1. Get media file info
        $stmt = $app->pdo->prepare("SELECT * FROM media_files WHERE message_id = ? AND chat_id = ?");
        $stmt->execute([$message_id, $chat_id]);
        $media_file = $stmt->fetch();

        // 2. Create a new post package
        $package_id = $post_repo->createPackageWithPublicId(
            $app->user['id'],
            $app->bot['id'],
            $media_file['caption'] ?? '',
            $media_file['id'],
            'rate',
            $category
        );

        // 3. Update the package_id for the media files
        if ($media_file['media_group_id']) {
            $stmt = $app->pdo->prepare("UPDATE media_files SET package_id = ? WHERE media_group_id = ?");
            $stmt->execute([$package_id, $media_file['media_group_id']]);
        } else {
            $stmt = $app->pdo->prepare("UPDATE media_files SET package_id = ? WHERE id = ?");
            $stmt->execute([$package_id, $media_file['id']]);
        }

        // 4. Send to admin channel for moderation
        $admin_channel_id = $app->bot_settings['admin_channel_rate_' . $category] ?? null;
        if ($admin_channel_id) {
            $post = $post_repo->find($package_id);
            $public_id = $post['public_id'];
            $user_id = $app->user['id'];

            $media_type = $media_file['file_type'];
            $media_count = 1;
            if ($media_file['media_group_id']) {
                $stmt = $app->pdo->prepare("SELECT COUNT(*) FROM media_files WHERE media_group_id = ?");
                $stmt->execute([$media_file['media_group_id']]);
                $media_count = $stmt->fetchColumn();
            }
            $media_info = "{$media_count}" . ($media_type === 'photo' ? 'P' : 'V');

            $caption = "<b>Kiriman Baru untuk Moderasi</b>\n\n";
            $caption .= "<b>ID Post:</b> <code>{$public_id}</code>\n";
            $caption .= "<b>User:</b> <code>{$user_id}</code>\n";
            $caption .= "<b>Info:</b> <code>{$media_info}</code>\n";
            $caption .= "<b>Kategori:</b> " . ucfirst($category) . "\n\n";
            if (!empty($media_file['caption'])) {
                $caption .= "<b>Caption Asli:</b>\n<i>" . htmlspecialchars($media_file['caption']) . "</i>";
            }

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '✅ Setujui', 'callback_data' => 'admin_approve_' . $public_id],
                    ],
                    [
                        ['text' => '❌ Tolak (Iklan)', 'callback_data' => 'admin_reject_' . $public_id . '_Iklan'],
                        ['text' => '❌ Tolak (Judi)', 'callback_data' => 'admin_reject_' . $public_id . '_Judi'],
                    ],
                    [
                        ['text' => '❌ Tolak (Kekerasan)', 'callback_data' => 'admin_reject_' . $public_id . '_Kekerasan'],
                        ['text' => '❌ Tolak (Duplikat)', 'callback_data' => 'admin_reject_' . $public_id . '_Duplikat'],
                    ],
                    [
                        ['text' => '🚫 Blokir (Spam)', 'callback_data' => 'admin_ban_' . $user_id . '_' . $public_id . '_Spam'],
                        ['text' => '🚫 Blokir (Judi)', 'callback_data' => 'admin_ban_' . $user_id . '_' . $public_id . '_Judi_Online'],
                    ]
                ]
            ];

            $app->telegram_api->copyMessage(
                $admin_channel_id,
                $chat_id,
                $message_id,
                $caption,
                'HTML',
                json_encode($keyboard)
            );
        }

        // 5. Send a confirmation message to the user
        $app->telegram_api->editMessageText(
            $app->chat_id,
            $callback_query['message']['message_id'],
            "✅ Terima kasih! Kiriman Anda telah diteruskan ke moderator dan akan segera diproses."
        );

        // 6. Clear the user state
        $user_repo->setUserState($app->user['id'], null, null);
    }

    private function handleCancellation(App $app, array $callback_query)
    {
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);
        $user_repo->setUserState($app->user['id'], null, null);

        $app->telegram_api->editMessageText(
            $app->chat_id,
            $callback_query['message']['message_id'],
            "Operasi dibatalkan."
        );
    }
}
