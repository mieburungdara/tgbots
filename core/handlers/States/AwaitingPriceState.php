<?php

namespace TGBot\Handlers\States;

use TGBot\App;
use TGBot\Database\UserRepository;
use TGBot\Database\MediaPackageRepository;
use TGBot\Database\BotChannelUsageRepository;
use TGBot\Database\MediaFileRepository;

class AwaitingPriceState implements StateInterface
{
    public function handle(App $app, array $message, array $state_context): void
    {
        $text = $message['text'];
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);

        $price = filter_var($text, FILTER_VALIDATE_INT);
        if ($price === false || $price <= 0) {
            $app->telegram_api->sendMessage($app->chat_id, "Harga tidak valid. Harap masukkan angka bulat positif.");
            return;
        }

        $post_repo = new MediaPackageRepository($app->pdo);
        $package_data = $this->createMediaPackage($app, $state_context, $price, $post_repo);
        $package_id = $package_data['package_id'];
        $public_id = $package_data['public_id'];

        $user_repo->setUserState($app->user['id'], null, null);

        $bot_channel_usage_repo = new BotChannelUsageRepository($app->pdo);
        $backup_channel_info = $bot_channel_usage_repo->getNextChannelForBot((int)$app->bot['id']);

        if ($backup_channel_info) {
            $backup_channel_id = $backup_channel_info['channel_id'];
            error_log("[AwaitingPriceState] Backup channel found: " . $backup_channel_id);
            $this->backupMedia($app, $package_id, $public_id, $price, $post_repo, $backup_channel_id);
        } else {
            error_log("[AwaitingPriceState] No backup channel found for bot ID: " . $app->bot['id']);
        }

        $message_text = "✅ Paket berhasil dibuat dengan ID: `{$public_id}`\n";
        $message_text .= "Harga: *Rp " . number_format($price, 0, ',', '.') . "*\n\n";
        $message_text .= "Anda dapat melihat konten Anda dengan perintah `/konten {$public_id}`.";

        $app->telegram_api->sendMessage($app->chat_id, $message_text, 'Markdown');
    }

    private function createMediaPackage(App $app, array $state_context, int $price, MediaPackageRepository $post_repo): array
    {
        $media_message = $state_context['media_messages'][0];
        $message_id = $media_message['message_id'];
        $chat_id = $media_message['chat_id'];

        error_log("[AwaitingPriceState] createMediaPackage - message_id: " . $message_id . ", chat_id: " . $chat_id);

        $stmt = $app->pdo->prepare("SELECT * FROM media_files WHERE message_id = ? AND chat_id = ?");
        $stmt->execute([$message_id, $chat_id]);
        $media_file = $stmt->fetch();

        if (!$media_file) {
            error_log("[AwaitingPriceState] createMediaPackage - No media_file found for message_id: " . $message_id . ", chat_id: " . $chat_id);
            throw new \Exception("Media file not found for the given message.");
        }
        error_log("[AwaitingPriceState] createMediaPackage - media_file found: " . json_encode($media_file));

        $package_id = $post_repo->createPackageWithPublicId(
            $app->user['id'],
            $app->bot['id'],
            $media_file['caption'] ?? '',
            $media_file['id'],
            'sell'
        );

        $post_repo->updatePrice($package_id, $price);

        if ($media_file['media_group_id']) {
            $stmt = $app->pdo->prepare("UPDATE media_files SET package_id = ? WHERE media_group_id = ?");
            $stmt->execute([$package_id, $media_file['media_group_id']]);
            error_log("[AwaitingPriceState] createMediaPackage - Updated media_files for media_group_id: " . $media_file['media_group_id'] . ", rows affected: " . $stmt->rowCount());
        } else {
            $stmt = $app->pdo->prepare("UPDATE media_files SET package_id = ? WHERE id = ?");
            $stmt->execute([$package_id, $media_file['id']]);
            error_log("[AwaitingPriceState] createMediaPackage - Updated media_file for id: " . $media_file['id'] . ", rows affected: " . $stmt->rowCount());
        }

        $post = $post_repo->find($package_id);
        $public_id = $post['public_id'];

        return [
            'package_id' => $package_id,
            'public_id' => $public_id
        ];
    }

    private function backupMedia(App $app, int $package_id, string $public_id, int $price, MediaPackageRepository $post_repo, string $backup_channel_id): void
    {
        if (!$backup_channel_id) return;

        $package_files = $post_repo->getGroupedPackageContent($package_id);
        if (empty($package_files)) {
            error_log("[AwaitingPriceState] No package files found for package ID: " . $package_id);
            return;
        }

        $media_file_repo = new MediaFileRepository($app->pdo);

        $backup_caption = "Konten Baru untuk Dijual (Backup)\n\nID Paket: `{$public_id}`\nPenjual: `{$app->user['id']}`\nHarga: Rp " . number_format($price, 0, ',', '.');

        $app->telegram_api->sendMessage($backup_channel_id, $backup_caption, 'Markdown');

        foreach ($package_files as $page) {
            if (count($page) > 1) {
                $message_ids_to_copy = array_map(fn($file) => $file['storage_message_id'], $page);
                $from_chat_id = $page[0]['storage_channel_id'];
                $copied_messages_response = $app->telegram_api->copyMessages($backup_channel_id, $from_chat_id, json_encode($message_ids_to_copy));
                error_log("[AwaitingPriceState] copyMessages response: " . json_encode($copied_messages_response));

                if ($copied_messages_response && $copied_messages_response['ok']) {
                    foreach ($copied_messages_response['result'] as $index => $copied_message) {
                        $original_file = $page[$index];
                        $media_file_repo->updateStorageInfo($original_file['id'], $backup_channel_id, $copied_message['message_id']);
                    }
                }
            } elseif (!empty($page)) {
                $file = $page[0];
                $copied_message_response = $app->telegram_api->copyMessage($backup_channel_id, $file['storage_channel_id'], $file['storage_message_id']);
                error_log("[AwaitingPriceState] copyMessage response: " . json_encode($copied_message_response));
                if ($copied_message_response && $copied_messages_response['ok']) {
                    $media_file_repo->updateStorageInfo($file['id'], $backup_channel_id, $copied_message_response['result']['message_id']);
                }
            }
            usleep(300000);
        }
    }
}