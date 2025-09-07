<?php

namespace TGBot\Handlers\Commands;

use TGBot\App;
use TGBot\Database\BotRepository;
use TGBot\Database\UserRepository;
use PDO;

class SellCommand implements CommandInterface
{
    public function execute(App $app, array $message, array $parts): void
    {
        $prerequisite_error = $this->validatePrerequisites($app, $message);
        if ($prerequisite_error !== null) {
            $app->telegram_api->sendMessage($app->chat_id, $prerequisite_error);
            return;
        }

        $replied_message = $message['reply_to_message'];
        $media_info = $this->getValidatedMediaInfo($app, $replied_message);

        if ($media_info === null) {
            $app->telegram_api->sendMessage($app->chat_id, "⚠️ Gagal. Pastikan Anda me-reply pesan media (foto/video) yang sudah tersimpan di bot.");
            return;
        }

        $media_group_id = $media_info['media_group_id'];
        $description = $media_info['description'];

        $user_repo = new UserRepository($app->pdo, $app->bot['id']);
        $state_context = [
            'media_messages' => [['message_id' => $replied_message['message_id'], 'chat_id' => $replied_message['chat']['id']]]
        ];
        $user_repo->setUserState($app->user['id'], 'awaiting_price', $state_context);

        $message_text = "✅ Media telah siap untuk dijual.\n\n";
        if (!empty($description)) {
            $message_text .= "Deskripsi: *\"" . $app->telegram_api->escapeMarkdown($description) . "\"*\n";
        }
        $message_text .= "Sekarang, silakan masukkan harga untuk paket ini (contoh: 50000).\n\n";
        $message_text .= "_Ketik /cancel untuk membatalkan._";

        $app->telegram_api->sendMessage($app->chat_id, $message_text, 'Markdown');
    }

    private function validatePrerequisites(App $app, array $message): ?string
    {
        if (isset($app->bot['assigned_feature']) && $app->bot['assigned_feature'] !== 'sell') {
            $bot_repo = new BotRepository($app->pdo);
            $correct_bots = $bot_repo->findAllBotsByFeature('sell');
            $suggestion = "";
            if (!empty($correct_bots)) {
                $suggestion = "\n\nFitur ini tersedia di bot berikut:\n";
                foreach ($correct_bots as $bot) {
                    $suggestion .= "- @" . $bot['username'] . "\n";
                }
            }
            return "Perintah /sell tidak tersedia di bot ini." . $suggestion;
        }

        if (!isset($message['reply_to_message'])) {
            return "Untuk menjual, silakan reply media yang ingin Anda jual dengan perintah /sell.";
        }

        if (empty($app->user['public_seller_id'])) {
            $text = "Anda belum terdaftar sebagai penjual. Apakah Anda ingin mendaftar sekarang?\n\nDengan mendaftar, Anda akan mendapatkan ID Penjual unik.";
            $keyboard = ['inline_keyboard' => [[['text' => "Ya, Daftar Sekarang", 'callback_data' => "register_seller"]]]];
            $app->telegram_api->sendMessage($app->chat_id, $text, null, json_encode($keyboard));
            return false;
            //return "register_seller_prompt"; // Special return to indicate prompt sent
        }

        return null; // All prerequisites passed
    }

    private function getValidatedMediaInfo(App $app, array $replied_message): ?array
    {
        $stmt_check_media = $app->pdo->prepare("SELECT COUNT(*) FROM media_files WHERE message_id = ? AND chat_id = ?");
        $stmt_check_media->execute([$replied_message['message_id'], $replied_message['chat']['id']]);
        if ($stmt_check_media->fetchColumn() == 0) {
             return null; // Media not found
        }

        $stmt_media_info = $app->pdo->prepare("SELECT media_group_id, caption FROM media_files WHERE message_id = ? AND chat_id = ?");
        $stmt_media_info->execute([$replied_message['message_id'], $replied_message['chat']['id']]);
        $media_info = $stmt_media_info->fetch(PDO::FETCH_ASSOC);

        $media_group_id = $media_info['media_group_id'] ?? null;
        $description = $media_info['caption'] ?? '';

        if ($media_group_id) {
            $stmt_caption = $app->pdo->prepare("SELECT caption FROM media_files WHERE media_group_id = ? AND caption IS NOT NULL AND caption != '' LIMIT 1");
            $stmt_caption->execute([$media_group_id]);
            $group_caption = $stmt_caption->fetchColumn();
            if ($group_caption) {
                $description = $group_caption;
            }
        }

        return [
            'media_group_id' => $media_group_id,
            'description' => $description
        ];
    }
}
