-- SQL migration to add bot_id to the messages table.
-- This is necessary to correctly associate messages with a specific user-bot conversation.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Add bot_id column, allowing NULL for existing messages.
-- Add a foreign key constraint that sets bot_id to NULL if the referenced bot is deleted.
ALTER TABLE `messages`
  ADD COLUMN `bot_id` INT(11) NULL DEFAULT NULL AFTER `user_id`,
  ADD KEY `bot_id` (`bot_id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;
