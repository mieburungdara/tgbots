<?php

// Set up the application environment
require_once __DIR__ . '/../core/autoloader.php';
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';
use TGBot\App;

// Get a database connection
$pdo = get_db_connection();
$logger = App::getLogger();

$logger->info('Cron job: check_subscriptions started.');

$sql = "SELECT s.id, s.user_id, mp.public_id 
        FROM subscriptions s
        JOIN media_packages mp ON s.package_id = mp.id
        WHERE s.status = 'active' AND s.end_date < NOW()";

$stmt = $pdo->query($sql);
$expired_subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($expired_subscriptions)) {
    echo "No expired subscriptions found.
";
    $logger->info('Cron job: No expired subscriptions found.');
    exit;
}

$update_sql = "UPDATE subscriptions SET status = 'expired' WHERE id = ?";
$update_stmt = $pdo->prepare($update_sql);

// We need a TelegramAPI instance to send notifications
// In a real app, you might fetch this from a config or a bot repository
// For simplicity, we'll assume a default bot or skip notification if no token is found.
$bot_token = getenv('TELEGRAM_BOT_TOKEN'); // Assuming you have a way to get a token
$telegram_api = $bot_token ? new TGBot\TelegramAPI($bot_token) : null;

$count = 0;
foreach ($expired_subscriptions as $sub) {
    try {
        $update_stmt->execute([$sub['id']]);
        $count++;
        echo "Subscription ID {$sub['id']} for user {$sub['user_id']} has expired and been updated.\n";

        if ($telegram_api) {
            $message = "Langganan Anda untuk paket `{$sub['public_id']}` telah berakhir. Silakan perbarui untuk tetap mendapatkan akses.";
            $telegram_api->sendMessage($sub['user_id'], $message, 'Markdown');
        }

    } catch (
Exception $e) {
        $logger->error("Cron job: Failed to update subscription ID {$sub['id']}. Error: " . $e->getMessage());
    }
}

echo "Processed {$count} expired subscriptions.
";
$logger->info("Cron job: Processed {$count} expired subscriptions.");

