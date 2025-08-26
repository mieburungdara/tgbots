<?php

require_once __DIR__ . '/HandlerInterface.php';

/**
 * Menangani pembaruan ketika sebuah pesan diedit.
 * Saat ini, fokus utamanya adalah memperbarui caption dari media yang telah diedit.
 */
class EditedMessageHandler implements HandlerInterface
{
    /**
     * Titik masuk utama untuk menangani pesan yang diedit.
     *
     * @param App $app Wadah aplikasi.
     * @param array $edited_message Data pesan yang diedit dari Telegram.
     */
    public function handle(App $app, array $edited_message): void
    {
        // Kita hanya peduli pada editan yang mengubah caption media.
        if (!isset($edited_message['caption'])) {
            return;
        }

        $message_id = $edited_message['message_id'];
        $chat_id = $edited_message['chat']['id'];
        $new_caption = $edited_message['caption'];

        try {
            // Cari file media yang sesuai dan perbarui caption-nya.
            $stmt = $app->pdo->prepare(
                "UPDATE media_files SET caption = :caption WHERE message_id = :message_id AND chat_id = :chat_id"
            );

            $stmt->execute([
                ':caption' => $new_caption,
                ':message_id' => $message_id,
                ':chat_id' => $chat_id
            ]);

            if ($stmt->rowCount() > 0) {
                app_log("Updated caption for media in message {$message_id} in chat {$chat_id}.", 'info');
            }

        } catch (Exception $e) {
            app_log("Error updating caption for edited message: " . $e->getMessage(), 'error');
        }
    }
}
