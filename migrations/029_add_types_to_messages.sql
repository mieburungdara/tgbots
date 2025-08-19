-- SQL migration to add chat_type and update_type to the messages table.
-- This provides better context for each message, allowing for more specific queries and logic.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `messages`
  ADD COLUMN `chat_type` VARCHAR(255) NULL COMMENT 'Tipe chat: private, group, supergroup, channel' AFTER `chat_id`,
  ADD COLUMN `update_type` VARCHAR(255) NULL COMMENT 'Tipe update dari Telegram: message, edited_message, channel_post, dll.' AFTER `chat_type`;

SET FOREIGN_KEY_CHECKS = 1;
