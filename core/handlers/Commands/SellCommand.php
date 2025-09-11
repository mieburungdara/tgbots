<?php

namespace TGBot\Handlers\Commands;

use TGBot\App;
use TGBot\Database\BotRepository;
use TGBot\Database\UserRepository;
use TGBot\Handlers\States\SellingProcessState;
use PDO;

class SellCommand implements CommandInterface
{
    private static $processed_media_groups = [];

    public function execute(App $app, array $message, array $parts): void
    {
        // 1. Cek duplikasi untuk media group
        if (isset($message['media_group_id'])) {
            if (in_array($message['media_group_id'], self::$processed_media_groups)) {
                return; // Sudah diproses oleh pesan pertama di grup
            }
            self::$processed_media_groups[] = $message['media_group_id'];
        }

        // 2. Dapatkan media yang relevan (dari reply atau dari pesan itu sendiri)
        $relevant_message = $this->getRelevantMediaMessage($message);
        if (!$relevant_message) {
            $app->telegram_api->sendMessage($app->chat_id, "Untuk menjual, silakan reply sebuah media, atau kirim media dengan caption /sell.");
            return;
        }

        // 3. Validasi prasyarat
        $prerequisite_error = $this->validatePrerequisites($app, $relevant_message);
        if ($prerequisite_error === "register_seller_prompt") {
            return;
        } elseif ($prerequisite_error !== null) {
            $app->telegram_api->sendMessage($app->chat_id, $prerequisite_error);
            return;
        }

        // 4. Kumpulkan semua media awal
        $initial_media = $this->getInitialMedia($app, $relevant_message);
        if (empty($initial_media)) {
            $app->telegram_api->sendMessage($app->chat_id, "⚠️ Gagal. Pastikan media sudah tersimpan di bot sebelum dijual.");
            return;
        }

        // 5. Atur state awal
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);
        $state_context = ['media_messages' => $initial_media];
        $user_repo->setUserState($app->user['id'], 'selling_process', $state_context);

        // 6. Kirim prompt awal
        $media_count = count($initial_media);
        $message_text = "✅ {$media_count} media awal telah diterima.\n\n";
        $message_text .= "Anda sekarang dapat:\n";
        $message_text .= "- Mengirim lebih banyak media (foto/video) untuk ditambahkan ke paket.\n";
        $message_text .= "- Mengirimkan harga (contoh: `50000`) untuk menyelesaikan.";

        $app->telegram_api->sendMessage($app->chat_id, $message_text);
    }

    private function getRelevantMediaMessage(array $message): ?array
    {
        if (isset($message['reply_to_message'])) {
            return $message['reply_to_message'];
        }
        if (isset($message['photo']) || isset($message['video'])) {
            return $message;
        }
        return null;
    }

    private function getInitialMedia(App $app, array $relevant_message): array
    {
        $media_group_id = $relevant_message['media_group_id'] ?? null;
        if ($media_group_id) {
            $stmt = $app->pdo->prepare("SELECT message_id, chat_id FROM media_files WHERE media_group_id = ?");
            $stmt->execute([$media_group_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        else {
            return [['message_id' => $relevant_message['message_id'], 'chat_id' => $relevant_message['chat']['id']]];
        }
    }

    private function validatePrerequisites(App $app, array $message): ?string
    {
        if (empty($app->user['public_seller_id'])) {
            $text = "Anda belum terdaftar sebagai penjual. Apakah Anda ingin mendaftar sekarang?";
            $keyboard = ['inline_keyboard' => [[['text' => "Ya, Daftar Sekarang", 'callback_data' => "register_seller"]]]];
            $app->telegram_api->sendMessage($message['chat']['id'], $text, null, json_encode($keyboard));
            return "register_seller_prompt";
        }
        return null;
    }
}
