-- SQL migration to allow NULL for user_id in the messages table.
-- This is necessary to store messages that are not associated with a specific user,
-- such as channel posts.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Modify the user_id column to be nullable.
-- The foreign key constraint will still work for non-NULL values.
ALTER TABLE `messages`
  MODIFY COLUMN `user_id` int(11) NULL COMMENT 'Referensi ke tabel users, bisa NULL untuk channel posts';

SET FOREIGN_KEY_CHECKS = 1;
