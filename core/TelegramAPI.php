<?php

/**
 * This file is part of the TGBot package.
 *
 * (c) Zidin Mitra Abadi <zidinmitra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TGBot;

use Exception;
use PDO;
use TGBot\Database\TelegramErrorLogRepository;
use TGBot\Database\UserRepository;
use Monolog\Logger;
use TGBot\App;
use TGBot\Database\BotRepository;

/**
 * Class TelegramAPI
 * @package TGBot
 */
class TelegramAPI
{
    /**
     * @var string
     */
    protected string $token;

    /**
     * @var string
     */
    protected string $api_url = 'https://api.telegram.org/bot';

    /**
     * @var PDO|null
     */
    protected ?PDO $pdo;

    /**
     * @var TelegramErrorLogRepository|null
     */
    protected ?TelegramErrorLogRepository $errorLogRepo;

    /**
     * @var UserRepository|null
     */
    protected ?UserRepository $userRepo;

    /**
     * @var int|null
     */
    protected ?int $bot_id;

    /**
     * @var Logger
     */
    protected Logger $logger;

    /**
     * @var \TGBot\Database\BotRepository|null
     */
    protected ?\TGBot\Database\BotRepository $botRepo = null;

    /**
     * @var int Konter kegagalan berurutan untuk circuit breaker.
     */
    protected int $failure_count;

    /**
     * @var int Waktu (timestamp) kapan circuit breaker akan terbuka kembali.
     */
    protected int $circuit_breaker_open_until;

    /**
     * @var int Jumlah kegagalan maksimum sebelum circuit breaker terbuka.
     */
    protected const MAX_FAILURES = 5;

    /**
     * @var int Durasi (detik) circuit breaker akan tetap terbuka.
     */
    protected const RESET_TIMEOUT = 300; // 5 menit

    /**
     * TelegramAPI constructor.
     *
     * @param string $token
     * @param PDO|null $pdo
     * @param int|null $internal_bot_id
     * @param Logger|null $logger
     * @param array|null $bot_data Data bot dari database, termasuk status circuit breaker.
     */
    public function __construct(string $token, ?PDO $pdo = null, ?int $internal_bot_id = null, ?Logger $logger = null, ?array $bot_data = null)
    {
        $this->token = $token;
        $this->pdo = $pdo;
        $this->bot_id = $internal_bot_id;
        $this->logger = $logger ?? App::getLogger();

        if ($this->pdo) {
            $this->errorLogRepo = new TelegramErrorLogRepository($this->pdo);
            if ($this->bot_id) {
                $this->userRepo = new UserRepository($this->pdo, $this->bot_id);
                $this->botRepo = new \TGBot\Database\BotRepository($this->pdo);
            }
        }

        // Load persistent circuit breaker state
        $this->failure_count = $bot_data['failure_count'] ?? 0;
        $this->circuit_breaker_open_until = $bot_data['circuit_breaker_open_until'] ?? 0;
    }

    /**
     * Send a request to the Telegram API.
     *
     * @param string $method
     * @param array $data
     * @return array
     */
    protected function apiRequest(string $method, array $data = [], int $retries = 0): array
    {
        // Circuit Breaker check
        if (time() < $this->circuit_breaker_open_until) {
            $this->logger->warning("Circuit breaker terbuka untuk bot ID {$this->bot_id}. Melewatkan panggilan API untuk metode {$method}.");
            return [
                'ok' => false,
                'error_code' => 503,
                'description' => 'Circuit breaker terbuka. Coba lagi nanti.',
            ];
        }

        $ch = null;
        $response = null;
        $max_retries = 3; // Maksimal percobaan ulang untuk rate limit
        $initial_retry_delay = 1; // Detik

        try {
            $url = $this->api_url . $this->token . '/' . $method;

            $ch = curl_init();
            if ($ch === false) {
                throw new Exception('Gagal menginisialisasi cURL.');
            }

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_POST, true);
            }
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($result === false) {
                throw new Exception(curl_error($ch), $http_code > 0 ? $http_code : 503);
            }

