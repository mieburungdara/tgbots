-- SQL migration to restructure the schema, add new tables, and rename existing ones.
-- This script is for databases that are already on version 1.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Langkah 1: Hapus foreign key yang ada untuk menghindari error
-- Asumsi nama constraint adalah default. Mungkin perlu disesuaikan jika nama custom.
ALTER TABLE `messages` DROP FOREIGN KEY `messages_ibfk_1`;
ALTER TABLE `chats` DROP FOREIGN KEY `chats_ibfk_1`;

-- Langkah 2: Ubah tabel `bots`
ALTER TABLE `bots`
  ADD COLUMN `username` varchar(255) DEFAULT NULL COMMENT 'Username bot (@namabot)' AFTER `token`,
  ADD COLUMN `first_name` varchar(255) DEFAULT NULL COMMENT 'Nama depan bot' AFTER `username`;

-- Langkah 3: Ubah tabel `chats` sebelum di-rename
-- Hapus unique key yang melibatkan bot_id
ALTER TABLE `chats` DROP KEY `bot_chat`;
-- Hapus kolom bot_id
ALTER TABLE `chats` DROP COLUMN `bot_id`;
-- Tambah kolom baru
ALTER TABLE `chats`
  ADD COLUMN `last_name` varchar(255) DEFAULT NULL AFTER `first_name`,
  ADD COLUMN `language_code` varchar(10) DEFAULT NULL AFTER `username`;
-- Ubah nama kolom chat_id menjadi telegram_id dan pastikan unik
ALTER TABLE `chats` CHANGE `chat_id` `telegram_id` bigint(20) NOT NULL COMMENT 'User ID unik dari Telegram';
ALTER TABLE `chats` ADD UNIQUE KEY `telegram_id` (`telegram_id`);

-- Langkah 4: Rename tabel `chats` menjadi `users`
RENAME TABLE `chats` TO `users`;

-- Langkah 5: Buat tabel relasi `rel_user_bot`
CREATE TABLE `rel_user_bot` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `bot_id` int(11) NOT NULL,
  `is_blocked` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0: tidak diblokir, 1: diblokir',
  `last_interaction_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_bot_unique` (`user_id`,`bot_id`),
  KEY `user_id` (`user_id`),
  KEY `bot_id` (`bot_id`),
  CONSTRAINT `rel_user_bot_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rel_user_bot_ibfk_2` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Langkah 6: Perbarui tabel `messages`
-- Ubah nama kolom
ALTER TABLE `messages` CHANGE `chat_id` `user_id` int(11) NOT NULL COMMENT 'Referensi ke tabel users';
-- Tambahkan kembali foreign key ke tabel `users` yang baru
ALTER TABLE `messages` ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;
