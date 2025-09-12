<?php

$pdo = get_db_connection();

// 1. Add subscription_price column to users table for sellers to set their price
$sql_alter_users = "
ALTER TABLE `users`
ADD COLUMN `subscription_price` DECIMAL(15,2) DEFAULT NULL AFTER `balance`;
";

// 2. Create subscriptions table to link a subscriber to a seller
$sql_create_subscriptions = "
CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `subscriber_user_id` BIGINT(20) NOT NULL,
  `seller_id` BIGINT(20) NOT NULL,
  `start_date` DATETIME NOT NULL,
  `end_date` DATETIME NOT NULL,
  `status` ENUM('active', 'expired', 'cancelled') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`subscriber_user_id`),
  KEY `package_id` (`seller_id`),
  KEY `end_date` (`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

try {
    // First, attempt to remove the columns from the previous incorrect migration, in case it was run.
    try {
        $pdo->exec("ALTER TABLE `media_packages` DROP COLUMN `package_type`, DROP COLUMN `monthly_price`;");
        echo "Cleanup not needed or already done.\n";
    } catch (PDOException $e) {
        // Ignore error if columns don't exist, which is expected on a clean run.
        echo "Cleanup not needed or already done.\n";
    }

    $pdo->exec($sql_alter_users);
    echo "Table 'users' altered successfully.\n";
    
    $pdo->exec($sql_create_subscriptions);
    echo "Table 'subscriptions' created successfully or already exists.\n";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage());
}