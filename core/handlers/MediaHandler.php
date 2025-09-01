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

            $media_repo->saveMediaFile($params);
        }
    }
}
