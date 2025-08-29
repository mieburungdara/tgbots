-- Migration to create the media_files table
-- Dijalankan pada 2025-08-11
-- Tabel ini menyimpan informasi detail tentang setiap file media yang dikirim ke bot.

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Menyimpan detail file media yang diterima dari pengguna.';
