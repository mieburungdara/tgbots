<?php

require_once __DIR__ . '/../core/autoloader.php';

use TGBot\App;
use TGBot\Database\SaleRepository;
use TGBot\Database\UserRepository;

// Initialize App with a dummy bot ID for cron context
// In a real scenario, you might iterate through all active bots
// or pass the bot ID as an argument.
$app = new App(1); // Assuming bot ID 1 for cron operations

$sale_repo = new SaleRepository($app->pdo);
$user_repo = new UserRepository($app->pdo, $app->bot['id']);

// Find gifts expiring in the next 24 hours and not yet claimed
$expiring_gifts = $sale_repo->findExpiringUnclaimedGifts(24); // 24 hours

foreach ($expiring_gifts as $gift_sale) {
    $recipient_user_id = $gift_sale['granted_to_user_id'];
    $package_public_id = $gift_sale['public_id'];
    $expires_at = $gift_sale['expires_at'];

    $message = "🔔 Pengingat Hadiah!\n\n";
    $message .= "Hadiah konten `{$package_public_id}` yang Anda terima akan kadaluarsa pada *{$expires_at}* jika tidak segera diklaim.\n\n";
    $message .= "Klik tombol di bawah untuk mengklaim hadiah Anda sekarang!";

    $keyboard = ['inline_keyboard' => [[['text' => 'Klaim Hadiah 🎁', 'callback_data' => "claim_gift_{$package_public_id}"]]]];

    try {
        $app->telegram_api->sendMessage($recipient_user_id, $message, 'Markdown', json_encode($keyboard));
        echo "Reminder sent to user {$recipient_user_id} for package {$package_public_id}\n";
    } catch (Exception $e) {
        echo "Failed to send reminder to user {$recipient_user_id}: " . $e->getMessage() . "\n";
    }
}

echo "Gift claim reminder cron job finished.\n";

