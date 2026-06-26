-- Migration: add event_id to messages for event-specific chat threads
ALTER TABLE `messages`
  ADD COLUMN `event_id` INT DEFAULT NULL,
  ADD INDEX (`event_id`),
  ADD CONSTRAINT `fk_messages_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE SET NULL;