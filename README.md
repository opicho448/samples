# Event Registration System (Samples)

Development README — default admin credentials

- **Admin email:** admin@example.com
- **Admin password:** admin123

Login page: /login.php

Important:
- These credentials are for local development only. Change the password immediately in production.
- To change the admin password via SQL (example):
  - UPDATE `users` SET `password` = '<password_hash>' WHERE `email` = 'admin@example.com';

Created: 2026-05-29
# Event Registration System

## Setup

1. Start XAMPP Control Panel as Administrator.
2. Ensure MySQL/MariaDB is running.
3. From the project directory, run:

```powershell
C:\xampp\php\php.exe migrate.php
```

4. Open the app in your browser, e.g. `http://localhost/samples`

## Notes

- `db.php` now uses environment variables for DB connection.
- Create a `.env` file from `.env.example` if you want to override defaults.
- If MySQL fails to start, grant write permission to `C:\xampp\mysql\data` and `ibdata1`, or start XAMPP as Administrator.

## Migration

- `migrate.php` executes SQL files in `migrations/`.
- It skips duplicate column/key errors and reports failures.

## New schema support

This version adds support for:
- `users`
- `payments`
- `venues`
- `venue_bookings`
- attendee RSVP and check-in fields
- event ownership via `events.created_by`

## New pages

- `register_user.php` — user sign-up
- `login.php` — email/password login
- `my_events.php` — attendee registration dashboard with RSVP updates
- `create_event.php` — event creation with venue booking
- `venues.php` — venue listing and booking view
