-- Migration to remove the file_unique_id column and its unique constraint
-- Dijalankan pada 2025-08-11
-- This change is based on user request to simplify the media table and resolve duplicate key errors.

ALTER TABLE `media_files`
DROP KEY `file_unique_id`,
DROP COLUMN `file_unique_id`;
