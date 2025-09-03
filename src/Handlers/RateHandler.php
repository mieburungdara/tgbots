<?php

namespace TGBot\Handlers;

use TGBot\App;
use TGBot\Database\UserRepository;

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
        // TODO: Implement this
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
