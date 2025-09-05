<?php

echo "Running migration 20250904232100_backfill_channel_names.php...\n";

if (!isset($pdo)) {
    echo "Error: PDO object not available. This script must be run through the migration runner.\n";
    exit(1);
}

// We need the TelegramAPI class. Assuming it can be autoloaded or is already included.
// If not, a require_once would be needed here.
// require_once __DIR__ . '/../core/TelegramAPI.php';

try {
    // 1. Fetch all feature channels that need updating
    $stmt_channels = $pdo->query("SELECT * FROM feature_channels WHERE name IS NULL OR discussion_group_name IS NULL OR name = '' OR discussion_group_name = ''");
    $channels_to_update = $stmt_channels->fetchAll(PDO::FETCH_ASSOC);

    if (empty($channels_to_update)) {
        echo "No channels need updating. Migration complete.\n";
        exit(0);
    }

    echo "Found " . count($channels_to_update) . " channels to update.\n";

    // Prepare update statement
    $stmt_update = $pdo->prepare("UPDATE feature_channels SET name = ?, discussion_group_name = ? WHERE id = ?");

    // Cache bot tokens to reduce DB queries
    $bot_tokens = [];
    $bot_apis = [];

    foreach ($channels_to_update as $channel) {
        $bot_id = $channel['managing_bot_id'];

        try {
            // Get or cache the bot's API instance
            if (!isset($bot_apis[$bot_id])) {
                if (!isset($bot_tokens[$bot_id])) {
                    $stmt_bot = $pdo->prepare("SELECT token FROM bots WHERE id = ?");
                    $stmt_bot->execute([$bot_id]);
                    $bot_token = $stmt_bot->fetchColumn();
                    if (!$bot_token) {
                        echo "Warning: Could not find token for bot ID {$bot_id}. Skipping channel ID {$channel['id']}.\n";
                        continue;
                    }
                    $bot_tokens[$bot_id] = $bot_token;
                }
                // Assuming autoloader can find this class
                $bot_apis[$bot_id] = new \TGBot\TelegramAPI($bot_tokens[$bot_id]);
            }

            $api = $bot_apis[$bot_id];
            $new_channel_name = $channel['name'];
            $new_group_name = $channel['discussion_group_name'];

            // Fetch and update public channel name if needed
            if (empty($new_channel_name) && !empty($channel['public_channel_id'])) {
                $channel_info = $api->getChat($channel['public_channel_id']);
                if ($channel_info && $channel_info['ok']) {
                    $new_channel_name = $channel_info['result']['title'];
                    echo "Fetched channel name '{$new_channel_name}' for ID {$channel['public_channel_id']}.\n";
                } else {
                    echo "Warning: Could not fetch info for channel ID {$channel['public_channel_id']}. Error: " . ($channel_info['description'] ?? 'Unknown') . "\n";
                }
            }

            // Fetch and update discussion group name if needed
            if (empty($new_group_name) && !empty($channel['discussion_group_id'])) {
                $group_info = $api->getChat($channel['discussion_group_id']);
                 if ($group_info && $group_info['ok']) {
                    $new_group_name = $group_info['result']['title'];
                    echo "Fetched group name '{$new_group_name}' for ID {$channel['discussion_group_id']}.\n";
                } else {
                    echo "Warning: Could not fetch info for group ID {$channel['discussion_group_id']}. Error: " . ($group_info['description'] ?? 'Unknown') . "\n";
                }
            }

            // Update the database only if there are changes
            if ($new_channel_name !== $channel['name'] || $new_group_name !== $channel['discussion_group_name']) {
                $stmt_update->execute([$new_channel_name, $new_group_name, $channel['id']]);
                echo "Updated DB for feature_channel ID {$channel['id']}.\n";
            }

            // Sleep for a short duration to avoid hitting API rate limits
            usleep(500000); // 0.5 seconds

        } catch (Exception $e) {
            echo "An error occurred while processing channel ID {$channel['id']}: " . $e->getMessage() . "\n";
            // Continue with the next channel
        }
    }

    echo "Migration completed successfully.\n";

} catch (Exception $e) {
    echo "An error occurred during migration: " . $e->getMessage() . "\n";
    throw $e;
}
