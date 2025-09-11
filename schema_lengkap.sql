SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 
-- Struktur untuk tabel `roles`
-- 
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 
-- Struktur untuk tabel `bots`
-- 
DROP TABLE IF EXISTS `bots`;
CREATE TABLE `bots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `telegram_id` bigint(20) NOT NULL COMMENT 'ID bot dari Telegram.',
  `token` varchar(255) NOT NULL COMMENT 'Token API dari BotFather.',
  `username` varchar(255) DEFAULT NULL,
  `assigned_feature` varchar(50) DEFAULT NULL COMMENT 'Fitur utama yang ditugaskan ke bot ini (misal: ''sell'', ''rate'').',
  PRIMARY KEY (`id`),
  UNIQUE KEY `telegram_id` (`telegram_id`),
  UNIQUE KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 
-- Struktur untuk tabel `users`
-- 
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `telegram_id` bigint(20) NOT NULL COMMENT 'ID unik dari Telegram.',
  `public_seller_id` char(5) DEFAULT NULL COMMENT 'ID singkat yang ditampilkan publik untuk penjual.',
  `first_name` varchar(255) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL COMMENT 'Menentukan hak akses pengguna.',
  `balance` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Saldo finansial pengguna.',
  `status` enum('active','blocked') NOT NULL DEFAULT 'active',
  `login_token` varchar(255) DEFAULT NULL COMMENT 'Token sekali pakai untuk login ke panel web.',
  `token_created_at` timestamp NULL DEFAULT NULL,
  `token_used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `subscription_price` decimal(15,2) DEFAULT NULL COMMENT 'Harga langganan bulanan yang ditetapkan oleh penjual.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `telegram_id` (`telegram_id`),
  UNIQUE KEY `public_seller_id` (`public_seller_id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 
-- Struktur untuk tabel `rel_user_bot`
-- 
DROP TABLE IF EXISTS `rel_user_bot`;
CREATE TABLE `rel_user_bot` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `bot_id` int(11) NOT NULL,
  `state` varchar(255) DEFAULT NULL COMMENT 'State percakapan saat ini (misal: ''awaiting_price'').',
  `state_context` json DEFAULT NULL COMMENT 'Data tambahan yang terkait dengan state.',
  `last_interaction_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_bot_unique` (`user_id`,`bot_id`),
  KEY `user_id` (`user_id`),
  KEY `bot_id` (`bot_id`),
  CONSTRAINT `rel_user_bot_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rel_user_bot_ibfk_2` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 
-- Struktur untuk tabel `media_files`
-- 
DROP TABLE IF EXISTS `media_files`;
CREATE TABLE `media_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `package_id` int(11) DEFAULT NULL COMMENT 'Paket tempat media ini berada.',
  `telegram_user_id` int(11) NOT NULL COMMENT 'Pengguna yang mengunggah media.',
  `file_id` varchar(255) NOT NULL COMMENT 'ID file dari Telegram, bisa kedaluwarsa.',
  `file_unique_id` varchar(255) NOT NULL COMMENT 'ID file permanen dari Telegram.',
  `media_group_id` varchar(255) DEFAULT NULL COMMENT 'Mengelompokkan beberapa media yang diunggah bersamaan.',
  `type` enum('photo','video','document') DEFAULT NULL,
  `storage_channel_id` bigint(20) DEFAULT NULL COMMENT 'ID channel backup tempat file disimpan.',
  `storage_message_id` bigint(20) DEFAULT NULL COMMENT 'ID pesan di channel backup.',
  PRIMARY KEY (`id`),
  KEY `package_id` (`package_id`),
  KEY `telegram_user_id` (`telegram_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 
-- Struktur untuk tabel `media_packages`
-- 
DROP TABLE IF EXISTS `media_packages`;
CREATE TABLE `media_packages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(20) NOT NULL COMMENT 'ID yang ditampilkan di URL dan callback.',
  `telegram_user_id` int(11) NOT NULL COMMENT 'ID pemilik/penjual paket.',
  `bot_id` int(11) NOT NULL,
  `thumbnail_media_id` int(11) DEFAULT NULL COMMENT 'Media yang digunakan sebagai preview.',
  `description` text,
  `price` decimal(15,2) NOT NULL,
  `status` enum('available','sold','retracted','pending','deleted') DEFAULT 'available',
  `is_protected` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Jika 1, konten dilindungi dari copy/forward.',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `telegram_user_id` (`telegram_user_id`),
  KEY `bot_id` (`bot_id`),
  KEY `thumbnail_media_id` (`thumbnail_media_id`),
  CONSTRAINT `media_packages_ibfk_1` FOREIGN KEY (`telegram_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `media_packages_ibfk_2` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `media_packages_ibfk_3` FOREIGN KEY (`thumbnail_media_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 
-- Struktur untuk tabel `sales`
-- 
DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `package_id` int(11) NOT NULL,
  `seller_user_id` int(11) NOT NULL COMMENT 'Penjual yang menerima pembayaran.',
  `buyer_user_id` int(11) NOT NULL COMMENT 'Pembeli yang melakukan pembayaran.',
  `price` decimal(15,2) NOT NULL COMMENT 'Harga pada saat transaksi.',
  `commission` decimal(15,2) DEFAULT NULL COMMENT 'Jumlah komisi yang diambil dari penjualan.',
  `sale_type` enum('one_time','subscription') NOT NULL DEFAULT 'one_time',
  `sale_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `package_id` (`package_id`),
  KEY `seller_user_id` (`seller_user_id`),
  KEY `buyer_user_id` (`buyer_user_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`package_id`) REFERENCES `media_packages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`seller_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_ibfk_3` FOREIGN KEY (`buyer_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 
-- Struktur untuk tabel `offers`
-- 
DROP TABLE IF EXISTS `offers`;
CREATE TABLE `offers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `package_id` int(11) NOT NULL,
  `buyer_user_id` int(11) NOT NULL,
  `seller_user_id` int(11) NOT NULL,
  `offer_price` decimal(15,2) NOT NULL,
  `status` enum('pending','accepted','rejected','expired','completed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `package_id` (`package_id`),
  KEY `buyer_user_id` (`buyer_user_id`),
  KEY `seller_user_id` (`seller_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 
-- Struktur untuk tabel `feature_channels`
-- 
DROP TABLE IF EXISTS `feature_channels`;
CREATE TABLE `feature_channels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `channel_id` bigint(20) NOT NULL,
  `feature_type` varchar(50) NOT NULL COMMENT 'Tipe fitur, misal: ''moderation'', ''sell''.',
  `owner_user_id` int(11) DEFAULT NULL COMMENT 'Jika null, ini adalah channel sistem.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `channel_id` (`channel_id`),
  KEY `owner_user_id` (`owner_user_id`),
  CONSTRAINT `feature_channels_ibfk_1` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 
-- Struktur untuk tabel `private_channels`
-- 
DROP TABLE IF EXISTS `private_channels`;
CREATE TABLE `private_channels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `channel_id` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `channel_id` (`channel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 
-- Struktur untuk tabel `private_channel_bots`
-- 
DROP TABLE IF EXISTS `private_channel_bots`;
CREATE TABLE `private_channel_bots` (
  `private_channel_id` int(11) NOT NULL,
  `bot_id` int(11) NOT NULL,
  PRIMARY KEY (`private_channel_id`,`bot_id`),
  KEY `bot_id` (`bot_id`),
  CONSTRAINT `private_channel_bots_ibfk_1` FOREIGN KEY (`private_channel_id`) REFERENCES `private_channels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `private_channel_bots_ibfk_2` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 
-- Struktur untuk tabel `raw_updates`
-- 
DROP TABLE IF EXISTS `raw_updates`;
CREATE TABLE `raw_updates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payload` json NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 
-- Struktur untuk tabel `telegram_error_logs`
-- 
DROP TABLE IF EXISTS `telegram_error_logs`;
CREATE TABLE `telegram_error_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `method` varchar(255) DEFAULT NULL COMMENT 'Metode API yang dipanggil.',
  `request_data` text,
  `http_code` int(11) DEFAULT NULL,
  `error_code` int(11) DEFAULT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 
-- Struktur untuk tabel `package_views`
-- 
DROP TABLE IF EXISTS `package_views`;
CREATE TABLE `package_views` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `package_id` int(11) NOT NULL,
  `viewer_user_id` int(11) NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_view` (`package_id`,`viewer_user_id`),
  KEY `package_id_idx` (`package_id`),
  CONSTRAINT `package_views_ibfk_1` FOREIGN KEY (`package_id`) REFERENCES `media_packages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `package_views_ibfk_2` FOREIGN KEY (`viewer_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- 
-- Struktur untuk tabel `subscriptions`
-- 
DROP TABLE IF EXISTS `subscriptions`;
CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subscriber_user_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `status` enum('active','expired','cancelled') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `subscriber_user_id` (`subscriber_user_id`),
  KEY `seller_id` (`seller_id`),
  CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`subscriber_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subscriptions_ibfk_2` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
