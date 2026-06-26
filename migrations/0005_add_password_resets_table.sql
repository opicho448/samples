-- Migration: add password reset tokens with PIN support
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(255) NOT NULL UNIQUE,
  `pin_hash` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
