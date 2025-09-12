<?php

$pdo = get_db_connection();

$sql = "
CREATE TABLE IF NOT EXISTS `offers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `package_id` int(11) NOT NULL,
  `buyer_user_id` int(11) NOT NULL,
  `seller_user_id` int(11) NOT NULL,
  `offer_price` decimal(15,2) NOT NULL,
  `status` enum('pending','accepted','rejected','expired','completed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `package_id` (`package_id`),
  KEY `buyer_user_id` (`buyer_user_id`),
  KEY `seller_user_id` (`seller_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

try {
    $pdo->exec($sql);
    echo "Table 'offers' created successfully or already exists.\n";
} catch (PDOException $e) {
    die("Could not create table 'offers': " . $e->getMessage());
}

