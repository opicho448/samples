-- Migration: add is_read column to notifications
ALTER TABLE notifications
  ADD COLUMN is_read TINYINT(1) DEFAULT 0;
