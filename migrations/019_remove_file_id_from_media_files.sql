-- Migration to remove the redundant file_id column from the media_files table
-- Dijalankan pada 2025-08-13

ALTER TABLE `media_files` DROP COLUMN `file_id`;
