-- Migration: Add is_archived column to messages table
ALTER TABLE `messages`
ADD COLUMN `is_archived` TINYINT(1) DEFAULT 0;