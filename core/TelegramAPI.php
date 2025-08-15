<?php

class TelegramAPI {
    protected $token;
    protected $api_url = 'https://api.telegram.org/bot';

    // Properti baru untuk interaksi database
    protected $pdo;
    protected $errorLogRepo;
    protected $userRepo;
    protected $bot_id;

    /**
     * Constructor.
     * @param string $token Token Bot Telegram.
     * @param PDO|null $pdo Objek koneksi database.
     * @param int|null $internal_bot_id ID internal bot dari database.
     */
    public function __construct($token, PDO $pdo = null, int $internal_bot_id = null)
    {
        $this->token = $token;
        $this->pdo = $pdo;
        $this->bot_id = $internal_bot_id;

        if ($this->pdo) {
            // Pastikan file-file ini belum di-include sebelumnya jika ada autoloader
            if (!class_exists('TelegramErrorLogRepository')) {
                require_once __DIR__ . '/database/TelegramErrorLogRepository.php';
            }
            if (!class_exists('UserRepository')) {
                require_once __DIR__ . '/database/UserRepository.php';
            }

            $this->errorLogRepo = new TelegramErrorLogRepository($this->pdo);
            if ($this->bot_id) {
                $this->userRepo = new UserRepository($this->pdo, $this->bot_id);
            }
        }
    }

    /**
     * Mengirim permintaan ke API Telegram, dengan penanganan error yang lebih baik.
     *
     * @param string $method Metode API yang akan dipanggil.
     * @param array $data Data yang akan dikirim.
     * @return mixed Hasil dari API Telegram, atau false jika gagal.
     */
    protected function apiRequest($method, $data = [])
    {
        $ch = null;
        $response = null;
        try {
            $url = $this->api_url . $this->token . '/' . $method;

            $ch = curl_init();
            if ($ch === false) {
                throw new Exception('Gagal menginisialisasi cURL.');
            }

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_POST, true);
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
                throw new Exception($errorMessage, $errorCode);
            }

