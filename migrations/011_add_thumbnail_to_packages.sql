-- Migration to add a thumbnail reference to media_packages
-- Dijalankan pada 2025-08-11
-- This allows a package to have a designated cover image/video.

ALTER TABLE `media_packages`
ADD COLUMN `thumbnail_media_id` BIGINT NULL DEFAULT NULL AFTER `description`,
ADD CONSTRAINT `fk_package_thumbnail` FOREIGN KEY (`thumbnail_media_id`) REFERENCES `media_files`(`id`) ON DELETE SET NULL;
