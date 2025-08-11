-- Migration: Memperbaiki tabel members agar sesuai dengan skema baru.
-- Mengubah nama kolom chat_id menjadi user_id dan memperbarui foreign key.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `members`
  DROP FOREIGN KEY `members_ibfk_1`,
  CHANGE `chat_id` `user_id` INT(11) NOT NULL COMMENT 'Referensi ke tabel users',
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD CONSTRAINT `members_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;