            $response = json_decode($result, true);

            if ($http_code !== 200 || (isset($response['ok']) && $response['ok'] === false)) {
                $errorMessage = $response['description'] ?? 'Unknown Telegram API error';
                $errorCode = $response['error_code'] ?? $http_code;

                // Handle rate limiting (HTTP 429) with retry mechanism
                if ($errorCode === 429 && $retries < $max_retries) {
                    $retryAfter = 0;
                    if (preg_match('/retry after (\d+)/i', $errorMessage, $matches)) {
                        $retryAfter = (int)$matches[1];
                    }
                    $delay = $retryAfter > 0 ? $retryAfter : ($initial_retry_delay * pow(2, $retries));
                    $this->logger->warning("Rate limit hit. Retrying in {$delay} seconds (attempt " . ($retries + 1) . "/{$max_retries}). Method: {$method}");
                    sleep($delay);
                    curl_close($ch); // Close current cURL handle before retrying
                    return $this->apiRequest($method, $data, $retries + 1); // Recursive call with incremented retries
                }

                // Increment failure count for circuit breaker
                $this->failure_count++;
                if ($this->failure_count >= self::MAX_FAILURES) {
                    $this->circuit_breaker_open_until = time() + self::RESET_TIMEOUT;
                    $this->logger->critical("Circuit breaker terbuka untuk bot ID {$this->bot_id} karena " . self::MAX_FAILURES . " kegagalan berurutan. Akan terbuka kembali pada " . date('Y-m-d H:i:s', $this->circuit_breaker_open_until));
                }

                // Persist circuit breaker state
                if ($this->botRepo && $this->bot_id) {
                    $this->botRepo->updateCircuitBreakerState($this->bot_id, $this->failure_count, $this->circuit_breaker_open_until);
                }

                throw new Exception($errorMessage, $errorCode);
            }

            // If successful, reset failure count
            $this->failure_count = 0;
            $this->circuit_breaker_open_until = 0;

            // Persist circuit breaker state
            if ($this->botRepo && $this->bot_id) {
                $this->botRepo->updateCircuitBreakerState($this->bot_id, $this->failure_count, $this->circuit_breaker_open_until);
            }

