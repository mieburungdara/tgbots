-- Migration to create a table for database-driven application logging

CREATE TABLE `app_logs` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `level` VARCHAR(20) NOT NULL,
    `message` TEXT NOT NULL,
    `context` JSON NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
