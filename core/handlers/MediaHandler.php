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

use TGBot\App;
use TGBot\Handlers\HandlerInterface;
use TGBot\Database\MediaFileRepository;

/**
 * Class MediaHandler
 * @package TGBot\Handlers
 */
class MediaHandler implements HandlerInterface
{
    /**
     * Handle a media message.
     *
     * @param App $app
     * @param array $message
     * @return void
     */
    public function handle(App $app, array $message): void
    {
        $media_repo = new MediaFileRepository($app->pdo);

        $media_type = null;
        $media_info = null;
        $media_keys = ['photo', 'video', 'document', 'audio', 'voice', 'animation', 'video_note'];

        foreach ($media_keys as $key) {
            if (isset($message[$key])) {
                $media_type = $key;
                // Untuk foto, ambil resolusi tertinggi (elemen terakhir array)
                $media_info = ($key === 'photo') ? end($message['photo']) : $message[$key];
                break;
            }
        }

        if ($media_type && $media_info) {
            $params = [
                'type' => $media_type,
                'file_size' => $media_info['file_size'] ?? null,
                'width' => $media_info['width'] ?? null,
                'height' => $media_info['height'] ?? null,
                'duration' => $media_info['duration'] ?? null,
                'mime_type' => $media_info['mime_type'] ?? null,
                'file_name' => $media_info['file_name'] ?? null,
                'caption' => $message['caption'] ?? null,
                'caption_entities' => $message['caption_entities'] ?? null,
                'user_id' => $app->user['id'],
                'chat_id' => $app->chat_id,
                'message_id' => $message['message_id'],
                'media_group_id' => $message['media_group_id'] ?? null,
                'has_spoiler' => $message['has_media_spoiler'] ?? false
            ];

            // Save initial media file info
            $media_file_id = $media_repo->saveMediaFile($params);

            // Attempt to copy media to storage channel
            $bot_channel_usage_repo = new \TGBot\Database\BotChannelUsageRepository($app->pdo);
            $storage_channel_info = $bot_channel_usage_repo->getNextChannelForBot((int)$app->bot['id']);

            if ($storage_channel_info) {
                $storage_channel_id = $storage_channel_info['channel_id'];
                App::getLogger()->info("[MediaHandler] Copying message " . $message['message_id'] . " from chat " . $app->chat_id . " to storage channel " . $storage_channel_id);

                $copied_message_response = $app->telegram_api->copyMessage(
                    $storage_channel_id,
                    $app->chat_id,
                    $message['message_id']
                );

                if ($copied_message_response && $copied_message_response['ok']) {
                    $copied_message_id = $copied_message_response['result']['message_id'];
                    $media_repo->updateStorageInfo($media_file_id, $storage_channel_id, $copied_message_id);
                    App::getLogger()->info("[MediaHandler] Successfully copied message to storage. media_file_id: " . $media_file_id . ", storage_channel_id: " . $storage_channel_id . ", storage_message_id: " . $copied_message_id);
                } else {
                    App::getLogger()->error("[MediaHandler] Failed to copy message to storage channel. Response: " . json_encode($copied_message_response));
                }
            } else {
                App::getLogger()->warning("[MediaHandler] No storage channel found for bot ID: " . $app->bot['id']);
            }
        }
    }
}
