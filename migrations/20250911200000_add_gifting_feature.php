<?php

require_once __DIR__ . '/../../core/database.php';

$pdo = get_db_connection();

$sql = "
ALTER TABLE `sales`
ADD COLUMN `granted_to_user_id` BIGINT NULL DEFAULT NULL COMMENT 'ID pengguna yang menerima akses konten, jika berbeda dari pembeli (untuk hadiah).' AFTER `buyer_user_id`,
ADD INDEX `idx_granted_to_user_id` (`granted_to_user_id`);
";

// We also need to add a foreign key, but let's do it carefully
// to avoid issues if the column already exists from a failed migration.

try {
    $pdo->exec($sql);
    echo "Column 'granted_to_user_id' added to 'sales' table successfully.\n";

    // Add the foreign key constraint
    $fk_sql = "ALTER TABLE `sales` ADD CONSTRAINT `fk_sales_granted_to` FOREIGN KEY (`granted_to_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;";
    $pdo->exec($fk_sql);
    echo "Foreign key for 'granted_to_user_id' added successfully.\n";

} catch (PDOException $e) {
    // Check if the error is about a duplicate column, which we can ignore.
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column 'granted_to_user_id' already exists. Skipping column creation.\n";
    } else if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "Foreign key 'fk_sales_granted_to' already exists. Skipping FK creation.\n";
    } else {
        die("Migration failed: " . $e->getMessage());
    }
}

// Update existing rows where granted_to_user_id is NULL to be the same as buyer_user_id
$update_sql = "UPDATE `sales` SET `granted_to_user_id` = `buyer_user_id` WHERE `granted_to_user_id` IS NULL;";
try {
    $pdo->exec($update_sql);
    echo "Updated existing sales records to set granted_to_user_id.";
} catch (PDOException $e) {
    die("Failed to update existing sales records: " . $e->getMessage());
}