            curl_close($ch);
            return $response;

        } catch (Exception $e) {
            if (is_resource($ch)) {
                curl_close($ch);
            }
            $this->handleApiError($e, $response, $method, $data);
            return false;
        }
    }

    /**
     * Menangani, mencatat, dan mengambil tindakan berdasarkan kesalahan API.
     *
     * @param Exception $e Exception yang ditangkap.
     * @param array|null $response Response body dari API.
     * @param string $method Metode API yang dipanggil.
     * @param array $requestData Data yang dikirim dalam permintaan.
     */
    protected function handleApiError(Exception $e, ?array $response, string $method, array $requestData)
    {
        $errorCode = $e->getCode();
        $description = $e->getMessage();
        $chatId = $requestData['chat_id'] ?? null;

        app_log("Telegram API Error: Code {$errorCode} - {$description} | Method: {$method} | ChatID: {$chatId}", 'bot');

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
                    app_log("Penanganan: Chat ID {$chatId} tidak valid.", 'bot_error');
                } elseif (stripos($description, 'message is not modified') !== false) {
                    app_log("Info: Edit pesan dibatalkan, tidak ada perubahan.", 'bot_info');
                    return; // Bukan error kritis, tidak perlu log ke DB.
                } elseif (stripos($description, "can't parse entities") !== false) {
                    app_log("Penanganan: Format Markdown/HTML pada pesan salah.", 'bot_error');
                }
                break;
            case 403: // Forbidden
                if (stripos($description, 'bot was blocked by the user') !== false) {
                    app_log("Penanganan: Bot diblokir oleh pengguna {$chatId}. Memperbarui status pengguna.", 'bot_error');
                    if ($this->userRepo && $chatId) {
                        $this->userRepo->updateUserStatusByTelegramId((int)$chatId, 'blocked');
                        app_log("Status pengguna {$chatId} diubah menjadi 'blocked' di database.", 'bot_db');
                    }
                } elseif (stripos($description, 'bot is not a member') !== false) {
                     app_log("Penanganan: Bot bukan admin/member di grup/channel {$chatId}.", 'bot_error');
                }
                break;
            case 429: // Too Many Requests
                if (preg_match('/retry after (\d+)/i', $description, $matches)) {
                    $retryAfter = (int)$matches[1];
                    app_log("Info: Rate limit, coba lagi setelah {$retryAfter} detik.", 'bot_info');
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
     * Mengirim pesan teks ke sebuah chat.
     *
     * @param int|string $chat_id ID dari chat tujuan.
     * @param string $text Teks pesan yang akan dikirim.
     * @param string|null $parse_mode Mode parsing: 'Markdown', 'HTML', atau null.
     * @param string|null $reply_markup Keyboard inline atau kustom dalam format JSON.
     * @return mixed Hasil dari API Telegram, atau false jika gagal.
     */
    public function sendMessage($chat_id, $text, $parse_mode = null, $reply_markup = null) {
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
        return $this->apiRequest('sendMessage', $data);
    }

    /**
     * Mengirim sekelompok foto atau video sebagai album.
     *
     * @param int|string $chat_id ID dari chat tujuan.
     * @param string $media JSON-encoded array of InputMediaPhoto and InputMediaVideo.
     * @return mixed Hasil dari API Telegram, atau false jika gagal.
     */
    public function sendMediaGroup($chat_id, $media) {
        $data = [
            'chat_id' => $chat_id,
            'media' => $media,
        ];
        return $this->apiRequest('sendMediaGroup', $data);
    }

    /**
     * Menyalin satu pesan dari satu chat ke chat lain.
     *
     * @param int|string $chat_id ID chat tujuan.
     * @param int|string $from_chat_id ID chat sumber.
     * @param int $message_id ID pesan yang akan disalin.
     * @param string|null $caption Caption baru untuk media (opsional).
     * @param string|null $reply_markup Keyboard inline (opsional).
     * @param bool $protect_content Melindungi konten dari forward dan save.
     * @return mixed Hasil dari API Telegram.
     */
    public function copyMessage($chat_id, $from_chat_id, $message_id, $caption = null, $reply_markup = null, bool $protect_content = false) {
        $data = [
            'chat_id' => $chat_id,
            'from_chat_id' => $from_chat_id,
            'message_id' => $message_id,
        ];
        if ($caption !== null) {
            $data['caption'] = $caption;
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
     * Menyalin beberapa pesan dari satu chat ke chat lain.
     *
     * @param int|string $chat_id ID chat tujuan.
     * @param int|string $from_chat_id ID chat sumber.
     * @param string $message_ids JSON-encoded array dari ID pesan yang akan disalin.
     * @param bool $protect_content Melindungi konten dari forward dan save.
     * @return mixed Hasil dari API Telegram.
     */
    public function copyMessages($chat_id, $from_chat_id, $message_ids, bool $protect_content = false) {
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
     * Menjawab callback query (misalnya, dari tombol inline).
     *
     * @param string $callback_query_id ID dari callback query.
     * @param string|null $text Teks notifikasi yang akan ditampilkan.
     * @param bool $show_alert Jika true, notifikasi akan ditampilkan sebagai dialog alert.
     * @return mixed Hasil dari API Telegram.
     */
    public function answerCallbackQuery($callback_query_id, $text = null, $show_alert = false) {
        $data = [
            'callback_query_id' => $callback_query_id,
        ];
        if ($text) {
            $data['text'] = $text;
        }
        $data['show_alert'] = $show_alert;
        return $this->apiRequest('answerCallbackQuery', $data);
    }

    // --- Metode untuk mengirim berbagai jenis media ---

    public function sendPhoto($chat_id, $photo, $caption = null, $parse_mode = null, $reply_markup = null) {
        $data = ['chat_id' => $chat_id, 'photo' => $photo];
        if ($caption) $data['caption'] = $caption;
        if ($parse_mode) $data['parse_mode'] = $parse_mode;
        if ($reply_markup) $data['reply_markup'] = $reply_markup;
        return $this->apiRequest('sendPhoto', $data);
    }

    public function sendVideo($chat_id, $video, $caption = null, $parse_mode = null, $reply_markup = null) {
        $data = ['chat_id' => $chat_id, 'video' => $video];
        if ($caption) $data['caption'] = $caption;
        if ($parse_mode) $data['parse_mode'] = $parse_mode;
        if ($reply_markup) $data['reply_markup'] = $reply_markup;
        return $this->apiRequest('sendVideo', $data);
    }

    public function sendAudio($chat_id, $audio, $caption = null, $parse_mode = null, $reply_markup = null) {
        $data = ['chat_id' => $chat_id, 'audio' => $audio];
        if ($caption) $data['caption'] = $caption;
        if ($parse_mode) $data['parse_mode'] = $parse_mode;
        if ($reply_markup) $data['reply_markup'] = $reply_markup;
        return $this->apiRequest('sendAudio', $data);
    }

    public function sendDocument($chat_id, $document, $caption = null, $parse_mode = null, $reply_markup = null) {
        $data = ['chat_id' => $chat_id, 'document' => $document];
        if ($caption) $data['caption'] = $caption;
        if ($parse_mode) $data['parse_mode'] = $parse_mode;
        if ($reply_markup) $data['reply_markup'] = $reply_markup;
        return $this->apiRequest('sendDocument', $data);
    }

    public function sendAnimation($chat_id, $animation, $caption = null, $parse_mode = null, $reply_markup = null) {
        $data = ['chat_id' => $chat_id, 'animation' => $animation];
        if ($caption) $data['caption'] = $caption;
        if ($parse_mode) $data['parse_mode'] = $parse_mode;
        if ($reply_markup) $data['reply_markup'] = $reply_markup;
        return $this->apiRequest('sendAnimation', $data);
    }

    public function sendVoice($chat_id, $voice, $caption = null, $parse_mode = null, $reply_markup = null) {
        $data = ['chat_id' => $chat_id, 'voice' => $voice];
        if ($caption) $data['caption'] = $caption;
        if ($parse_mode) $data['parse_mode'] = $parse_mode;
        if ($reply_markup) $data['reply_markup'] = $reply_markup;
        return $this->apiRequest('sendVoice', $data);
    }

    /**
     * Mengatur URL webhook untuk menerima update.
     *
     * @param string $url URL webhook.
     * @return mixed Hasil dari API Telegram.
     */
    public function setWebhook($url) {
        return $this->apiRequest('setWebhook', ['url' => $url]);
    }

    /**
     * Mendapatkan informasi webhook saat ini.
     *
     * @return mixed Hasil dari API Telegram.
     */
    public function getWebhookInfo() {
        return $this->apiRequest('getWebhookInfo');
    }

    /**
     * Menghapus webhook yang sudah ter-set.
     *
     * @return mixed Hasil dari API Telegram.
     */
    public function deleteWebhook() {
        return $this->apiRequest('deleteWebhook');
    }

    /**
     * Mendapatkan informasi dasar tentang bot.
     *
     * @return mixed Hasil dari API Telegram.
     */
    public function getMe() {
        return $this->apiRequest('getMe');
    }

    /**
     * Menghapus sebuah pesan.
     *
     * @param int|string $chat_id ID dari chat.
     * @param int $message_id ID dari pesan yang akan dihapus.
     * @return mixed Hasil dari API Telegram.
     */
    public function deleteMessage($chat_id, $message_id) {
        $data = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
        ];
        return $this->apiRequest('deleteMessage', $data);
    }
}
