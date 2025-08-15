-- Menambahkan kolom status ke tabel pengguna untuk menangani pengguna yang memblokir bot
ALTER TABLE `users`
ADD COLUMN `status` ENUM('active', 'blocked') NOT NULL DEFAULT 'active' AFTER `balance`;
