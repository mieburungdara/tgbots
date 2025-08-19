-- Migration to create a table for storing all raw incoming Telegram updates for debugging

CREATE TABLE `raw_updates` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `payload` JSON NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
