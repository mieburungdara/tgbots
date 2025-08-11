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
        curl_close($ch);

        if ($result === false) {
            error_log("cURL Error: " . curl_error($ch));
            return false;
        }

        return json_decode($result, true);
    }

    /**
     * Mengirim pesan teks ke sebuah chat.
     *
     * @param int|string $chat_id ID dari chat tujuan.
     * @param string $text Teks pesan yang akan dikirim.
     * @return mixed Hasil dari API Telegram, atau false jika gagal.
     */
    public function sendMessage($chat_id, $text) {
        $data = [
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'HTML' // Bisa juga 'Markdown' atau biarkan kosong
        ];
        return $this->apiRequest('sendMessage', $data);
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