            curl_close($ch);
            return $response;

        } catch (Exception $e) {
            if (is_resource($ch)) {
                curl_close($ch);
            }
            // Increment failure count for circuit breaker even on general exceptions
            $this->failure_count++;
            if ($this->failure_count >= self::MAX_FAILURES) {
                $this->circuit_breaker_open_until = time() + self::RESET_TIMEOUT;
                $this->logger->critical("Circuit breaker terbuka untuk bot ID {$this->bot_id} karena " . self::MAX_FAILURES . " kegagalan berurutan (exception). Akan terbuka kembali pada " . date('Y-m-d H:i:s', $this->circuit_breaker_open_until));
            }

            // Persist circuit breaker state
            if ($this->botRepo && $this->bot_id) {
                $this->botRepo->updateCircuitBreakerState($this->bot_id, $this->failure_count, $this->circuit_breaker_open_until);
            }

            $this->handleApiError($e, $response, $method, $data);
            // Kembalikan array error yang konsisten dengan format respons Telegram
            return [
                'ok' => false,
                'error_code' => $e->getCode(),
                'description' => $e->getMessage(),
            ];
        }
    }

    /**
     * Handle API errors.
     *
     * @param Exception $e
     * @param array|null $response
     * @param string $method
     * @param array $requestData
     * @return void
     */
    protected function handleApiError(Exception $e, ?array $response, string $method, array $requestData): void
    {
        $errorCode = $e->getCode();
        $description = $e->getMessage();
        $chatId = $requestData['chat_id'] ?? null;

        $this->logger->info("Telegram API Error: Code {$errorCode} - {$description} | Method: {$method} | ChatID: {$chatId}");

        $logData = [
            'method' => $method,
            'request_data' => $requestData,
            'http_code' => ($errorCode >= 200 && $errorCode < 600) ? $errorCode : null,
            'error_code' => $response['error_code'] ?? null,
            'description' => $description,
            'status' => 'failed',
            'retry_after' => null,
            'chat_id' => $chatId
        ];

        switch ($errorCode) {
            case 400: // Bad Request
                if (stripos($description, 'chat not found') !== false) {
                    $this->logger->error("Penanganan: Chat ID {$chatId} tidak valid.");
                } elseif (stripos($description, 'message is not modified') !== false) {
                    $this->logger->info("Info: Edit pesan dibatalkan, tidak ada perubahan.");
                    return; // Bukan error kritis, tidak perlu log ke DB.
                } elseif (stripos($description, "can't parse entities") !== false) {
                    $this->logger->error("Penanganan: Format Markdown/HTML pada pesan salah.");
                }
                break;
            case 403: // Forbidden
                if (stripos($description, 'bot was blocked by the user') !== false) {
                    $this->logger->error("Penanganan: Bot diblokir oleh pengguna {$chatId}. Memperbarui status pengguna.");
                    if ($this->userRepo && $chatId) {
                        $this->userRepo->updateUserStatusByTelegramId((int)$chatId, 'blocked');
                        $this->logger->info("Status pengguna {$chatId} diubah menjadi 'blocked' di database.");
                    }
                } elseif (stripos($description, 'bot is not a member') !== false) {
                     $this->logger->error("Penanganan: Bot bukan admin/member di grup/channel {$chatId}.");
                }
                break;
            case 429: // Too Many Requests
                if (preg_match('/retry after (\d+)/i', $description, $matches)) {
                    $retryAfter = (int)$matches[1];
                    $this->logger->warning("Info: Rate limit, coba lagi setelah {$retryAfter} detik.");
                    $logData['status'] = 'pending_retry';
                    $logData['retry_after'] = $retryAfter;
                }
                break;
        }

        if ($this->errorLogRepo) {
            $this->errorLogRepo->create($logData);
        }
    }

    /**
     * Escape text for Markdown.
     *
     * @param string $text
     * @return string
     */
    public function escapeMarkdown(string $text): string
    {
        // Karakter yang perlu di-escape untuk Markdown lama
        $escape_chars = '_*`[';
        return preg_replace('/([' . preg_quote($escape_chars, '/') . '])/', '\\$1', $text);
    }

    /**
     * Escape text for HTML parse mode.
     *
     * @param string $text
     * @return string
     */
    public function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_HTML5, 'UTF-8', false);
    }

    /**
     * Send a message.
     *
     * @param int|string $chat_id
     * @param string $text
     * @param string|null $parse_mode
     * @param string|null $reply_markup
     * @param int|null $message_thread_id
     * @param array|null $reply_parameters
     * @return array
     */
    public function sendMessage($chat_id, string $text, ?string $parse_mode = 'Markdown', ?string $reply_markup = null, ?int $message_thread_id = null, ?array $reply_parameters = null): array
    {
        $data = [
            'chat_id' => $chat_id,
            'text' => $text,
        ];
        if ($parse_mode) {
            $data['parse_mode'] = $parse_mode;
        }
        if ($reply_markup) {
            $data['reply_markup'] = $reply_markup;
        }
        if ($message_thread_id) {
            $data['message_thread_id'] = $message_thread_id;
        }
        // Pastikan reply_parameters adalah array yang valid dan tidak kosong sebelum dikirim
        if (is_array($reply_parameters) && !empty($reply_parameters)) {
            $data['reply_parameters'] = json_encode($reply_parameters);
        }
        return $this->apiRequest('sendMessage', $data);
    }

    /**
     * Send a media group.
     *
     * @param int|string $chat_id
     * @param string $media
     * @return array
     */
    public function sendMediaGroup($chat_id, string $media): array
    {
        $data = [
            'chat_id' => $chat_id,
            'media' => $media,
        ];
        return $this->apiRequest('sendMediaGroup', $data);
    }

    /**
     * Copy a message.
     *
     * @param int|string $chat_id
     * @param int|string $from_chat_id
     * @param int $message_id
     * @param string|null $caption
     * @param string|null $parse_mode
     * @param string|null $reply_markup
     * @param bool $protect_content
     * @return array
     */
    public function copyMessage($chat_id, $from_chat_id, int $message_id, ?string $caption = null, ?string $parse_mode = 'Markdown', ?string $reply_markup = null, bool $protect_content = false): array
    {
        $data = [
            'chat_id' => $chat_id,
            'from_chat_id' => $from_chat_id,
            'message_id' => $message_id,
        ];
        if ($caption !== null) {
            $data['caption'] = $caption;
        }
        if ($parse_mode) {
            $data['parse_mode'] = $parse_mode;
        }
        if ($reply_markup !== null) {
            $data['reply_markup'] = $reply_markup;
        }
        if ($protect_content) {
            $data['protect_content'] = true;
        }
        return $this->apiRequest('copyMessage', $data);
    }

    /**
     * Copy messages.
     *
     * @param int|string $chat_id
     * @param int|string $from_chat_id
     * @param string $message_ids
     * @param bool $protect_content
     * @return array
     */
    public function copyMessages($chat_id, $from_chat_id, string $message_ids, bool $protect_content = false): array
    {
        $data = [
            'chat_id' => $chat_id,
            'from_chat_id' => $from_chat_id,
            'message_ids' => $message_ids,
        ];
        if ($protect_content) {
            $data['protect_content'] = true;
        }
        return $this->apiRequest('copyMessages', $data);
    }

    /**
     * Answer a callback query.
     *
     * @param string $callback_query_id
     * @param string|null $text
     * @param bool $show_alert
     * @return array
     */
    public function answerCallbackQuery(string $callback_query_id, ?string $text = null, bool $show_alert = false): array
    {
        $data = [
            'callback_query_id' => $callback_query_id,
        ];
        if ($text) {
            $data['text'] = $text;
        }
        $data['show_alert'] = $show_alert;
        return $this->apiRequest('answerCallbackQuery', $data);
    }

    /**
     * Send a photo.
     *
     * @param int|string $chat_id
     * @param string $photo
     * @param string|null $caption
     * @param string|null $parse_mode
     * @param string|null $reply_markup
     * @return array
     */
    public function sendPhoto($chat_id, string $photo, ?string $caption = null, ?string $parse_mode = 'Markdown', ?string $reply_markup = null): array
    {
        $data = ['chat_id' => $chat_id, 'photo' => $photo];
        if ($caption) $data['caption'] = $caption;
        if ($parse_mode) $data['parse_mode'] = $parse_mode;
        if ($reply_markup) $data['reply_markup'] = $reply_markup;
        return $this->apiRequest('sendPhoto', $data);
    }

    /**
     * Send a video.
     *
     * @param int|string $chat_id
     * @param string $video
     * @param string|null $caption
     * @param string|null $parse_mode
     * @param string|null $reply_markup
     * @return array
     */
    public function sendVideo($chat_id, string $video, ?string $caption = null, ?string $parse_mode = 'Markdown', ?string $reply_markup = null): array
    {
        $data = ['chat_id' => $chat_id, 'video' => $video];
        if ($caption) $data['caption'] = $caption;
        if ($parse_mode) $data['parse_mode'] = $parse_mode;
        if ($reply_markup) $data['reply_markup'] = $reply_markup;
        return $this->apiRequest('sendVideo', $data);
    }

    /**
     * Send an audio file.
     *
     * @param int|string $chat_id
     * @param string $audio
     * @param string|null $caption
     * @param string|null $parse_mode
     * @param string|null $reply_markup
     * @return array
     */
    public function sendAudio($chat_id, string $audio, ?string $caption = null, ?string $parse_mode = 'Markdown', ?string $reply_markup = null): array
    {
        $data = ['chat_id' => $chat_id, 'audio' => $audio];
        if ($caption) $data['caption'] = $caption;
        if ($parse_mode) $data['parse_mode'] = $parse_mode;
        if ($reply_markup) $data['reply_markup'] = $reply_markup;
        return $this->apiRequest('sendAudio', $data);
    }

    /**
     * Send a document.
     *
     * @param int|string $chat_id
     * @param string $document
     * @param string|null $caption
     * @param string|null $parse_mode
     * @param string|null $reply_markup
     * @return array
     */
    public function sendDocument($chat_id, string $document, ?string $caption = null, ?string $parse_mode = 'Markdown', ?string $reply_markup = null): array
    {
        $data = ['chat_id' => $chat_id, 'document' => $document];
        if ($caption) $data['caption'] = $caption;
        if ($parse_mode) $data['parse_mode'] = $parse_mode;
        if ($reply_markup) $data['reply_markup'] = $reply_markup;
        return $this->apiRequest('sendDocument', $data);
    }

    /**
     * Send an animation.
     *
     * @param int|string $chat_id
     * @param string $animation
     * @param string|null $caption
     * @param string|null $parse_mode
     * @param string|null $reply_markup
     * @return array
     */
    public function sendAnimation($chat_id, string $animation, ?string $caption = null, ?string $parse_mode = 'Markdown', ?string $reply_markup = null): array
    {
        $data = ['chat_id' => $chat_id, 'animation' => $animation];
        if ($caption) $data['caption'] = $caption;
        if ($parse_mode) $data['parse_mode'] = $parse_mode;
        if ($reply_markup) $data['reply_markup'] = $reply_markup;
        return $this->apiRequest('sendAnimation', $data);
    }

    /**
     * Send a voice message.
     *
     * @param int|string $chat_id
     * @param string $voice
     * @param string|null $caption
     * @param string|null $parse_mode
     * @param string|null $reply_markup
     * @return array
     */
    public function sendVoice($chat_id, string $voice, ?string $caption = null, ?string $parse_mode = 'Markdown', ?string $reply_markup = null): array
    {
        $data = ['chat_id' => $chat_id, 'voice' => $voice];
        if ($caption) $data['caption'] = $caption;
        if ($parse_mode) $data['parse_mode'] = $parse_mode;
        if ($reply_markup) $data['reply_markup'] = $reply_markup;
        return $this->apiRequest('sendVoice', $data);
    }

    /**
     * Set a webhook.
     *
     * @param string $url
     * @return array
     */
    public function setWebhook(string $url): array
    {
        return $this->apiRequest('setWebhook', ['url' => $url]);
    }

    /**
     * Get webhook info.
     *
     * @return array
     */
    public function getWebhookInfo(): array
    {
        return $this->apiRequest('getWebhookInfo');
    }

    /**
     * Delete a webhook.
     *
     * @return array
     */
    public function deleteWebhook(): array
    {
        return $this->apiRequest('deleteWebhook');
    }

    /**
     * Set the list of the bot's commands.
     *
     * @param array $commands A JSON-serialized list of bot commands to be set as the list of the bot's commands.
     * @return array
     */
    public function setMyCommands(array $commands): array
    {
        return $this->apiRequest('setMyCommands', ['commands' => json_encode($commands)]);
    }

    /**
     * Delete the list of the bot's commands.
     *
     * @return array
     */
    public function deleteMyCommands(): array
    {
        return $this->apiRequest('deleteMyCommands');
    }

    /**
     * Get bot info.
     *
     * @return array
     */
    public function getMe(): array
    {
        return $this->apiRequest('getMe');
    }

    /**
     * Delete a message.
     *
     * @param int|string $chat_id
     * @param int $message_id
     * @return array
     */
    public function deleteMessage($chat_id, int $message_id): array
    {
        $data = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
        ];
        return $this->apiRequest('deleteMessage', $data);
    }

    /**
     * Get chat member info.
     *
     * @param int|string $chat_id
     * @param int $user_id
     * @return array
     */
    public function getChatMember($chat_id, int $user_id): array
    {
        $data = [
            'chat_id' => $chat_id,
            'user_id' => $user_id,
        ];
        return $this->apiRequest('getChatMember', $data);
    }

    /**
     * Get bot ID.
     *
     * @return int|null
     */
    public function getBotId(): ?int
    {
        $response = $this->getMe();
        return $response['ok'] ? $response['result']['id'] : null;
    }

    /**
     * Get chat info.
     *
     * @param int|string $chat_id
     * @return array
     */
    public function getChat($chat_id): array
    {
        $data = [
            'chat_id' => $chat_id,
        ];
        return $this->apiRequest('getChat', $data);
    }

    /**
     * Answer an inline query.
     *
     * @param string $inline_query_id
     * @param array $results
     * @return array
     */
    public function answerInlineQuery(string $inline_query_id, array $results): array
    {
        $data = [
            'inline_query_id' => $inline_query_id,
            'results' => json_encode($results),
        ];
        return $this->apiRequest('answerInlineQuery', $data);
    }

    /**
     * Edit a message's caption.
     *
     * @param int|string $chat_id
     * @param int $message_id
     * @param string $caption
     * @param string|null $parse_mode
     * @param string|null $reply_markup
     * @return array
     */
    public function editMessageCaption($chat_id, int $message_id, string $caption, ?string $parse_mode = 'Markdown', ?string $reply_markup = null): array
    {
        $data = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'caption' => $caption,
        ];
        if ($parse_mode) {
            $data['parse_mode'] = $parse_mode;
        }
        if ($reply_markup) {
            $data['reply_markup'] = $reply_markup;
        }
        return $this->apiRequest('editMessageCaption', $data);
    }

    /**
     * Edit a message's text.
     *
     * @param int|string $chat_id
     * @param int $message_id
     * @param string $text
     * @param string|null $parse_mode
     * @param string|null $reply_markup
     * @return array
     */
    public function editMessageText($chat_id, int $message_id, string $text, ?string $parse_mode = 'Markdown', ?string $reply_markup = null): array
    {
        $data = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $text,
        ];
        if ($parse_mode) {
            $data['parse_mode'] = $parse_mode;
        }
        if ($reply_markup) {
            $data['reply_markup'] = $reply_markup;
        }
        return $this->apiRequest('editMessageText', $data);
    }

    /**
     * Send a long message.
     *
     * @param int|string $chat_id
     * @param string $text
     * @param string|null $parse_mode
     * @return void
     */
    public function sendLongMessage($chat_id, string $text, ?string $parse_mode = null): void
    {
        $max_length = 4096;
        $paragraphs = preg_split('/(\r\n|\n|\r){2,}/', $text);
        $current_message = "";

        foreach ($paragraphs as $paragraph) {
            if (empty(trim($paragraph))) {
                continue;
            }

            // Jika paragraf itu sendiri sudah terlalu panjang, pecah secara paksa
            if (strlen($paragraph) > $max_length) {
                if (!empty($current_message)) {
                    $this->sendMessage($chat_id, $current_message, $parse_mode);
                    $current_message = "";
                }
                $sub_chunks = str_split($paragraph, $max_length);
                foreach ($sub_chunks as $chunk) {
                    $this->sendMessage($chat_id, $chunk, $parse_mode);
                }
                continue;
            }

            if (strlen($current_message) + strlen($paragraph) + 2 > $max_length) {
                if (!empty($current_message)) {
                    $this->sendMessage($chat_id, $current_message, $parse_mode);
                }
                $current_message = $paragraph;
            } else {
                if (!empty($current_message)) {
                    $current_message .= "\n\n";
                }
                $current_message .= $paragraph;
            }
        }

        if (!empty($current_message)) {
            $this->sendMessage($chat_id, $current_message, $parse_mode);
        }
    }
}
