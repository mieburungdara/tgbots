-- Tabel untuk mencatat kesalahan dari API Telegram
CREATE TABLE `telegram_error_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `method` VARCHAR(255) NULL,
  `request_data` LONGTEXT NULL,
  `http_code` INT(11) NULL,
  `error_code` INT(11) NULL,
  `description` TEXT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'failed',
  `retry_after` INT(11) NULL,
  `chat_id` BIGINT(20) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
