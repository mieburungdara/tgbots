-- Menambahkan kolom untuk menyimpan ID grup diskusi yang terhubung ke channel jualan
ALTER TABLE `seller_sales_channels`
ADD COLUMN `discussion_group_id` BIGINT NULL DEFAULT NULL COMMENT 'ID grup diskusi yang terhubung' AFTER `channel_id`;
