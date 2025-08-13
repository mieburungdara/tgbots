<?php

class TelegramAPI {
    protected $token;
    protected $api_url = 'https://api.telegram.org/bot';

    /**
     * Constructor.
     * @param string $token Token Bot Telegram.
     */
    public function __construct($token) {
        $this->token = $token;
    }

    /**
     * Mengirim permintaan ke API Telegram.
     *
     * @param string $method Metode API yang akan dipanggil.
     * @param array $data Data yang akan dikirim.
     * @return mixed Hasil dari API Telegram, atau false jika gagal.
     */
    protected function apiRequest($method, $data = []) {
        $url = $this->api_url . $this->token . '/' . $method;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_POST, true);

        $result = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            app_log("Telegram API cURL Error: " . $curl_error, 'bot');
            return false;
        }

        return json_decode($result, true);
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
     * @return mixed Hasil dari API Telegram.
     */
    public function copyMessage($chat_id, $from_chat_id, $message_id, $caption = null, $reply_markup = null) {
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
        return $this->apiRequest('copyMessage', $data);
    }

    /**
     * Menyalin beberapa pesan dari satu chat ke chat lain.
     *
     * @param int|string $chat_id ID chat tujuan.
     * @param int|string $from_chat_id ID chat sumber.
     * @param string $message_ids JSON-encoded array dari ID pesan yang akan disalin.
     * @return mixed Hasil dari API Telegram.
     */
    public function copyMessages($chat_id, $from_chat_id, $message_ids) {
        $data = [
            'chat_id' => $chat_id,
            'from_chat_id' => $from_chat_id,
            'message_ids' => $message_ids,
        ];
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
}
