<?php

namespace TGBot\Handlers;

use TGBot\App;
use TGBot\Database\UserRepository;
use TGBot\Database\MediaPackageRepository;
use TGBot\Database\BotChannelUsageRepository;
use TGBot\Database\MediaFileRepository;
use PDO;

class StateHandler
{
    public function handle(App $app, array $message): void
    {
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);
        $text = $message['text'];
        $state_context = json_decode($app->user['state_context'] ?? '{}', true);

        if (strpos($text, '/cancel') === 0) {
            $user_repo->setUserState($app->user['id'], null, null);
            $app->telegram_api->sendMessage($app->chat_id, "Operasi dibatalkan.");
            return;
        }

        if ($app->user['state'] === 'awaiting_price') {
            $price = filter_var($text, FILTER_VALIDATE_INT);
            if ($price === false || $price <= 0) {
                $app->telegram_api->sendMessage($app->chat_id, "Harga tidak valid. Harap masukkan angka bulat positif.");
                return;
            }

            $post_repo = new MediaPackageRepository($app->pdo);

            $media_message = $state_context['media_messages'][0];
            $message_id = $media_message['message_id'];
            $chat_id = $media_message['chat_id'];

            $stmt = $app->pdo->prepare("SELECT * FROM media_files WHERE message_id = ? AND chat_id = ?");
            $stmt->execute([$message_id, $chat_id]);
            $media_file = $stmt->fetch();

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
            } else {
                $stmt = $app->pdo->prepare("UPDATE media_files SET package_id = ? WHERE id = ?");
                $stmt->execute([$package_id, $media_file['id']]);
            }

            $post = $post_repo->find($package_id);
            $public_id = $post['public_id'];

            $user_repo->setUserState($app->user['id'], null, null);

            $bot_channel_usage_repo = new BotChannelUsageRepository($app->pdo);
            $backup_channel_info = $bot_channel_usage_repo->getNextChannelForBot((int)$app->bot['id']);

            $backup_channel_id = null;
            if ($backup_channel_info) {
                $backup_channel_id = $backup_channel_info['channel_id'];
            }

            if ($backup_channel_id) {
                $package_files = $post_repo->getGroupedPackageContent($package_id);
                $media_file_repo = new MediaFileRepository($app->pdo);

                $backup_caption = "Konten Baru untuk Dijual (Backup)\n\nID Paket: `{$public_id}`\nPenjual: `{$app->user['id']}`\nHarga: Rp " . number_format($price, 0, ',', '.');

                if (!empty($package_files)) {
                    $app->telegram_api->sendMessage($backup_channel_id, $backup_caption, 'Markdown');

                    foreach ($package_files as $page_index => $page) {
                        if (count($page) > 1) {
                            $message_ids_to_copy = array_map(fn($file) => $file['storage_message_id'], $page);
                            $from_chat_id = $page[0]['storage_channel_id'];
                            $copied_messages_response = $app->telegram_api->copyMessages($backup_channel_id, $from_chat_id, json_encode($message_ids_to_copy));

                            if ($copied_messages_response && $copied_messages_response['ok']) {
                                foreach ($copied_messages_response['result'] as $index => $copied_message) {
                                    $original_file = $page[$index];
                                    $media_file_repo->updateStorageInfo(
                                        $original_file['id'],
                                        $backup_channel_id,
                                        $copied_message['message_id']
                                    );
                                }
                            }
                        } elseif (!empty($page)) {
                            $file = $page[0];
                            $copied_message_response = $app->telegram_api->copyMessage($backup_channel_id, $file['storage_channel_id'], $file['storage_message_id']);
                            if ($copied_message_response && $copied_message_response['ok']) {
                                $media_file_repo->updateStorageInfo(
                                    $file['id'],
                                    $backup_channel_id,
                                    $copied_message_response['result']['message_id']
                                );
                            }
                        }
                         usleep(300000);
                    }
                }
            }
            $message_text = "✅ Paket berhasil dibuat dengan ID: `{$public_id}`\n";
            $message_text .= "Harga: *Rp " . number_format($price, 0, ',', '.') . "*\n\n";
            $message_text .= "Anda dapat melihat konten Anda dengan perintah `/konten {$public_id}`.";

            $app->telegram_api->sendMessage($app->chat_id, $message_text, 'Markdown');
        }
    }
}
