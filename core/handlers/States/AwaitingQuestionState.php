<?php

namespace TGBot\Handlers\States;

use TGBot\App;
use TGBot\Database\UserRepository;
use TGBot\Database\MediaPackageRepository;
use Exception;

class AwaitingQuestionState implements StateInterface
{
    public function handle(App $app, array $message, array $state_context): void
    {
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);
        $package_repo = new MediaPackageRepository($app->pdo);

        $question_text = $message['text'] ?? '';
        $package_public_id = $state_context['package_public_id'];
        $seller_user_id = $state_context['seller_user_id'];
        $buyer_user_id = $state_context['buyer_user_id'];

        if (empty($question_text)) {
            $app->telegram_api->sendMessage($app->chat_id, "Pertanyaan tidak boleh kosong. Silakan ketik pertanyaan Anda.");
            return;
        }

        $package = $package_repo->findByPublicId($package_public_id);
        if (!$package) {
            $app->telegram_api->sendMessage($app->chat_id, "Error: Paket tidak ditemukan.");
            $user_repo->setUserState($app->user['id'], null, null);
            return;
        }

        $seller = $user_repo->findUserById($seller_user_id);
        if (!$seller) {
            $app->telegram_api->sendMessage($app->chat_id, "Error: Penjual tidak ditemukan.");
            $user_repo->setUserState($app->user['id'], null, null);
            return;
        }

        // Forward the question to the seller
        $forward_message = "💬 *Pertanyaan Baru tentang Konten*\n\n" .
                           "Dari: `{$app->user['first_name']}` (@{$app->user['username']})\n" .
                           "Konten: `{$package_public_id}`\n" .
                           "Pertanyaan: {$question_text}\n\n" .
                           "_Balas pesan ini untuk menjawab._";

        $reply_keyboard = [
            ['text' => 'Balas Pembeli ↩️', 'callback_data' => "reply_to_buyer_{$buyer_user_id}_{$package_public_id}"]
        ];
        $reply_markup = json_encode(['inline_keyboard' => [$reply_keyboard]]);

        try {
            $app->telegram_api->sendMessage($seller_user_id, $forward_message, 'Markdown', $reply_markup);
            $app->telegram_api->sendMessage($app->chat_id, "✅ Pertanyaan Anda telah dikirim ke penjual.");
            $user_repo->setUserState($app->user['id'], null, null); // Clear buyer's state
        } catch (Exception $e) {
            $app->telegram_api->sendMessage($app->chat_id, "⚠️ Gagal mengirim pertanyaan: " . $e->getMessage());
            app_log("Gagal mengirim pertanyaan ke penjual {$seller_user_id}: " . $e->getMessage(), 'error', ['buyer_id' => $buyer_user_id, 'package_id' => $package_public_id]);
        }
    }
}
