-- Mengubah kolom discussion_group_id menjadi NOT NULL untuk memastikan setiap channel jualan memiliki grup diskusi
ALTER TABLE `seller_sales_channels`
MODIFY COLUMN `discussion_group_id` BIGINT NOT NULL COMMENT 'ID grup diskusi yang terhubung';
