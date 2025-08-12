<?php

class UpdateHandler
{
    private $bot_settings;

    public function __construct(array $bot_settings)
    {
        $this->bot_settings = $bot_settings;
    }

    /**
     * Determines the type of the update and checks if it should be processed.
     *
     * @param array $update The incoming update from Telegram.
     * @return string|null The type of the update, or null if it should be ignored.
     */
    public function getUpdateType(array $update): ?string
    {
        $setting_to_check = null;
        $update_type = null;

        if (isset($update['message'])) {
            $update_type = 'message';
            $message_context = $update['message'];
            $media_keys = ['photo', 'video', 'audio', 'voice', 'document', 'animation', 'video_note'];
            $is_media = false;
            foreach ($media_keys as $key) {
                if (isset($message_context[$key])) {
                    $is_media = true;
                    break;
                }
            }
            $setting_to_check = $is_media ? 'save_media_messages' : 'save_text_messages';
        } elseif (isset($update['edited_message'])) {
            $update_type = 'edited_message';
            $setting_to_check = 'save_edited_messages';
        } elseif (isset($update['callback_query'])) {
            $update_type = 'callback_query';
            $setting_to_check = 'save_callback_queries';
        }

        // Jika jenis update tidak didukung atau dinonaktifkan oleh admin, kembalikan null.
        if ($setting_to_check === null || empty($this->bot_settings[$setting_to_check])) {
            return null;
        }

        return $update_type;
    }

    /**
     * Extracts the primary context from the update (e.g., the message object).
     *
     * @param array $update
     * @return array|null
     */
    public static function getMessageContext(array $update): ?array
    {
        if (isset($update['message'])) {
            return $update['message'];
        } elseif (isset($update['edited_message'])) {
            return $update['edited_message'];
        } elseif (isset($update['callback_query'])) {
            $message_context = $update['callback_query']['message'];
            // Inject necessary data from the callback query itself into the context
            $message_context['from'] = $update['callback_query']['from']; // User who clicked
            $message_context['text'] = "Callback: " . ($update['callback_query']['data'] ?? ''); // Store callback data
            $message_context['date'] = time(); // Time the button was clicked
            return $message_context;
        }
        return null;
    }
}
