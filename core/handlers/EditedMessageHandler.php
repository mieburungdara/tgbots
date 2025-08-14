<?php

class EditedMessageHandler
{
    private $pdo;
    private $edited_message;

    public function __construct(PDO $pdo, array $edited_message)
    {
        $this->pdo = $pdo;
        $this->edited_message = $edited_message;
    }

    public function handle()
    {
        // We only care about edits that have text/caption content.
        // For edited media captions, the new caption is in the 'caption' field.
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
