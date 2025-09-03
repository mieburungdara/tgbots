<?php

namespace TGBot\Handlers;

use TGBot\App;
use TGBot\Database\UserRepository;
use TGBot\Database\PostPackageRepository;

class TanyaHandler
{
    public function handle(App $app, array $callback_query)
    {
        $data = $callback_query['data'];

        if (strpos($data, 'tanya_category_') === 0) {
            $this->handleCategorySelection($app, $callback_query);
        } elseif (strpos($data, 'tanya_confirm_') === 0) {
            $this->handleConfirmation($app, $callback_query);
        } elseif ($data === 'tanya_cancel') {
            $this->handleCancellation($app, $callback_query);
        }
    }

    private function handleCategorySelection(App $app, array $callback_query)
    {
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);
        $state_context = json_decode($app->user['state_context'] ?? '{}', true);
        $category = substr($callback_query['data'], strlen('tanya_category_'));
        $state_context['category'] = $category;
        $user_repo->setUserState($app->user['id'], 'awaiting_tanya_confirmation', $state_context);

        $text = "<b>Preview Post</b>\n\n";
        $text .= "<b>Kategori:</b> " . ucfirst($category) . "\n\n";
        $text .= "<b>Pertanyaan:</b>\n";
        $text .= "<i>" . htmlspecialchars($state_context['text']) . "</i>\n\n";
        $text .= "<b>Aturan:</b> Dilarang iklan, judi, dan kekerasan.\n\n";
        $text .= "Silakan konfirmasi untuk melanjutkan.";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Konfirmasi', 'callback_data' => 'tanya_confirm_' . $category],
                    ['text' => '❌ Batal', 'callback_data' => 'tanya_cancel'],
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
        $post_repo = new PostPackageRepository($app->pdo);
        $state_context = json_decode($app->user['state_context'] ?? '{}', true);

        $category = $state_context['category'];
        $message_id = $state_context['message_id'];
        $chat_id = $state_context['chat_id'];
        $text = $state_context['text'];

        // 1. Create a new post package
        $package_id = $post_repo->createPackageWithPublicId(
            $app->user['id'],
            $app->bot['id'],
            $text,
            0, // No thumbnail for text-based posts
            'tanya',
            $category
        );

        // 2. Send to admin channel for moderation
        $admin_channel_id = $app->bot_settings['admin_channel_tanya_' . $category] ?? null;
        if ($admin_channel_id) {
            $post = $post_repo->find($package_id);
            $public_id = $post['public_id'];
            $user_id = $app->user['id'];

            $caption = "<b>Pertanyaan Baru untuk Moderasi</b>\n\n";
            $caption .= "<b>ID Post:</b> <code>{$public_id}</code>\n";
            $caption .= "<b>User:</b> <code>{$user_id}</code>\n";
            $caption .= "<b>Kategori:</b> " . ucfirst($category) . "\n\n";
            $caption .= "<b>Pertanyaan:</b>\n<i>" . htmlspecialchars($text) . "</i>";

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
                        ['text' => '🚫 Blokir (Spam)', 'callback_data' => 'admin_ban_' . $user_id . '_' . $public_id . '_Spam'],
                    ]
                ]
            ];

            $app->telegram_api->sendMessage(
                $admin_channel_id,
                $caption,
                'HTML',
                json_encode($keyboard)
            );
        }

        // 3. Send a confirmation message to the user
        $app->telegram_api->editMessageText(
            $app->chat_id,
            $callback_query['message']['message_id'],
            "✅ Terima kasih! Pertanyaan Anda telah diteruskan ke moderator dan akan segera diproses."
        );

        // 4. Clear the user state
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
