<?php

echo "Running migration 20250904223800_set_null_moderation_channel_for_sell_feature.php...\n";

if (!isset($pdo)) {
    echo "Error: PDO object not available. This script must be run through the migration runner.\n";
    exit(1);
}

try {
    $sql = "UPDATE `feature_channels` SET `moderation_channel_id` = NULL WHERE `feature_type` = 'sell';";

    $statement = $pdo->prepare($sql);
    $statement->execute();

    $rowCount = $statement->rowCount();

    echo "Migration completed. Updated {$rowCount} rows.\n";

} catch (Exception $e) {
    echo "An error occurred during migration: " . $e->getMessage() . "\n";
    throw $e;
}
