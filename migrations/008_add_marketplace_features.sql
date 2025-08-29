-- Migration: Menambahkan fitur marketplace media
-- Dijalankan pada: 2025-08-11
-- Deskripsi:
-- 1. Menambahkan kolom `balance` ke tabel `users` untuk sistem saldo internal.
-- 2. Menambahkan kolom `state` dan `state_context` ke `rel_user_bot` untuk mengelola percakapan multi-langkah.
-- 3. Membuat tabel `media_packages` untuk mendefinisikan item yang dijual.
-- 4. Menambahkan kolom `package_id` ke `media_files` untuk menghubungkan file ke paketnya.

-- 1. Tambah kolom saldo ke tabel pengguna
ALTER TABLE `users` ADD `balance` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Saldo internal pengguna' AFTER `role`;

-- 2. Tambah kolom state untuk conversation handling
ALTER TABLE `rel_user_bot`
ADD `state` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Status percakapan pengguna saat ini (mis: awaiting_price)' AFTER `last_interaction_at`,
ADD `state_context` JSON NULL COMMENT 'Data sementara terkait state (mis: media_group_id yang sedang dikirim)' AFTER `state`;

-- 3. Buat tabel untuk paket media yang dijual
CREATE TABLE `media_packages` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `seller_user_id` INT(11) NOT NULL COMMENT 'Referensi ke tabel users (penjual)',
  `bot_id` INT(11) NOT NULL COMMENT 'Referensi ke tabel bots',
  `description` TEXT NULL COMMENT 'Deskripsi paket media',
  `price` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `status` ENUM('pending', 'available', 'sold', 'delisted') NOT NULL DEFAULT 'pending' COMMENT 'Status paket penjualan',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY `seller_user_id` (`seller_user_id`),
  KEY `bot_id` (`bot_id`),
  CONSTRAINT `fk_package_seller` FOREIGN KEY (`seller_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_package_bot` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Menyimpan informasi paket media yang dijual.';

-- 4. Modifikasi tabel media_files untuk menautkan ke paket
ALTER TABLE `media_files`
ADD `package_id` BIGINT NULL DEFAULT NULL COMMENT 'Referensi ke media_packages' AFTER `message_id`,
ADD KEY `package_id` (`package_id`);

-- Menambahkan foreign key constraint secara terpisah untuk memastikan tabel sudah ada
ALTER TABLE `media_files`
ADD CONSTRAINT `fk_media_package` FOREIGN KEY (`package_id`) REFERENCES `media_packages` (`id`) ON DELETE SET NULL;
