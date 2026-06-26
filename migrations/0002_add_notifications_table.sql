-- Migration: add notifications table for email logging
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `email` VARCHAR(255) NOT NULL,
  `type` VARCHAR(100) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS=1;
