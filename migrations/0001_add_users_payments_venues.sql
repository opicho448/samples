-- Migration: add users, payments, venues, and related columns
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) DEFAULT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `role` ENUM('user','organizer','admin') NOT NULL DEFAULT 'user',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attendee_id` INT UNSIGNED DEFAULT NULL,
  `event_id` INT UNSIGNED DEFAULT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'KES',
  `gateway` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',
  `transaction_id` VARCHAR(255) DEFAULT NULL,
  `meta` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`attendee_id`),
  INDEX (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `venues` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `location` VARCHAR(255) DEFAULT NULL,
  `capacity` INT UNSIGNED DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `venue_bookings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `venue_id` INT UNSIGNED NOT NULL,
  `event_id` INT UNSIGNED DEFAULT NULL,
  `start_datetime` DATETIME NOT NULL,
  `end_datetime` DATETIME NOT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`venue_id`),
  INDEX (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Alter existing tables to link users and add rsvp/checkin fields
ALTER TABLE `events`
  ADD COLUMN `organizer_email` VARCHAR(255) DEFAULT NULL,
  ADD COLUMN `created_by` INT UNSIGNED DEFAULT NULL,
  ADD COLUMN `venue_id` INT UNSIGNED DEFAULT NULL;

ALTER TABLE `attendees`
  ADD COLUMN `user_id` INT UNSIGNED DEFAULT NULL,
  ADD COLUMN `rsvp_status` ENUM('yes','no','maybe') DEFAULT 'maybe',
  ADD COLUMN `checked_in` TINYINT(1) DEFAULT 0;

-- Add foreign keys where possible; adjust names if your schema differs.
ALTER TABLE `events`
  ADD CONSTRAINT `fk_events_created_by_users` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_events_venue` FOREIGN KEY (`venue_id`) REFERENCES `venues`(`id`) ON DELETE SET NULL;

ALTER TABLE `attendees`
  ADD CONSTRAINT `fk_attendees_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;

ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_attendee` FOREIGN KEY (`attendee_id`) REFERENCES `attendees`(`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_payments_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE SET NULL;

ALTER TABLE `venue_bookings`
  ADD CONSTRAINT `fk_venue_bookings_venue` FOREIGN KEY (`venue_id`) REFERENCES `venues`(`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_venue_bookings_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_venue_bookings_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS=1;

-- End migration
