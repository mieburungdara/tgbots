<?php

/**
 * Kelas wrapper untuk berinteraksi dengan API Bot Telegram.
 * Menyediakan metode untuk semua endpoint API umum, serta penanganan error
 * yang terintegrasi dengan database untuk logging dan tindakan korektif.
 */
class TelegramAPI {
    protected $token;
    protected $api_url = 'https://api.telegram.org/bot';

    // Properti baru untuk interaksi database
    protected $pdo;
    protected $errorLogRepo;
    protected $userRepo;
    protected $bot_id;

    /**
     * Membuat instance TelegramAPI.
     *
     * @param string $token Token Bot Telegram.
     * @param PDO|null $pdo Objek koneksi database (opsional, untuk logging error).
     * @param int|null $internal_bot_id ID internal bot dari database (opsional, untuk operasi terkait pengguna).
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
     * Metode inti untuk mengirim permintaan ke API Telegram menggunakan cURL.
     * Dilengkapi dengan penanganan error terpusat melalui `handleApiError`.
     *
     * @param string $method Metode API yang akan dipanggil (misal: 'sendMessage').
     * @param array $data Data yang akan dikirim dalam permintaan.
     * @return array Hasil dekode JSON dari API Telegram, atau array error kustom jika gagal.
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
            // Kembalikan array error yang konsisten dengan format respons Telegram
            return [
                'ok' => false,
                'error_code' => $e->getCode(),
                'description' => $e->getMessage(),
            ];
        }
    }

    /**
     * Menangani, mencatat, dan mengambil tindakan berdasarkan kesalahan API.
     * Metode ini mencatat error ke log aplikasi dan database, serta dapat
     * memicu tindakan spesifik seperti menandai pengguna yang memblokir.
     *
     * @param Exception $e Exception yang ditangkap dari `apiRequest`.
     * @param array|null $response Body respons dari API jika ada.
     * @param string $method Metode API yang dipanggil.
     * @param array $requestData Data yang dikirim dalam permintaan.
     * @return void
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
     * Melakukan escape karakter khusus untuk digunakan dalam mode parse Markdown lama.
     *
     * @param string $text Teks yang akan di-escape.
     * @return string Teks yang sudah di-escape.
     */
    public function escapeMarkdown($text)
    {
        // Karakter yang perlu di-escape untuk Markdown lama
        $escape_chars = '_*`[';
        return preg_replace('/([' . preg_quote($escape_chars, '/') . '])/', '\\\\$1', $text);
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
    public function sendMessage($chat_id, $text, $parse_mode = 'Markdown', $reply_markup = null, $message_thread_id = null, $reply_parameters = null) {
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
     * Menyalin sebuah pesan dari satu chat ke chat lain.
     *
     * @param int|string $chat_id ID chat tujuan.
     * @param int|string $from_chat_id ID chat sumber.
     * @param int $message_id ID pesan yang akan disalin.
     * @param string|null $caption Caption baru untuk media (opsional).
     * @param string|null $parse_mode Mode parse untuk caption baru.
     * @param string|null $reply_markup Keyboard inline dalam format JSON (opsional).
     * @param bool $protect_content Jika true, melindungi konten dari forward dan penyimpanan.
     * @return array Hasil dari API Telegram.
     */
    public function copyMessage($chat_id, $from_chat_id, $message_id, $caption = null, $parse_mode = 'Markdown', $reply_markup = null, bool $protect_content = false) {
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
     * Menyalin beberapa pesan sebagai satu grup dari satu chat ke chat lain.
     * Berguna untuk menyalin album.
     *
     * @param int|string $chat_id ID chat tujuan.
     * @param int|string $from_chat_id ID chat sumber.
     * @param string $message_ids Array ID pesan yang di-encode sebagai JSON.
     * @param bool $protect_content Jika true, melindungi konten dari forward dan penyimpanan.
     * @return array Hasil dari API Telegram.
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

    /**
     * Metode-metode berikut adalah wrapper sederhana untuk mengirim berbagai jenis media.
     * Mereka semua menerima `chat_id`, sumber media (`photo`, `video`, dll.),
     * dan parameter opsional seperti `caption`, `parse_mode`, dan `reply_markup`.
     * @return array Hasil dari API Telegram.
     */
    public function sendPhoto($chat_id, $photo, $caption = null, $parse_mode = 'Markdown', $reply_markup = null) {
        $data = ['chat_id' => $chat_id, 'photo' => $photo];
        if ($caption) $data['caption'] = $caption;
        if ($parse_mode) $data['parse_mode'] = $parse_mode;
        if ($reply_markup) $data['reply_markup'] = $reply_markup;
        return $this->apiRequest('sendPhoto', $data);
    }

    public function sendVideo($chat_id, $video, $caption = null, $parse_mode = 'Markdown', $reply_markup = null) {
        $data = ['chat_id' => $chat_id, 'video' => $video];
        if ($caption) $data['caption'] = $caption;
        if ($parse_mode) $data['parse_mode'] = $parse_mode;
        if ($reply_markup) $data['reply_markup'] = $reply_markup;
        return $this->apiRequest('sendVideo', $data);
    }

    public function sendAudio($chat_id, $audio, $caption = null, $parse_mode = 'Markdown', $reply_markup = null) {
        $data = ['chat_id' => $chat_id, 'audio' => $audio];
        if ($caption) $data['caption'] = $caption;
        if ($parse_mode) $data['parse_mode'] = $parse_mode;
        if ($reply_markup) $data['reply_markup'] = $reply_markup;
        return $this->apiRequest('sendAudio', $data);
    }

    public function sendDocument($chat_id, $document, $caption = null, $parse_mode = 'Markdown', $reply_markup = null) {
        $data = ['chat_id' => $chat_id, 'document' => $document];
        if ($caption) $data['caption'] = $caption;
        if ($parse_mode) $data['parse_mode'] = $parse_mode;
        if ($reply_markup) $data['reply_markup'] = $reply_markup;
        return $this->apiRequest('sendDocument', $data);
    }

    public function sendAnimation($chat_id, $animation, $caption = null, $parse_mode = 'Markdown', $reply_markup = null) {
        $data = ['chat_id' => $chat_id, 'animation' => $animation];
        if ($caption) $data['caption'] = $caption;
        if ($parse_mode) $data['parse_mode'] = $parse_mode;
        if ($reply_markup) $data['reply_markup'] = $reply_markup;
        return $this->apiRequest('sendAnimation', $data);
    }

    public function sendVoice($chat_id, $voice, $caption = null, $parse_mode = 'Markdown', $reply_markup = null) {
        $data = ['chat_id' => $chat_id, 'voice' => $voice];
        if ($caption) $data['caption'] = $caption;
        if ($parse_mode) $data['parse_mode'] = $parse_mode;
        if ($reply_markup) $data['reply_markup'] = $reply_markup;
        return $this->apiRequest('sendVoice', $data);
    }

    /**
     * Mengatur URL webhook untuk bot agar dapat menerima pembaruan dari Telegram.
     *
     * @param string $url URL HTTPS dari skrip webhook Anda.
     * @return array Hasil dari API Telegram.
     */
    public function setWebhook($url) {
        return $this->apiRequest('setWebhook', ['url' => $url]);
    }

    /**
     * Mendapatkan informasi tentang status webhook saat ini.
     *
     * @return array Hasil dari API Telegram.
     */
    public function getWebhookInfo() {
        return $this->apiRequest('getWebhookInfo');
    }

    /**
     * Menghapus URL webhook yang sedang terpasang.
     * Berguna untuk beralih kembali ke mode getUpdates (polling).
     *
     * @return array Hasil dari API Telegram.
     */
    public function deleteWebhook() {
        return $this->apiRequest('deleteWebhook');
    }

    /**
     * Mendapatkan informasi dasar tentang bot itu sendiri (username, ID, dll.).
     *
     * @return array Hasil dari API Telegram.
     */
    public function getMe() {
        return $this->apiRequest('getMe');
    }

    /**
     * Menghapus sebuah pesan dari sebuah chat.
     *
     * @param int|string $chat_id ID dari chat tempat pesan berada.
     * @param int $message_id ID dari pesan yang akan dihapus.
     * @return array Hasil dari API Telegram.
     */
    public function deleteMessage($chat_id, $message_id) {
        $data = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
        ];
        return $this->apiRequest('deleteMessage', $data);
    }

    /**
     * Mendapatkan informasi tentang seorang anggota (member) di sebuah chat.
     * Berguna untuk memeriksa status (admin, member, dll.) dari bot atau pengguna.
     *
     * @param int|string $chat_id ID dari chat.
     * @param int $user_id ID dari pengguna yang akan diperiksa.
     * @return array Hasil dari API Telegram.
     */
    public function getChatMember($chat_id, $user_id)
    {
        $data = [
            'chat_id' => $chat_id,
            'user_id' => $user_id,
        ];
        return $this->apiRequest('getChatMember', $data);
    }

    /**
     * Mendapatkan ID numerik dari bot saat ini dengan memanggil metode `getMe`.
     *
     * @return int|null ID bot atau null jika panggilan API gagal.
     */
    public function getBotId()
    {
        $response = $this->getMe();
        return $response['ok'] ? $response['result']['id'] : null;
    }

    /**
     * Mendapatkan informasi mendetail tentang sebuah chat (channel, grup, atau pengguna).
     *
     * @param int|string $chat_id ID unik atau username dari chat target.
     * @return array Hasil dari API Telegram.
     */
    public function getChat($chat_id)
    {
        $data = [
            'chat_id' => $chat_id,
        ];
        return $this->apiRequest('getChat', $data);
    }

    /**
     * Mengirim balasan untuk sebuah inline query.
     *
     * @param string $inline_query_id ID dari inline query yang akan dijawab.
     * @param array $results Array yang berisi objek InlineQueryResult.
     * @return array Hasil dari API Telegram.
     */
    public function answerInlineQuery(string $inline_query_id, array $results)
    {
        $data = [
            'inline_query_id' => $inline_query_id,
            'results' => json_encode($results),
        ];
        return $this->apiRequest('answerInlineQuery', $data);
    }

    /**
     * Mengirim pesan teks yang panjang dengan memecahnya menjadi beberapa bagian
     * yang lebih kecil agar sesuai dengan batas karakter Telegram.
     * Pesan dipecah berdasarkan paragraf untuk menjaga keterbacaan.
     *
     * @param int|string $chat_id ID dari chat tujuan.
     * @param string $text Teks panjang yang akan dikirim.
     * @param string|null $parse_mode Mode parsing: 'Markdown', 'HTML', atau null.
     * @return void
     */
    public function sendLongMessage($chat_id, $text, $parse_mode = null)
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
