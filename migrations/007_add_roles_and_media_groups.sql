-- Migration to add user roles and media group support
-- Dijalankan pada 2025-08-11

-- Menambahkan kolom 'role' ke tabel 'users' untuk sistem peran (admin/user).
ALTER TABLE `users`
ADD COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'user' COMMENT 'Peran pengguna (misalnya, user, admin)' AFTER `language_code`;

-- Menambahkan kolom 'media_group_id' ke tabel 'media_files' untuk mengelompokkan media.
ALTER TABLE `media_files`
ADD COLUMN `media_group_id` VARCHAR(255) NULL DEFAULT NULL COMMENT 'ID untuk mengelompokkan media yang dikirim bersamaan' AFTER `message_id`;
