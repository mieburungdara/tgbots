/*
 Navicat Premium Dump SQL

 Source Server         : tbox
 Source Server Type    : MySQL
 Source Server Version : 101113 (10.11.13-MariaDB-cll-lve)
 Source Host           : 45.143.81.225:3306
 Source Schema         : u1574101_tgbots

 Target Server Type    : MySQL
 Target Server Version : 101113 (10.11.13-MariaDB-cll-lve)
 File Encoding         : 65001

 Date: 04/09/2025 03:52:52
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for api_request_logs
-- ----------------------------
DROP TABLE IF EXISTS `api_request_logs`;
CREATE TABLE `api_request_logs`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `bot_id` bigint NOT NULL COMMENT 'ID bot yang melakukan request',
  `method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Metode API Telegram yang dipanggil',
  `request_payload` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT 'Payload request yang dikirim dalam format JSON',
  `response_payload` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT 'Payload response yang diterima dalam format JSON',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT 'Waktu pembuatan log',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `bot_id`(`bot_id` ASC) USING BTREE,
  CONSTRAINT `api_request_logs_ibfk_1` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 53 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = 'Mencatat semua request API yang dilakukan ke Telegram untuk tujuan debugging.' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for app_logs
-- ----------------------------
DROP TABLE IF EXISTS `app_logs`;
CREATE TABLE `app_logs`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `level` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Level log (e.g., info, error, warning)',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Pesan log',
  `context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL COMMENT 'Konteks tambahan dalam format JSON',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT 'Waktu pembuatan log',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 722 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Menyimpan log aplikasi internal untuk debugging dan monitoring.' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for balance_transactions
-- ----------------------------
DROP TABLE IF EXISTS `balance_transactions`;
CREATE TABLE `balance_transactions`  (
  `id` bigint NOT NULL AUTO_INCREMENT COMMENT 'ID unik transaksi.',
  `user_id` bigint NOT NULL COMMENT 'ID pengguna yang saldonya diubah, merujuk ke users.id.',
  `amount` decimal(15, 2) NOT NULL COMMENT 'Jumlah yang ditambahkan (positif) atau dikurangi (negatif).',
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'admin_adjustment' COMMENT 'Jenis transaksi (misal: admin_adjustment, refund, etc).',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT 'Catatan atau alasan dari admin untuk transaksi ini.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT 'Waktu transaksi dibuat.',
  `admin_telegram_id` bigint NULL DEFAULT NULL COMMENT 'ID admin Telegram yang melakukan penyesuaian saldo.',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `user_id`(`user_id` ASC) USING BTREE,
  CONSTRAINT `fk_balance_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = 'Mencatat riwayat penyesuaian saldo oleh admin.' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for bot_channel_usage
-- ----------------------------
DROP TABLE IF EXISTS `bot_channel_usage`;
CREATE TABLE `bot_channel_usage`  (
  `bot_id` bigint NOT NULL COMMENT 'ID bot yang menggunakan channel, merujuk ke tabel bots.',
  `last_used_channel_id` int NOT NULL COMMENT 'ID channel terakhir yang digunakan, merujuk ke private_channels.',
  PRIMARY KEY (`bot_id`) USING BTREE,
  INDEX `last_used_channel_id`(`last_used_channel_id` ASC) USING BTREE,
  CONSTRAINT `bot_channel_usage_ibfk_1` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `bot_channel_usage_ibfk_2` FOREIGN KEY (`last_used_channel_id`) REFERENCES `private_channels` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = latin1 COLLATE = latin1_swedish_ci COMMENT = 'Mencatat channel penyimpanan mana yang terakhir digunakan oleh setiap bot untuk strategi round-robin.' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for bot_settings
-- ----------------------------
DROP TABLE IF EXISTS `bot_settings`;
CREATE TABLE `bot_settings`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `bot_id` bigint NOT NULL COMMENT 'ID bot yang memiliki pengaturan.',
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Kunci pengaturan (misal: welcome_message).',
  `setting_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Nilai pengaturan.',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `bot_setting_unique`(`bot_id` ASC, `setting_key` ASC) USING BTREE,
  CONSTRAINT `bot_settings_ibfk_1` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = 'Menyimpan pengaturan kunci-nilai untuk setiap bot.' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for bots
-- ----------------------------
DROP TABLE IF EXISTS `bots`;
CREATE TABLE `bots`  (
  `id` bigint NOT NULL COMMENT 'Telegram Bot ID unik',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Nama bot untuk identifikasi di admin panel',
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Token API dari BotFather',
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'Username bot (@namabot)',
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'Nama depan bot',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT 'Waktu pembuatan record',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `token`(`token` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = 'Menyimpan informasi bot yang terhubung ke sistem.' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for channel_post_packages
-- ----------------------------
DROP TABLE IF EXISTS `channel_post_packages`;
CREATE TABLE `channel_post_packages`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `channel_id` bigint NOT NULL COMMENT 'ID channel tempat post dibuat',
  `message_id` bigint NOT NULL COMMENT 'ID pesan di channel',
  `package_id` bigint NOT NULL COMMENT 'ID paket media yang diposting',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT 'Waktu pembuatan record',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `channel_message_idx`(`channel_id` ASC, `message_id` ASC) USING BTREE,
  INDEX `package_id_fk_idx`(`package_id` ASC) USING BTREE,
  CONSTRAINT `channel_post_packages_package_id_fk` FOREIGN KEY (`package_id`) REFERENCES `media_packages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 18 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Menghubungkan postingan di channel dengan paket media yang sesuai.' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for media_files
-- ----------------------------
DROP TABLE IF EXISTS `media_files`;
CREATE TABLE `media_files`  (
  `id` bigint NOT NULL AUTO_INCREMENT COMMENT 'ID unik media (auto-increment).',
  `type` enum('photo','video','audio','voice','document','video_note','animation') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Jenis media.',
  `file_size` int NULL DEFAULT NULL COMMENT 'Ukuran file dalam bytes.',
  `width` int NULL DEFAULT NULL COMMENT 'Lebar media (foto/video/sticker).',
  `height` int NULL DEFAULT NULL COMMENT 'Tinggi media (foto/video/sticker).',
  `duration` int NULL DEFAULT NULL COMMENT 'Durasi (audio/voice/video/video_note) dalam detik.',
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'Tipe MIME file.',
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'Nama file asli (untuk dokumen).',
  `caption` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT 'Caption media.',
  `caption_entities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL COMMENT 'Entitas formatting (bold, link, dll.) dalam caption.',
  `user_id` bigint NULL DEFAULT NULL COMMENT 'ID pengirim media.',
  `chat_id` bigint NULL DEFAULT NULL COMMENT 'ID chat sumber media.',
  `message_id` bigint NULL DEFAULT NULL COMMENT 'ID pesan terkait media.',
  `package_id` bigint NULL DEFAULT NULL COMMENT 'Referensi ke media_packages',
  `media_group_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'ID untuk mengelompokkan media yang dikirim bersamaan',
  `performer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'Nama artis/pembuat (audio).',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'Judul (audio/video).',
  `emoji` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'Emoji terkait sticker.',
  `set_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'Nama pack sticker (jika berasal dari pack).',
  `has_spoiler` tinyint(1) NULL DEFAULT NULL COMMENT 'Apakah media memiliki spoiler.',
  `is_animated` tinyint(1) NULL DEFAULT NULL COMMENT 'Apakah media animasi (sticker/GIF).',
  `thumbnail_id` bigint NULL DEFAULT NULL COMMENT 'ID thumbnail (relasi ke tabel ini).',
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'Path/URL file di server.',
  `file_url` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'URL langsung ke file (jika dihosting di Telegram server).',
  `thumbnail_url` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'URL thumbnail (jika tersedia).',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT 'Waktu penyimpanan media.',
  `modified_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Waktu terakhir update.',
  `storage_channel_id` bigint NULL DEFAULT NULL COMMENT 'ID channel tempat media disimpan.',
  `storage_message_id` bigint NULL DEFAULT NULL COMMENT 'ID pesan di channel tempat media disimpan.',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `message_id`(`message_id` ASC) USING BTREE,
  INDEX `user_id`(`user_id` ASC) USING BTREE,
  INDEX `chat_id`(`chat_id` ASC) USING BTREE,
  INDEX `type`(`type` ASC) USING BTREE,
  INDEX `package_id`(`package_id` ASC) USING BTREE,
  CONSTRAINT `fk_media_package` FOREIGN KEY (`package_id`) REFERENCES `media_packages` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = 'Menyimpan detail file media yang diterima dari pengguna.' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for media_packages
-- ----------------------------
DROP TABLE IF EXISTS `media_packages`;
CREATE TABLE `media_packages`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `public_id` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'ID publik yang ditampilkan ke pengguna (e.g., ABCD_0001).',
  `seller_user_id` bigint NOT NULL COMMENT 'Referensi ke tabel users (penjual)',
  `bot_id` bigint NOT NULL COMMENT 'Referensi ke tabel bots',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT 'Deskripsi paket media',
  `thumbnail_media_id` bigint NULL DEFAULT NULL COMMENT 'ID media yang dijadikan thumbnail untuk paket ini.',
  `price` decimal(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Harga paket media.',
  `status` enum('pending','available','sold','rejected','deleted') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending' COMMENT 'Status paket media.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT 'Waktu pembuatan paket.',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Waktu terakhir paket diubah.',
  `protect_content` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0: tidak diproteksi, 1: diproteksi dari penyalinan.',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `public_id`(`public_id` ASC) USING BTREE,
  INDEX `seller_user_id`(`seller_user_id` ASC) USING BTREE,
  INDEX `bot_id`(`bot_id` ASC) USING BTREE,
  INDEX `fk_package_thumbnail`(`thumbnail_media_id` ASC) USING BTREE,
  CONSTRAINT `fk_package_bot` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_package_seller` FOREIGN KEY (`seller_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_package_thumbnail` FOREIGN KEY (`thumbnail_media_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = 'Menyimpan informasi paket media yang dijual.' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for messages
-- ----------------------------
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` bigint NULL DEFAULT NULL COMMENT 'Referensi ke tabel users, bisa NULL untuk channel posts',
  `bot_id` bigint NULL DEFAULT NULL COMMENT 'ID bot yang menerima atau mengirim pesan.',
  `telegram_message_id` bigint NOT NULL COMMENT 'Message ID dari Telegram',
  `chat_id` bigint NULL DEFAULT NULL COMMENT 'ID dari chat, grup, atau channel asal pesan',
  `chat_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'Tipe chat: private, group, supergroup, channel',
  `update_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'Tipe update dari Telegram: message, edited_message, channel_post, dll.',
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT 'Isi teks pesan.',
  `raw_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL COMMENT 'Objek JSON mentah dari Telegram',
  `direction` enum('incoming','outgoing') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'incoming: dari user, outgoing: dari admin',
  `telegram_timestamp` datetime NOT NULL COMMENT 'Waktu kirim pesan dari Telegram',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT 'Waktu penyimpanan record.',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `user_id`(`user_id` ASC) USING BTREE,
  INDEX `bot_id`(`bot_id` ASC) USING BTREE,
  CONSTRAINT `fk_messages_bot` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_messages_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 44 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = 'Menyimpan riwayat pesan yang masuk dan keluar dari semua bot.' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for migrations
-- ----------------------------
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `migration_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Nama file migrasi yang telah dieksekusi.',
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT 'Waktu eksekusi migrasi.',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `migration_file`(`migration_file` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 38 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = 'Mencatat migrasi database yang telah dijalankan.' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for private_channel_bots
-- ----------------------------
DROP TABLE IF EXISTS `private_channel_bots`;
CREATE TABLE `private_channel_bots`  (
  `private_channel_id` int NOT NULL COMMENT 'ID channel penyimpanan, merujuk ke private_channels.',
  `bot_id` bigint NOT NULL COMMENT 'ID bot yang terhubung, merujuk ke bots.',
  `verified_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu verifikasi bot sebagai admin di channel.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT 'Waktu pembuatan relasi.',
  PRIMARY KEY (`private_channel_id`, `bot_id`) USING BTREE,
  INDEX `bot_id`(`bot_id` ASC) USING BTREE,
  CONSTRAINT `private_channel_bots_ibfk_1` FOREIGN KEY (`private_channel_id`) REFERENCES `private_channels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `private_channel_bots_ibfk_2` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Tabel pivot untuk hubungan many-to-many antara bot dan channel penyimpanan.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for private_channels
-- ----------------------------
DROP TABLE IF EXISTS `private_channels`;
CREATE TABLE `private_channels`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `channel_id` bigint NOT NULL COMMENT 'ID unik channel dari Telegram.',
  `name` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL COMMENT 'Nama channel untuk identifikasi.',
  `created_at` timestamp NULL DEFAULT current_timestamp COMMENT 'Waktu pembuatan record.',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `channel_id`(`channel_id` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 9 CHARACTER SET = latin1 COLLATE = latin1_swedish_ci COMMENT = 'Menyimpan daftar channel pribadi untuk penggunaan internal (misal: channel penyimpanan file).' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for raw_updates
-- ----------------------------
DROP TABLE IF EXISTS `raw_updates`;
CREATE TABLE `raw_updates`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Payload update mentah dari Telegram dalam format JSON.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT 'Waktu pembuatan record.',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 328 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Menyimpan semua update mentah yang diterima dari Telegram untuk tujuan debug.' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for rel_user_bot
-- ----------------------------
DROP TABLE IF EXISTS `rel_user_bot`;
CREATE TABLE `rel_user_bot`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL COMMENT 'ID pengguna, merujuk ke tabel users.',
  `bot_id` bigint NOT NULL COMMENT 'ID bot, merujuk ke tabel bots.',
  `is_blocked` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0: tidak diblokir, 1: diblokir',
  `last_interaction_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu interaksi terakhir pengguna dengan bot.',
  `state` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'Status percakapan pengguna saat ini (mis: awaiting_price)',
  `state_context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL COMMENT 'Data sementara terkait state (mis: media_group_id yang sedang dikirim)',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `user_bot_unique`(`user_id` ASC, `bot_id` ASC) USING BTREE,
  INDEX `fk_rel_bot`(`bot_id` ASC) USING BTREE,
  CONSTRAINT `fk_rel_bot` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_rel_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 44 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = 'Menghubungkan pengguna dengan bot dan menyimpan status interaksi.' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for roles
-- ----------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Nama peran (e.g., admin, seller, member).',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `name`(`name` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 19 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = 'Menyimpan daftar peran pengguna.' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for sales
-- ----------------------------
DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `package_id` bigint NOT NULL COMMENT 'Referensi ke media_packages.id',
  `seller_user_id` bigint NOT NULL COMMENT 'Referensi ke users.id (penjual)',
  `buyer_user_id` bigint NOT NULL COMMENT 'Referensi ke users.id (pembeli)',
  `price` decimal(15, 2) NOT NULL COMMENT 'Harga saat penjualan terjadi.',
  `purchased_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT 'Waktu pembelian.',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `package_id`(`package_id` ASC) USING BTREE,
  INDEX `seller_user_id`(`seller_user_id` ASC) USING BTREE,
  INDEX `buyer_user_id`(`buyer_user_id` ASC) USING BTREE,
  CONSTRAINT `fk_sales_buyer` FOREIGN KEY (`buyer_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_sales_package` FOREIGN KEY (`package_id`) REFERENCES `media_packages` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_sales_seller` FOREIGN KEY (`seller_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = 'Mencatat transaksi penjualan yang berhasil.' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for seller_sales_channels
-- ----------------------------
DROP TABLE IF EXISTS `seller_sales_channels`;
CREATE TABLE `seller_sales_channels`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `seller_user_id` bigint NOT NULL COMMENT 'ID penjual, merujuk ke tabel users.',
  `bot_id` bigint NULL DEFAULT NULL COMMENT 'Referensi ke tabel bots',
  `channel_id` bigint NOT NULL COMMENT 'ID channel penjualan.',
  `discussion_group_id` bigint NOT NULL COMMENT 'ID grup diskusi yang terhubung.',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Apakah channel penjualan aktif.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT 'Waktu pembuatan record.',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `unique_seller`(`seller_user_id` ASC) USING BTREE,
  INDEX `idx_channel_id`(`channel_id` ASC) USING BTREE,
  INDEX `fk_sales_channels_bot_id`(`bot_id` ASC) USING BTREE,
  CONSTRAINT `fk_sales_channels_bot_id` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_seller_sales_channels_user_id` FOREIGN KEY (`seller_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 9 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Menyimpan channel penjualan untuk setiap penjual.' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for telegram_error_logs
-- ----------------------------
DROP TABLE IF EXISTS `telegram_error_logs`;
CREATE TABLE `telegram_error_logs`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Metode API yang gagal.',
  `request_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT 'Data request yang dikirim.',
  `http_code` int NULL DEFAULT NULL COMMENT 'Kode status HTTP.',
  `error_code` int NULL DEFAULT NULL COMMENT 'Kode error dari Telegram.',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT 'Deskripsi error dari Telegram.',
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'failed' COMMENT 'Status penanganan error (failed, pending_retry).',
  `retry_after` int NULL DEFAULT NULL COMMENT 'Waktu tunggu sebelum mencoba lagi (dalam detik).',
  `chat_id` bigint NULL DEFAULT NULL COMMENT 'ID chat terkait error.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT 'Waktu pembuatan log.',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 121 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Mencatat error yang terjadi saat berinteraksi dengan API Telegram.' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for user_roles
-- ----------------------------
DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE `user_roles`  (
  `user_id` bigint NOT NULL COMMENT 'ID pengguna, merujuk ke tabel users.',
  `role_id` int NOT NULL COMMENT 'ID peran, merujuk ke tabel roles.',
  PRIMARY KEY (`user_id`, `role_id`) USING BTREE,
  INDEX `user_id`(`user_id` ASC) USING BTREE,
  INDEX `role_id`(`role_id` ASC) USING BTREE,
  CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = 'Tabel pivot untuk hubungan many-to-many antara pengguna dan peran.' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for users
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users`  (
  `id` bigint NOT NULL COMMENT 'User ID unik dari Telegram',
  `public_seller_id` char(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'ID publik unik untuk penjual (4 huruf).',
  `seller_package_sequence` int UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Urutan paket untuk ID publik penjual.',
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'Nama depan pengguna.',
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'Nama belakang pengguna.',
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'Username pengguna di Telegram.',
  `language_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'Kode bahasa pengguna.',
  `balance` decimal(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Saldo internal pengguna.',
  `status` enum('active','blocked') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active' COMMENT 'Status pengguna (aktif/diblokir).',
  `login_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'Token sekali pakai untuk login ke panel member.',
  `token_created_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu pembuatan token login.',
  `token_used` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Apakah token login sudah digunakan.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT 'Waktu pembuatan record pengguna.',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `public_seller_id`(`public_seller_id` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = 'Menyimpan data pengguna bot.' ROW_FORMAT = DYNAMIC;

SET FOREIGN_KEY_CHECKS = 1;
