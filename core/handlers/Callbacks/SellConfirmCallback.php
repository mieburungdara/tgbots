<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;
use TGBot\Database\UserRepository;
use TGBot\Database\MediaPackageRepository;
use TGBot\Database\BotChannelUsageRepository;
use TGBot\Database\MediaFileRepository;
use Exception;

class SellConfirmCallback implements CallbackCommandInterface
{
    public function execute(App $app, array $callback_query, string $params): void
    {
        $app->telegram_api->answerCallbackQuery($callback_query['id'], '✅ Konfirmasi diterima. Memproses paket Anda...');

        $parts = explode('_', $params);
        if (count($parts) < 3) {
            $app->telegram_api->editMessageText($app->chat_id, $callback_query['message']['message_id'], '⚠️ Terjadi kesalahan: Konteks penjualan tidak lengkap.');
            return;
        }

        $price = (int)$parts[0];
        $original_message_id = (int)$parts[1];
        $original_chat_id = (int)$parts[2];

        try {
            $app->pdo->beginTransaction();

            $post_repo = new MediaPackageRepository($app->pdo);
            $package_data = $this->createMediaPackage($app, $original_message_id, $original_chat_id, $price, $post_repo);
            $package_id = $package_data['package_id'];
            $public_id = $package_data['public_id'];

            $bot_channel_usage_repo = new BotChannelUsageRepository($app->pdo);
            $backup_channel_info = $bot_channel_usage_repo->getNextChannelForBot((int)$app->bot['id']);

            if ($backup_channel_info) {
                $this->backupMedia($app, $package_id, $backup_channel_info['channel_id']);
            }

            $app->pdo->commit();

            $message_text = "✅ Paket berhasil dibuat dengan ID: `{$public_id}`\n";
            $message_text .= "Harga: *Rp " . number_format($price, 0, ',', '.') . "*\n\n";
            $message_text .= "Anda dapat melihat konten Anda dengan perintah `/konten {$public_id}`.";

            $app->telegram_api->editMessageText($app->chat_id, $callback_query['message']['message_id'], $message_text, 'Markdown');

        } catch (Exception $e) {
            if ($app->pdo->inTransaction()) {
                $app->pdo->rollBack();
            }
            $error_message = "⚠️ Gagal memproses paket: " . $e->getMessage();
            $app->telegram_api->editMessageText($app->chat_id, $callback_query['message']['message_id'], $error_message);
            app_log("Gagal menangani konfirmasi penjualan: " . $e->getMessage(), 'error');
        }
    }

    private function createMediaPackage(App $app, int $message_id, int $chat_id, int $price, MediaPackageRepository $post_repo): array
    {
        $stmt = $app->pdo->prepare("SELECT * FROM media_files WHERE message_id = ? AND chat_id = ?");
        $stmt->execute([$message_id, $chat_id]);
        $media_file = $stmt->fetch();

        if (!$media_file) {
            throw new Exception("File media asli tidak ditemukan di database.");
        }

        $package_id = $post_repo->createPackageWithPublicId(
            $app->user['id'],
            $app->bot['id'],
            $media_file['caption'] ?? '',
            $media_file['id'],
            'sell'
        );

        $post_repo->updatePrice($package_id, $price);

        if ($media_file['media_group_id']) {
            $stmt_update = $app->pdo->prepare("UPDATE media_files SET package_id = ? WHERE media_group_id = ?");
            $stmt_update->execute([$package_id, $media_file['media_group_id']]);
        } else {
            $stmt_update = $app->pdo->prepare("UPDATE media_files SET package_id = ? WHERE id = ?");
            $stmt_update->execute([$package_id, $media_file['id']]);
        }

        $post = $post_repo->find($package_id);
        return ['package_id' => $package_id, 'public_id' => $post['public_id']];
    }

    private function backupMedia(App $app, int $package_id, string $backup_channel_id): void
    {
        $post_repo = new MediaPackageRepository($app->pdo);
        $media_file_repo = new MediaFileRepository($app->pdo);

        $package_pages = $post_repo->getGroupedPackageContent($package_id);
        if (empty($package_pages)) return;

        $all_files = array_merge(...$package_pages);
        if (empty($all_files)) return;

        $files_by_chat = [];
        foreach ($all_files as $file) {
            if (isset($file['chat_id'], $file['message_id'])) {
                $files_by_chat[$file['chat_id']][] = $file;
            }
        }

        foreach ($files_by_chat as $from_chat_id => $files) {
            $message_ids_to_copy = array_map(fn($file) => (int)$file['message_id'], $files);
            $copied_messages_response = $app->telegram_api->copyMessages($backup_channel_id, $from_chat_id, json_encode($message_ids_to_copy));

            if ($copied_messages_response && $copied_messages_response['ok']) {
                foreach ($copied_messages_response['result'] as $index => $copied_message) {
                    $original_file = $files[$index];
                    $media_file_repo->updateStorageInfo($original_file['id'], $backup_channel_id, $copied_message['message_id']);
                }
            } else {
                app_log("Gagal menyalin grup media ke backup channel: " . json_encode($copied_messages_response), 'error');
                throw new Exception("Gagal menyalin media ke penyimpanan.");
            }
            usleep(300000); // Rate limiting
        }
    }
}
