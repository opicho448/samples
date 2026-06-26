-- Migration: add messages table for user-hotel messaging
CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `from_user_id` INT UNSIGNED DEFAULT NULL,
  `to_user_id` INT UNSIGNED DEFAULT NULL,
  `subject` VARCHAR(255) DEFAULT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`to_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
