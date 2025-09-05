<?php

echo "Running migration: Change public_seller_id length from 4 to 5\n";

if (!isset($pdo)) {
    echo "Error: PDO object not available. This script must be run through the migration runner.\n";
    exit(1);
}

try {
    $sql = "ALTER TABLE `users` MODIFY COLUMN `public_seller_id` char(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'ID publik unik untuk penjual (5 huruf).';";
    $pdo->exec($sql);
    echo "Migration completed successfully: `users`.`public_seller_id` is now char(5).\n";
} catch (Exception $e) {
    echo "An error occurred during migration: " . $e->getMessage() . "\n";
    throw $e;
}
