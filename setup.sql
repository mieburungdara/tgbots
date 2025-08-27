-- Changelog Database
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
DROP TABLE IF EXISTS `messages`;
DROP TABLE IF EXISTS `rel_user_bot`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `chats`; -- Hapus juga tabel lama jika ada
DROP TABLE IF EXISTS `bots`;

-- Tabel untuk menyimpan informasi bot
CREATE TABLE `bots`  (
  `id` bigint NOT NULL COMMENT 'Telegram Bot ID unik',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Nama bot untuk identifikasi di admin panel',
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Token API dari BotFather',
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'Username bot (@namabot)',
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'Nama depan bot',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `token`(`token` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- Tabel untuk menyimpan informasi pengguna yang berinteraksi dengan bot
CREATE TABLE `users`  (
  `id` bigint NOT NULL COMMENT 'User ID unik dari Telegram',
  `public_seller_id` char(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `seller_package_sequence` int UNSIGNED NOT NULL DEFAULT 0,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `language_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `balance` decimal(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Saldo internal pengguna',
  `status` enum('active','blocked') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `public_seller_id`(`public_seller_id` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

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

-- PERHATIAN:
-- Skrip ini hanya untuk setup awal.
-- Untuk pembaruan skema database selanjutnya, silakan buat file migrasi baru
-- di dalam direktori /migrations.

SET FOREIGN_KEY_CHECKS = 1;
