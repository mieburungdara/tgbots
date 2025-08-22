<?php

/**
 * Menangani pembaruan ketika sebuah pesan diedit.
 * Saat ini, fokus utamanya adalah memperbarui caption dari media yang telah diedit.
 */
class EditedMessageHandler
{
    private $pdo;
    private $edited_message;

    /**
     * Membuat instance EditedMessageHandler.
     *
     * @param PDO $pdo Objek koneksi database.
     * @param array $edited_message Data pesan yang diedit dari Telegram.
     */
    public function __construct(PDO $pdo, array $edited_message)
    {
        $this->pdo = $pdo;
        $this->edited_message = $edited_message;
    }

    /**
     * Titik masuk utama untuk menangani pesan yang diedit.
     * Memeriksa adanya caption baru dan memperbarui catatan media di database.
     */
    public function handle()
    {
        // Kita hanya peduli pada editan yang memiliki konten teks/caption.
        // Untuk caption media yang diedit, caption baru ada di field 'caption'.
        if (!isset($this->edited_message['caption'])) {
            return;
        }

        $message_id = $this->edited_message['message_id'];
        $chat_id = $this->edited_message['chat']['id'];
        $new_caption = $this->edited_message['caption'];

        try {
            // Find the corresponding media_file and update its caption.
            // We match on both message_id and chat_id to be safe.
            $stmt = $this->pdo->prepare(
                "UPDATE media_files
                 SET caption = ?
                 WHERE message_id = ? AND chat_id = ?"
            );

            $stmt->execute([$new_caption, $message_id, $chat_id]);

            // Optional: Log that the caption was updated.
            if ($stmt->rowCount() > 0) {
                app_log("Updated caption for media in message {$message_id} in chat {$chat_id}.", 'info');
            }

        } catch (Exception $e) {
            app_log("Error updating caption for edited message: " . $e->getMessage(), 'error');
        }
    }
}
