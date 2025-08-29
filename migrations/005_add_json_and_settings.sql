-- Migration to add raw_data column and bot_settings table
-- Dijalankan pada 2025-08-11

-- Menambahkan kolom `raw_data` ke tabel `messages` untuk menyimpan JSON mentah dari setiap pembaruan.
-- Ini memungkinkan untuk analisis atau tampilan data di masa depan.
ALTER TABLE `messages`
ADD COLUMN `raw_data` JSON NULL COMMENT 'Objek JSON mentah dari Telegram' AFTER `text`;

-- Membuat tabel `bot_settings` untuk menyimpan pengaturan spesifik per bot.
-- Ini memungkinkan admin untuk mengkonfigurasi perilaku bot, seperti jenis pesan apa yang akan disimpan.
CREATE TABLE `bot_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bot_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bot_setting_unique` (`bot_id`, `setting_key`),
  KEY `bot_id` (`bot_id`),
  CONSTRAINT `bot_settings_ibfk_1` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Menyimpan pengaturan kunci-nilai untuk setiap bot.';
