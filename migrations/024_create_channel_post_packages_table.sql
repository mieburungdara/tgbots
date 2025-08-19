-- Migration to create a table linking channel posts to media packages

CREATE TABLE `channel_post_packages` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `channel_id` BIGINT NOT NULL,
    `message_id` BIGINT NOT NULL,
    `package_id` BIGINT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `channel_message_idx` (`channel_id`, `message_id`),
    KEY `package_id_fk_idx` (`package_id`),
    CONSTRAINT `channel_post_packages_package_id_fk` FOREIGN KEY (`package_id`) REFERENCES `media_packages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
