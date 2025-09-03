<?php

/**
 * Migration to align schema with version 5.2.0 changes.
 *
 * This migration performs the following actions safely:
 * 1. Renames the `post_packages` table to `media_packages` if it exists.
 * 2. Adds the `assigned_feature` column to the `bots` table if it doesn't exist.
 * 3. Adds the `post_type` column to the `media_packages` table if it doesn't exist.
 * 4. Adds the `category` column to the `media_packages` table if it doesn't exist.
 */

echo "Running migration 20250903141000_update_schema_to_5_2_0.php...\n";

// The PDO object $pdo is available in the scope of the migration runner.
if (!isset($pdo)) {
    echo "Error: PDO object not available. This script must be run through the migration runner.\n";
    return;
}

// Helper function to check if a table exists
function tableExists(PDO $pdo, $tableName) {
    try {
        $result = $pdo->query("SELECT 1 FROM {$tableName} LIMIT 1");
    } catch (Exception $e) {
        return false;
    }
    return $result !== false;
}

// Helper function to check if a column exists in a table
function columnExists(PDO $pdo, $tableName, $columnName) {
    if (!tableExists($pdo, $tableName)) return false;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$tableName}`");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return in_array(strtolower($columnName), array_map('strtolower', $columns), true);
    } catch (Exception $e) {
        // If SHOW COLUMNS fails, assume column doesn't exist to be safe
        return false;
    }
}


try {
    // 1. Rename `post_packages` to `media_packages`
    if (tableExists($pdo, 'post_packages') && !tableExists($pdo, 'media_packages')) {
        echo "Renaming table `post_packages` to `media_packages`...\n";
        $pdo->exec("ALTER TABLE `post_packages` RENAME TO `media_packages`;");
        echo "Table renamed successfully.\n";
    } else {
        echo "Skipping table rename (either `post_packages` does not exist or `media_packages` already exists).\n";
    }

    // 2. Add `assigned_feature` to `bots` table
    if (tableExists($pdo, 'bots') && !columnExists($pdo, 'bots', 'assigned_feature')) {
        echo "Adding `assigned_feature` column to `bots` table...\n";
        $pdo->exec("ALTER TABLE `bots` ADD COLUMN `assigned_feature` enum('sell','rate','tanya') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'Fitur spesifik yang ditugaskan untuk bot ini (jika ada).' AFTER `first_name`;");
        echo "Column `assigned_feature` added successfully.\n";
    } else {
        echo "Skipping `bots`.`assigned_feature` (table or column may not exist or already exists).\n";
    }

    // 3. Add `post_type` to `media_packages` table
    if (tableExists($pdo, 'media_packages') && !columnExists($pdo, 'media_packages', 'post_type')) {
        echo "Adding `post_type` column to `media_packages` table...\n";
        $pdo->exec("ALTER TABLE `media_packages` ADD COLUMN `post_type` enum('sell','rate','tanya') NOT NULL DEFAULT 'sell' COMMENT 'Jenis postingan untuk fitur yang berbeda.' AFTER `status`;");
        echo "Column `post_type` added successfully.\n";
    } else {
        echo "Skipping `media_packages`.`post_type` (table or column may not exist or already exists).\n";
    }

    // 4. Add `category` to `media_packages` table
    if (tableExists($pdo, 'media_packages') && !columnExists($pdo, 'media_packages', 'category')) {
        echo "Adding `category` column to `media_packages` table...\n";
        $pdo->exec("ALTER TABLE `media_packages` ADD COLUMN `category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'Kategori spesifik untuk fitur rate/tanya.' AFTER `post_type`;");
        echo "Column `category` added successfully.\n";
    } else {
        echo "Skipping `media_packages`.`category` (table or column may not exist or already exists).\n";
    }

    echo "Migration completed successfully.\n";

} catch (Exception $e) {
    echo "An error occurred during migration: " . $e->getMessage() . "\n";
    // The migration runner should handle transactions, but we re-throw to be safe
    throw $e;
}
