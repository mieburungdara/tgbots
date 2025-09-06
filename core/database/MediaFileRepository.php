<?php

/**
 * This file is part of the TGBot package.
 *
 * (c) Zidin Mitra Abadi <zidinmitra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TGBot\Database;

use PDO;

/**
 * Class MediaFileRepository
 * @package TGBot\Database
 */
class MediaFileRepository
{
    /**
     * @var PDO
     */
    private PDO $pdo;

    /**
     * MediaFileRepository constructor.
     *
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Save a new media file.
     *
     * @param array $params
     * @return string
     */
    public function saveMediaFile(array $params): string
    {
        $sql = "INSERT INTO media_files (type, file_size, width, height, duration, mime_type, file_name, caption, caption_entities, user_id, chat_id, message_id, media_group_id, has_spoiler, storage_channel_id, storage_message_id)
                VALUES (:type, :file_size, :width, :height, :duration, :mime_type, :file_name, :caption, :caption_entities, :user_id, :chat_id, :message_id, :media_group_id, :has_spoiler, :storage_channel_id, :storage_message_id)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':type' => $params['type'],
            ':file_size' => $params['file_size'] ?? null,
            ':width' => $params['width'] ?? null,
            ':height' => $params['height'] ?? null,
            ':duration' => $params['duration'] ?? null,
            ':mime_type' => $params['mime_type'] ?? null,
            ':file_name' => $params['file_name'] ?? null,
            ':caption' => $params['caption'] ?? null,
            ':caption_entities' => $params['caption_entities'] ? json_encode($params['caption_entities']) : null,
            ':user_id' => $params['user_id'],
            ':chat_id' => $params['chat_id'],
            ':message_id' => $params['message_id'],
            ':media_group_id' => $params['media_group_id'] ?? null,
            ':has_spoiler' => $params['has_spoiler'] ?? false,
            ':storage_channel_id' => $params['storage_channel_id'] ?? null,
            ':storage_message_id' => $params['storage_message_id'] ?? null
        ]);

        return $this->pdo->lastInsertId();
    }

    /**
     * Update storage information for a media file.
     *
     * @param int $mediaFileId
     * @param string $storageChannelId
     * @param int $storageMessageId
     * @return bool
     */
    public function updateStorageInfo(int $mediaFileId, string $storageChannelId, int $storageMessageId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE media_files SET storage_channel_id = ?, storage_message_id = ? WHERE id = ?");
        return $stmt->execute([$storageChannelId, $storageMessageId, $mediaFileId]);
    }
}
