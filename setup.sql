-- Skrip untuk membuat tabel database.
-- Sesuai instruksi, skrip ini akan menghapus tabel yang ada terlebih dahulu (jika ada)
-- dan membuatnya kembali dari awal.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Tabel untuk menyimpan informasi bot
DROP TABLE IF EXISTS `bots`;
CREATE TABLE `bots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Nama bot untuk identifikasi di admin panel',
  `token` varchar(255) NOT NULL COMMENT 'Token API dari BotFather',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel untuk menyimpan informasi chat/pengguna yang berinteraksi dengan bot
DROP TABLE IF EXISTS `chats`;
CREATE TABLE `chats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bot_id` int(11) NOT NULL,
  `chat_id` bigint(20) NOT NULL COMMENT 'Chat ID unik dari Telegram',
  `first_name` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bot_chat` (`bot_id`, `chat_id`),
  KEY `bot_id` (`bot_id`),
  CONSTRAINT `chats_ibfk_1` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel untuk menyimpan semua pesan
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chat_id` int(11) NOT NULL COMMENT 'Referensi ke tabel chats',
  `telegram_message_id` bigint(20) NOT NULL COMMENT 'Message ID dari Telegram',
  `text` text,
  `direction` enum('incoming','outgoing') NOT NULL COMMENT 'incoming: dari user, outgoing: dari admin',
  `telegram_timestamp` datetime NOT NULL COMMENT 'Waktu kirim pesan dari Telegram',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `chat_id` (`chat_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`chat_id`) REFERENCES `chats` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PERHATIAN:
-- Skrip ini hanya untuk setup awal.
-- Untuk pembaruan skema database selanjutnya, silakan buat file migrasi baru
-- di dalam direktori /migrations.

SET FOREIGN_KEY_CHECKS = 1;
