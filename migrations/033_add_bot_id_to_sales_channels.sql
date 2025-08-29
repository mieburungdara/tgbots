-- Menambahkan kolom bot_id untuk menautkan channel jualan ke bot tertentu
ALTER TABLE `seller_sales_channels`
ADD COLUMN `bot_id` INT NULL COMMENT 'Referensi ke tabel bots' AFTER `seller_user_id`,
ADD CONSTRAINT `fk_sales_channels_bot_id` FOREIGN KEY (`bot_id`) REFERENCES `bots`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;
