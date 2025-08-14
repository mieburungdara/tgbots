<?php

class MediaFileRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Simpan file media baru ke database.
     *
     * @param array $params Parameter untuk file media.
     * @return string ID dari baris yang baru dimasukkan.
     */
    public function saveMediaFile(array $params): string
    {
        $sql = "INSERT INTO media_files (type, file_size, width, height, duration, mime_type, file_name, caption, caption_entities, user_id, chat_id, message_id, media_group_id, has_spoiler)
                VALUES (:type, :file_size, :width, :height, :duration, :mime_type, :file_name, :caption, :caption_entities, :user_id, :chat_id, :message_id, :media_group_id, :has_spoiler)";

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
            ':has_spoiler' => $params['has_spoiler'] ?? false
        ]);

        return $this->pdo->lastInsertId();
    }
}
