-- Tabel untuk menyimpan channel jualan yang didaftarkan oleh penjual
CREATE TABLE `seller_sales_channels` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `seller_user_id` INT(11) NOT NULL,
  `channel_id` BIGINT(20) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_seller` (`seller_user_id`),
  KEY `idx_channel_id` (`channel_id`),
  CONSTRAINT `fk_seller_sales_channels_user_id` FOREIGN KEY (`seller_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
