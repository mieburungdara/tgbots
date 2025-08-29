-- SQL migration to add the chat_id column back to the messages table.
-- This is crucial for storing the origin of any message, especially for
-- channel posts or group messages where the context is the chat, not just the user.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Add the chat_id column. It can be indexed if frequent lookups by chat_id are expected.
ALTER TABLE `messages`
  ADD COLUMN `chat_id` BIGINT NULL COMMENT 'ID dari chat, grup, atau channel asal pesan' AFTER `telegram_message_id`;

SET FOREIGN_KEY_CHECKS = 1;
