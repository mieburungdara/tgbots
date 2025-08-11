-- Changelog Database
--
-- Versi 4 (2025-08-11):
-- - Menambahkan kolom `raw_data` ke tabel `messages` untuk menyimpan JSON mentah.
-- - Menambahkan tabel `bot_settings` untuk pengaturan per bot.
--
-- Versi 3 (2025-08-10):
-- - Menambahkan tabel `media_files` untuk menyimpan informasi media.
--
-- Versi 2 (YYYY-MM-DD):
-- - Merombak struktur tabel untuk mendukung relasi many-to-many antara pengguna dan bot.
-- - Mengganti nama tabel `chats` menjadi `users` untuk kejelasan.
-- - Menambahkan kolom `last_name` dan `language_code` ke tabel `users`.
-- - Menghapus kolom `bot_id` dari tabel `users`.
-- - Menambahkan kolom `username` dan `first_name` ke tabel `bots`.
-- - Membuat tabel baru `rel_user_bot` untuk mengelola hubungan antara pengguna dan bot,
--   lengkap dengan status `is_blocked` dan `last_interaction_at`.
-- - Memperbarui foreign key di tabel `messages` untuk menunjuk ke `users`.
--
-- Versi 1 (Initial setup):
-- - Pembuatan awal tabel `bots`, `chats`, `messages`.

-- Skrip untuk membuat tabel database.
-- Sesuai instruksi, skrip ini akan menghapus tabel yang ada terlebih dahulu (jika ada)
-- dan membuatnya kembali dari awal.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Hapus tabel dalam urutan yang benar untuk menghindari masalah foreign key
DROP TABLE IF EXISTS `bot_settings`;
DROP TABLE IF EXISTS `media_files`;
DROP TABLE IF EXISTS `messages`;
DROP TABLE IF EXISTS `rel_user_bot`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `chats`; -- Hapus juga tabel lama jika ada
DROP TABLE IF EXISTS `bots`;

-- Tabel untuk menyimpan informasi bot
CREATE TABLE `bots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Nama bot untuk identifikasi di admin panel',
  `token` varchar(255) NOT NULL COMMENT 'Token API dari BotFather',
  `username` varchar(255) DEFAULT NULL COMMENT 'Username bot (@namabot)',
  `first_name` varchar(255) DEFAULT NULL COMMENT 'Nama depan bot',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel untuk menyimpan informasi pengguna yang berinteraksi dengan bot
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `telegram_id` bigint(20) NOT NULL COMMENT 'User ID unik dari Telegram',
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `language_code` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `telegram_id` (`telegram_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel untuk relasi antara pengguna dan bot
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

-- Tabel untuk menyimpan semua pesan
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'Referensi ke tabel users',
  `bot_id` int(11) NOT NULL COMMENT 'Referensi ke tabel bots',
  `telegram_message_id` bigint(20) NOT NULL COMMENT 'Message ID dari Telegram',
  `text` text,
  `raw_data` JSON NULL COMMENT 'Objek JSON mentah dari Telegram',
  `direction` enum('incoming','outgoing') NOT NULL COMMENT 'incoming: dari user, outgoing: dari admin',
  `telegram_timestamp` datetime NOT NULL COMMENT 'Waktu kirim pesan dari Telegram',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `bot_id` (`bot_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel untuk menyimpan informasi member (untuk fitur login panel)
DROP TABLE IF EXISTS `members`;
CREATE TABLE `members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'Referensi ke tabel users',
  `login_token` varchar(255) DEFAULT NULL,
  `token_created_at` timestamp NULL DEFAULT NULL,
  `token_used` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `fk_members_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel untuk menyimpan file media
CREATE TABLE `media_files` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT 'ID unik media (auto-increment).',
  `file_id` VARCHAR(255) NOT NULL COMMENT 'File ID dari Telegram untuk akses file.',
  `file_unique_id` VARCHAR(255) NOT NULL COMMENT 'ID unik permanen file (berbeda dari file_id).',
  `type` ENUM('photo', 'video', 'audio', 'voice', 'document', 'video_note', 'animation') NOT NULL COMMENT 'Jenis media.',
  `file_size` INT NULL COMMENT 'Ukuran file dalam bytes.',
  `width` INT NULL COMMENT 'Lebar media (foto/video/sticker).',
  `height` INT NULL COMMENT 'Tinggi media (foto/video/sticker).',
  `duration` INT NULL COMMENT 'Durasi (audio/voice/video/video_note) dalam detik.',
  `mime_type` VARCHAR(100) NULL COMMENT 'Tipe MIME file.',
  `file_name` VARCHAR(255) NULL COMMENT 'Nama file asli (untuk dokumen).',
  `caption` TEXT NULL COMMENT 'Caption media.',
  `caption_entities` JSON NULL COMMENT 'Entitas formatting (bold, link, dll.) dalam caption.',
  `user_id` BIGINT NULL COMMENT 'ID pengirim media.',
  `chat_id` BIGINT NULL COMMENT 'ID chat sumber media.',
  `message_id` BIGINT NULL COMMENT 'ID pesan terkait media.',
  `performer` VARCHAR(255) NULL COMMENT 'Nama artis/pembuat (audio).',
  `title` VARCHAR(255) NULL COMMENT 'Judul (audio/video).',
  `emoji` VARCHAR(10) NULL COMMENT 'Emoji terkait sticker.',
  `set_name` VARCHAR(255) NULL COMMENT 'Nama pack sticker (jika berasal dari pack).',
  `has_spoiler` BOOLEAN NULL COMMENT 'Apakah media memiliki spoiler.',
  `is_animated` BOOLEAN NULL COMMENT 'Apakah media animasi (sticker/GIF).',
  `thumbnail_id` BIGINT NULL COMMENT 'ID thumbnail (relasi ke tabel ini).',
  `file_path` VARCHAR(255) NULL COMMENT 'Path/URL file di server.',
  `file_url` VARCHAR(512) NULL COMMENT 'URL langsung ke file (jika dihosting di Telegram server).',
  `thumbnail_url` VARCHAR(512) NULL COMMENT 'URL thumbnail (jika tersedia).',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Waktu penyimpanan media.',
  `modified_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Waktu terakhir update.',
  UNIQUE KEY `file_unique_id` (`file_unique_id`),
  KEY `message_id` (`message_id`),
  KEY `user_id` (`user_id`),
  KEY `chat_id` (`chat_id`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel untuk menyimpan pengaturan spesifik per bot
CREATE TABLE `bot_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bot_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bot_setting_unique` (`bot_id`, `setting_key`),
  KEY `bot_id` (`bot_id`),
  CONSTRAINT `bot_settings_ibfk_1` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PERHATIAN:
-- Skrip ini hanya untuk setup awal.
-- Untuk pembaruan skema database selanjutnya, silakan buat file migrasi baru
-- di dalam direktori /migrations.

SET FOREIGN_KEY_CHECKS = 1;
