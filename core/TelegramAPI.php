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
}
