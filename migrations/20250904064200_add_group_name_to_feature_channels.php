<?php

echo "Running migration 20250904064200_add_group_name_to_feature_channels.php...\n";

if (!isset($pdo)) {
    echo "Error: PDO object not available. This script must be run through the migration runner.\n";
    exit(1);
}

// Helper function to check if a column exists to make this script safe to re-run.
function columnExists_20250904064200(PDO $pdo, $tableName, $columnName) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$tableName}` LIKE '{$columnName}'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        // If the table doesn't exist, the column certainly doesn't.
        return false;
    }
}

try {
    $tableName = 'feature_channels';
    $columnName = 'discussion_group_name';

    if (!columnExists_20250904064200($pdo, $tableName, $columnName)) {
        echo "Adding `{$columnName}` column to `{$tableName}` table...\n";
        $sql = "ALTER TABLE `{$tableName}` ADD `{$columnName}` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Nama grup diskusi dari Telegram' AFTER `discussion_group_id`;";
        $pdo->exec($sql);
        echo "Column `{$columnName}` added successfully.\n";
    } else {
        echo "Column `{$columnName}` already exists in `{$tableName}`. Skipping.\n";
    }

    echo "Migration completed successfully.\n";

} catch (Exception $e) {
    echo "An error occurred during migration: " . $e->getMessage() . "\n";
    throw $e;
}
