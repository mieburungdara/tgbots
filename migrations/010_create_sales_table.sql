-- Migration to create the sales table for tracking transactions
-- Dijalankan pada 2025-08-11
-- This table records every successful purchase, linking packages to buyers.

CREATE TABLE `sales` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `package_id` BIGINT NOT NULL COMMENT 'Referensi ke media_packages.id',
  `seller_user_id` INT(11) NOT NULL COMMENT 'Referensi ke users.id (penjual)',
  `buyer_user_id` INT(11) NOT NULL COMMENT 'Referensi ke users.id (pembeli)',
  `price` DECIMAL(15, 2) NOT NULL,
  `purchased_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `package_id` (`package_id`),
  KEY `seller_user_id` (`seller_user_id`),
  KEY `buyer_user_id` (`buyer_user_id`),
  CONSTRAINT `fk_sales_package` FOREIGN KEY (`package_id`) REFERENCES `media_packages`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sales_seller` FOREIGN KEY (`seller_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sales_buyer` FOREIGN KEY (`buyer_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Records successful sales transactions.';
