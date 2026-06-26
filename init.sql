CREATE DATABASE IF NOT EXISTS event_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE event_system;

CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    event_date DATETIME NOT NULL,
    venue VARCHAR(255) NOT NULL,
    venue_id INT UNSIGNED DEFAULT NULL,
    organizer VARCHAR(255) NOT NULL,
    organizer_email VARCHAR(255) DEFAULT NULL,
    category VARCHAR(100) NOT NULL,
    ticket_options TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS attendees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    email VARCHAR(200) NOT NULL,
    phone VARCHAR(80) NOT NULL,
    ticket_type VARCHAR(100) NOT NULL,
    ticket_code VARCHAR(100) NULL,
    payment_method VARCHAR(80) NOT NULL,
    payment_status VARCHAR(80) NOT NULL,
    registration_status ENUM('Pending','Confirmed','Rejected') NOT NULL DEFAULT 'Pending',
    mpesa_transaction_id VARCHAR(255) DEFAULT NULL,
    payment_proof_path VARCHAR(500) DEFAULT NULL,
    amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO events (title, description, event_date, venue, organizer, category, ticket_options)
VALUES
('Conference Launch 2026', 'Join us for a full-day conference with expert speakers, networking, and workshops.', '2026-09-12 09:00:00', 'Grand Hall', 'City Events', 'Conference', '[{"name":"General","price":0,"quantity":120},{"name":"VIP","price":99.99,"quantity":50}]'),
('Startup Workshop', 'A hands-on workshop for founders and entrepreneurs to build, pitch, and grow.', '2026-08-22 10:00:00', 'CoLab Center', 'Innovation Hub', 'Workshop', '[{"name":"Standard","price":29.99,"quantity":80},{"name":"Early Bird","price":19.99,"quantity":40}]');

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) DEFAULT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    role ENUM('user','organizer','admin') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    pin_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attendee_id INT DEFAULT NULL,
    event_id INT DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    currency VARCHAR(10) NOT NULL DEFAULT 'KES',
    gateway VARCHAR(100) DEFAULT NULL,
    status ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',
    transaction_id VARCHAR(255) DEFAULT NULL,
    meta JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    email VARCHAR(255) NOT NULL,
    type VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT DEFAULT NULL,
    from_user_id INT DEFAULT NULL,
    to_user_id INT DEFAULT NULL,
    subject VARCHAR(255) DEFAULT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS venues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255) DEFAULT NULL,
    capacity INT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS venue_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id INT NOT NULL,
    event_id INT DEFAULT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE events
  ADD COLUMN created_by INT DEFAULT NULL;

ALTER TABLE events
  ADD FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE SET NULL;

ALTER TABLE attendees
  ADD COLUMN user_id INT DEFAULT NULL,
  ADD COLUMN rsvp_status ENUM('yes','no','maybe') DEFAULT 'maybe',
  ADD COLUMN checked_in TINYINT(1) DEFAULT 0;

INSERT INTO users (name, email, password_hash, phone, role)
VALUES ('Admin User', 'admin@example.com', '$2y$10$b2azQUrhhYxBkpqfyPbnNustQdYfpNn4cy6Tsnxj08XRMmXQC4scm', '0700000000', 'admin');

INSERT INTO venues (name, location, capacity, description)
VALUES ('Grand Hall', 'Main Campus', 250, 'Large indoor hall on the premises for conferences and workshops.'),
       ('CoLab Center', 'Innovation Building', 120, 'Flexible venue space ideal for small events and training sessions.');

INSERT INTO venue_bookings (venue_id, event_id, start_datetime, end_datetime, created_by)
VALUES (1, 1, '2026-09-12 08:00:00', '2026-09-12 18:00:00', 1);

#ALTER TABLE events
#  ADD COLUMN venue_id INT DEFAULT NULL AFTER venue,
#  ADD COLUMN organizer_email VARCHAR(255) DEFAULT NULL #AFTER organizer;

UPDATE events
SET venue_id = (SELECT id FROM venues WHERE name = 'Grand Hall')
WHERE title = 'Conference Launch 2026';

UPDATE events
SET venue_id = (SELECT id FROM venues WHERE name = 'CoLab Center')
WHERE title = 'Startup Workshop';

UPDATE events
SET organizer_email = (SELECT email FROM users WHERE id = events.created_by)
WHERE organizer_email IS NULL;

ALTER TABLE events MODIFY COLUMN venue_id INT UNSIGNED DEFAULT NULL;