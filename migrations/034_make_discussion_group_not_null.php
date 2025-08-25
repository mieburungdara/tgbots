<?php

// Migration to make the discussion_group_id column NOT NULL.

// This script should be run by a migration runner that provides the $pdo object.
// If running standalone, we need to establish a connection.
if (!isset($pdo)) {
    // Attempt to include the database connection setup if not already available.
    // This path might need adjustment depending on the runner's working directory.
    $db_path = __DIR__ . '/../core/database.php';
    if (file_exists($db_path)) {
        require_once $db_path;
        $pdo = get_db_connection();
    } else {
        echo "Error: Database connection file not found for migration 034." . PHP_EOL;
        exit(1);
    }
}

if ($pdo) {
    try {
        $sql = "ALTER TABLE `seller_sales_channels` MODIFY COLUMN `discussion_group_id` BIGINT NOT NULL COMMENT 'ID grup diskusi yang terhubung';";
        $pdo->exec($sql);
        echo "Migration 034 successful: discussion_group_id is now NOT NULL." . PHP_EOL;
    } catch (Exception $e) {
        echo "Error executing migration 034: " . $e->getMessage() . PHP_EOL;
        throw $e;
    }
} else {
    echo "Error: Could not get database connection for migration 034." . PHP_EOL;
    exit(1);
}
