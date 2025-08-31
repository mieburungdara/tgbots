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

/**
 * Class UpdateHandler
 * @package TGBot\Handlers
 */
class UpdateHandler
{
    /**
     * @var array
     */
    private array $bot_settings;

    /**
     * UpdateHandler constructor.
     *
     * @param array $bot_settings
     */
    public function __construct(array $bot_settings)
    {
        $this->bot_settings = $bot_settings;
    }

    /**
     * Get the update type.
     *
     * @param array $update
     * @return string|null
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
        } elseif (isset($update['channel_post'])) {
            $update_type = 'channel_post';
            // Selalu proses channel post yang merupakan perintah
            if (isset($update['channel_post']['text']) && strpos($update['channel_post']['text'], '/') === 0) {
                return $update_type;
            }
            return null; // Abaikan channel post biasa
        } elseif (isset($update['inline_query'])) {
            return 'inline_query';
        }

        // Jika jenis update tidak didukung atau dinonaktifkan oleh admin, kembalikan null.
        if ($setting_to_check === null || empty($this->bot_settings[$setting_to_check])) {
            return null;
        }

        return $update_type;
    }

    /**
     * Get the message context.
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
        } elseif (isset($update['channel_post'])) {
            return $update['channel_post'];
        }
        return null;
    }

    /**
     * Check if a message is a media message.
     *
     * @param array $message
     * @return bool
     */
    public static function isMediaMessage(array $message): bool
    {
        $media_keys = ['photo', 'video', 'audio', 'voice', 'document', 'animation', 'video_note'];
        foreach ($media_keys as $key) {
            if (isset($message[$key])) {
                return true;
            }
        }
        return false;
    }
}
