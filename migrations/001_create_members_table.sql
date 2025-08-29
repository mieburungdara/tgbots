-- Migration: Membuat tabel members
-- Tabel ini digunakan untuk menyimpan informasi member dan token login mereka.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Tabel untuk menyimpan informasi member
DROP TABLE IF EXISTS `members`;
CREATE TABLE `members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chat_id` int(11) NOT NULL,
  `login_token` varchar(255) DEFAULT NULL,
  `token_created_at` timestamp NULL DEFAULT NULL,
  `token_used` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `chat_id` (`chat_id`),
  CONSTRAINT `members_ibfk_1` FOREIGN KEY (`chat_id`) REFERENCES `chats` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
