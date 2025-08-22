<?php

require_once __DIR__ . '/../database/MediaFileRepository.php';

/**
 * Menangani pesan yang berisi media (foto, video, dokumen, dll.).
 * Tugas utamanya adalah mengekstrak metadata media dan menyimpannya ke database.
 */
class MediaHandler
{
    private $pdo;
    private $message;
    private $user_id_from_telegram;
    private $chat_id_from_telegram;
    private $telegram_message_id;
    private $media_repo;

    /**
     * Membuat instance MediaHandler.
     *
     * @param PDO $pdo Objek koneksi database.
     * @param array $message Data pesan lengkap dari Telegram.
     * @param int $user_id_from_telegram ID Telegram pengguna yang mengirim media.
     * @param int $chat_id_from_telegram ID obrolan tempat media dikirim.
     * @param int $telegram_message_id ID pesan media di Telegram.
     */
    public function __construct(PDO $pdo, array $message, int $user_id_from_telegram, int $chat_id_from_telegram, int $telegram_message_id)
    {
        $this->pdo = $pdo;
        $this->message = $message;
        $this->user_id_from_telegram = $user_id_from_telegram;
        $this->chat_id_from_telegram = $chat_id_from_telegram;
        $this->telegram_message_id = $telegram_message_id;
        $this->media_repo = new MediaFileRepository($pdo);
    }

    /**
     * Titik masuk utama untuk menangani pesan media.
     * Mengidentifikasi jenis media, mengumpulkan metadata, dan menyimpannya.
     */
    public function handle()
    {
        $media_type = null;
        $media_info = null;
        $media_keys = ['photo', 'video', 'document', 'audio', 'voice', 'animation', 'video_note'];

        foreach ($media_keys as $key) {
            if (isset($this->message[$key])) {
                $media_type = $key;
                $media_info = ($key === 'photo') ? end($this->message['photo']) : $this->message[$key];
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
                'caption' => $this->message['caption'] ?? null,
                'caption_entities' => $this->message['caption_entities'] ?? null,
                'user_id' => $this->user_id_from_telegram,
                'chat_id' => $this->chat_id_from_telegram,
                'message_id' => $this->telegram_message_id,
                'media_group_id' => $this->message['media_group_id'] ?? null,
                'has_spoiler' => $this->message['has_media_spoiler'] ?? false
            ];

            $this->media_repo->saveMediaFile($params);
        }
    }
}
