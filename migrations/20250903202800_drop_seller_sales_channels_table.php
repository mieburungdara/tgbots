<?php

/**
 * Migration to drop the obsolete seller_sales_channels table.
 */

echo "Running migration 20250903202800_drop_seller_sales_channels_table.php...\n";

if (!isset($pdo)) {
    echo "Error: PDO object not available.\n";
    return;
}

try {
    $sql = "DROP TABLE IF EXISTS `seller_sales_channels`;";
    $pdo->exec($sql);
    echo "Table `seller_sales_channels` dropped successfully (if it existed).\n";

} catch (Exception $e) {
    echo "An error occurred during migration: " . $e->getMessage() . "\n";
    throw $e;
}
