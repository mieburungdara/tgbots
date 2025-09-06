<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;
use TGBot\Database\MediaPackageRepository;
use TGBot\Database\ChannelPostPackageRepository;
use TGBot\Database\FeatureChannelRepository;
use TGBot\Database\BotRepository;
use TGBot\TelegramAPI;
use Exception;

class PostToChannelCallback implements CallbackCommandInterface
{
    public function execute(App $app, array $callback_query, string $params): void
    {
        $parts = explode('_', $params);
        if (count($parts) < 2) {
            $app->telegram_api->answerCallbackQuery($callback_query['id'], '⚠️ Format callback tidak valid.', true);
            return;
        }
        $feature_channel_id = (int)array_pop($parts);
        $public_id = implode('_', $parts);

        $package_repo = new MediaPackageRepository($app->pdo);
        $post_package_repo = new ChannelPostPackageRepository($app->pdo);
        $feature_channel_repo = new FeatureChannelRepository($app->pdo);

        $callback_query_id = $callback_query['id'];
        $telegram_user_id = $app->user['id'];

        $package = $package_repo->findByPublicId($public_id);
        if (!$package || $package['seller_user_id'] != $telegram_user_id) {
            $app->telegram_api->answerCallbackQuery($callback_query_id, '⚠️ Anda tidak memiliki izin untuk mem-posting konten ini.', true);
            return;
        }

        $sales_channel = $feature_channel_repo->find($feature_channel_id);
        if (!$sales_channel || $sales_channel['owner_user_id'] != $telegram_user_id) {
            $app->telegram_api->answerCallbackQuery($callback_query_id, '⚠️ Channel tidak valid atau Anda tidak memiliki izin.', true);
            return;
        }

        $channel_id = $sales_channel['public_channel_id'];
        $managing_bot_id = $sales_channel['managing_bot_id'];

        $bot_repo = new BotRepository($app->pdo);
        $managing_bot = $bot_repo->find($managing_bot_id);
        if (!$managing_bot) {
             $app->telegram_api->answerCallbackQuery($callback_query_id, '⚠️ Gagal mendapatkan data bot pengelola.', true);
             return;
        }
        $posting_api = new TelegramAPI($managing_bot['token']);

        $thumbnail = $package_repo->getThumbnailFile($package['id']);
        if (!$thumbnail) {
            $app->telegram_api->answerCallbackQuery($callback_query_id, '⚠️ Gagal mendapatkan media pratinjau untuk konten ini.', true);
            return;
        }

        $price_formatted = "Rp " . number_format($package['price'], 0, ',', '.');
        $caption = sprintf(
            "✨ *Konten Baru Tersedia* ✨\n\n%s\n\nHarga: *%s*",
            $app->telegram_api->escapeMarkdown($package['description']),
            $app->telegram_api->escapeMarkdown($price_formatted)
        );

        try {
            $app->pdo->beginTransaction();

            $result = $posting_api->copyMessage($channel_id, $thumbnail['storage_channel_id'], $thumbnail['storage_message_id'], $caption, 'Markdown', null, (bool)$package['protect_content']);
            if (!$result || ($result['ok'] === false)) throw new Exception($result['description'] ?? 'Unknown error');

            $post_package_repo->create($channel_id, $result['result']['message_id'], $package['id']);

            $app->pdo->commit();

            if (isset($callback_query['message']['message_id'])) {
                $app->telegram_api->editMessageText($app->chat_id, $callback_query['message']['message_id'], '✅ Berhasil di-posting ke channel ' . htmlspecialchars($sales_channel['name']) . '!');
            } else {
                $app->telegram_api->answerCallbackQuery($callback_query_id, '✅ Berhasil di-posting!', false);
            }

        } catch (Exception $e) {
            $app->pdo->rollBack();
            $error_message = "❌ Gagal mem-posting. Pastikan bot adalah admin dengan izin yang benar.";
            if (stripos($e->getMessage(), 'bot was kicked') !== false || stripos($e->getMessage(), 'not a member') !== false) {
                 $error_message = "❌ Gagal: Bot bukan lagi admin di channel.";
            }

            if (isset($callback_query['message']['message_id'])) {
                $app->telegram_api->editMessageText($app->chat_id, $callback_query['message']['message_id'], $error_message);
            } else {
                $app->telegram_api->answerCallbackQuery($callback_query_id, $error_message, true);
            }
            app_log("Gagal post ke channel {$channel_id} untuk user {$telegram_user_id}: " . $e->getMessage(), 'bot_error');
        }
    }
}
