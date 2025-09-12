<?php

require_once __DIR__ . '/../core/autoloader.php';
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';

use TGBot\Database\MediaPackageRepository;
use TGBot\TelegramAPI;
use TGBot\App;

$pdo = get_db_connection();
$logger = App::getLogger();

if (!$pdo) {
    $logger->critical("Cronjob Error: Gagal terkoneksi ke database.");
    exit;
}

$post_repo = new MediaPackageRepository($pdo);

$five_minutes_ago = date('Y-m-d H:i:s', strtotime('-5 minutes'));

$stmt = $pdo->prepare("SELECT * FROM media_packages WHERE status = 'pending' AND created_at < ?");
$stmt->execute([$five_minutes_ago]);
$pending_posts = $stmt->fetchAll();

foreach ($pending_posts as $post) {
    try {
        $pdo->beginTransaction();

        // 1. Approve the post
        $stmt_approve = $pdo->prepare("UPDATE media_packages SET status = 'available' WHERE id = ?");
        $stmt_approve->execute([$post['id']]);

        // 2. Get bot info
        $stmt_bot = $pdo->prepare("SELECT token FROM bots WHERE id = ?");
        $stmt_bot->execute([$post['bot_id']]);
        $bot_token = $stmt_bot->fetchColumn();

        if (!$bot_token) {
            throw new Exception("Bot token not found for bot_id: " . $post['bot_id']);
        }

        $telegram_api = new TelegramAPI($bot_token);

        // 3. Get public channel ID
        $post_type = $post['post_type']; // 'rate' or 'tanya'
        $category = $post['category'];

        $stmt_settings = $pdo->prepare("SELECT setting_value FROM bot_settings WHERE bot_id = ? AND setting_key = ?");
        $setting_key = 'public_channel_' . $post_type . '_' . $category;
        $stmt_settings->execute([$post['bot_id'], $setting_key]);
        $public_channel_id = $stmt_settings->fetchColumn();

        if (!$public_channel_id) {
            throw new Exception("Public channel not configured for bot_id: " . $post['bot_id'] . " and setting_key: " . $setting_key);
        }

        // 4. Forward the post to the public channel
        $public_post = null;
        if ($post_type === 'rate') {
            $media_file = $post_repo->getThumbnailFile($post['id']);
            if ($media_file) {
                $public_post = $telegram_api->forwardMessage($public_channel_id, $media_file['chat_id'], $media_file['message_id']);
            }
        } else { // tanya
            $public_post = $telegram_api->sendMessage($public_channel_id, $post['description']);
        }

        // 5. Send notification to user
        if ($public_post && $public_post['ok']) {
            $user_id = $post['seller_user_id'];
            $public_post_link = "https://t.me/c/" . substr($public_channel_id, 4) . "/" . $public_post['result']['message_id'];
            $message = "🎉 Kabar baik! Kiriman Anda telah disetujui dan dipublikasikan.\n\nLihat di sini: " . $public_post_link;

            $keyboard = ['inline_keyboard' => [[['text' => 'Tarik Post', 'callback_data' => 'retract_post_' . $post['public_id']]]]];

            $telegram_api->sendMessage($user_id, $message, null, json_encode($keyboard));
        }

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $logger->error("Cronjob Error: Gagal auto-approve post #" . $post['id'] . ". Error: " . $e->getMessage());
    }
}
