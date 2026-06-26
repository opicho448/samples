<?php
require_once __DIR__ . '/db.php';
// ensure Mailer class is loaded from this project's file
require_once __DIR__ . '/Mailer.php';
if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    session_start();
}

function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (getenv($name) === false) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

loadEnv(__DIR__ . '/.env');

function escape($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function currentUser() {
    global $pdo;
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    return $stmt->fetch();
}

function requireLogin($role = null) {
    $user = currentUser();
    if (!$user) {
        redirect('login.php');
    }
    if ($role && $user['role'] !== $role) {
        redirect('index.php');
    }
    return $user;
}

function loginUser($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
}

function logoutUser() {
    session_unset();
    session_destroy();
}

function tableExists($pdo, $tableName) {
    $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    if ($tableName === '') {
        return false;
    }
    $quoted = $pdo->quote($tableName);
    $stmt = $pdo->query("SHOW TABLES LIKE {$quoted}");
    return (bool)$stmt->fetchColumn();
}

function columnExists($pdo, $tableName, $columnName) {
    $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    $columnName = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
    if ($tableName === '' || $columnName === '') {
        return false;
    }
    $quoted = $pdo->quote($columnName);
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$tableName}` LIKE {$quoted}");
    return (bool)$stmt->fetchColumn();
}

function ensureMessagesSchema() {
    global $pdo;

    if (!tableExists($pdo, 'messages')) {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `messages` (' .
            '`id` INT AUTO_INCREMENT PRIMARY KEY, ' .
            '`event_id` INT DEFAULT NULL, ' .
            '`from_user_id` INT DEFAULT NULL, ' .
            '`to_user_id` INT DEFAULT NULL, ' .
            '`subject` VARCHAR(255) DEFAULT NULL, ' .
            '`message` TEXT NOT NULL, ' .
            '`photo_url` TEXT DEFAULT NULL, ' .
            '`is_read` TINYINT(1) DEFAULT 0, ' .
            '`is_archived` TINYINT(1) DEFAULT 0, ' .
            '`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, ' .
            'KEY `idx_messages_event` (`event_id`), ' .
            'CONSTRAINT `fk_messages_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE SET NULL, ' .
            'CONSTRAINT `fk_messages_from_user` FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL, ' .
            'CONSTRAINT `fk_messages_to_user` FOREIGN KEY (`to_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL' .
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;'
        );
        return;
    }

    $alterClauses = [];
    if (!columnExists($pdo, 'messages', 'event_id')) {
        $alterClauses[] = 'ADD COLUMN `event_id` INT DEFAULT NULL';
        $alterClauses[] = 'ADD INDEX `idx_messages_event` (`event_id`)';
        $alterClauses[] = 'ADD CONSTRAINT `fk_messages_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE SET NULL';
    }
    if (!columnExists($pdo, 'messages', 'is_read')) {
        $alterClauses[] = 'ADD COLUMN `is_read` TINYINT(1) DEFAULT 0';
    }
    if (!columnExists($pdo, 'messages', 'created_at')) {
        $alterClauses[] = 'ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP';
    }
    if (!columnExists($pdo, 'messages', 'subject')) {
        $alterClauses[] = 'ADD COLUMN `subject` VARCHAR(255) DEFAULT NULL';
    }
    if (!columnExists($pdo, 'messages', 'message')) {
        $alterClauses[] = 'ADD COLUMN `message` TEXT NOT NULL';
    }
    if (!columnExists($pdo, 'messages', 'photo_url')) {
        $alterClauses[] = 'ADD COLUMN `photo_url` TEXT DEFAULT NULL';
    }
    if (!columnExists($pdo, 'messages', 'is_archived')) {
        $alterClauses[] = 'ADD COLUMN `is_archived` TINYINT(1) DEFAULT 0';
    }
    if (!columnExists($pdo, 'messages', 'from_user_id')) {
        $alterClauses[] = 'ADD COLUMN `from_user_id` INT UNSIGNED DEFAULT NULL';
        $alterClauses[] = 'ADD CONSTRAINT `fk_messages_from_user` FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL';
    }
    if (!columnExists($pdo, 'messages', 'to_user_id')) {
        $alterClauses[] = 'ADD COLUMN `to_user_id` INT UNSIGNED DEFAULT NULL';
        $alterClauses[] = 'ADD CONSTRAINT `fk_messages_to_user` FOREIGN KEY (`to_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL';
    }

    if (!empty($alterClauses)) {
        $pdo->exec('ALTER TABLE `messages` ' . implode(', ', $alterClauses));
    }
}

function ensureUserThemeSchema() {
    global $pdo;
    if (!columnExists($pdo, 'users', 'theme')) {
        $pdo->exec('ALTER TABLE `users` ADD COLUMN `theme` VARCHAR(50) DEFAULT NULL');
    }
}

function logNotification($userId, $email, $type, $subject, $message, $status = 'pending') {
    global $pdo;
    if (!$email) {
        return false;
    }
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, email, type, subject, message, status) VALUES (:user_id, :email, :type, :subject, :message, :status)');
    return $stmt->execute([
        'user_id' => $userId,
        'email' => $email,
        'type' => $type,
        'subject' => $subject,
        'message' => $message,
        'status' => $status,
    ]);
}

function sendNotification($to, $subject, $message, $userId = null, $type = 'general') {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        logNotification($userId, $to, $type, $subject, $message, 'failed');
        return false;
    }

    // instantiate Mailer from project file
    $mailer = new \Mailer();
    $sent = $mailer->send($to, $subject, $message);
    $status = $sent ? 'sent' : 'failed';
    logNotification($userId, $to, $type, $subject, $message, $status);
    return $sent;
}

function siteUrl() {
    $url = getenv('APP_URL');
    if ($url) {
        return rtrim($url, '/');
    }
    if (!empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . $_SERVER['HTTP_HOST'];
    }
    return 'http://localhost';
}

function createUser($name, $email, $password, $phone = '', $role = 'user', $theme = null) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) {
        return false;
    }
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, phone, role, theme) VALUES (:name, :email, :password_hash, :phone, :role, :theme)');
    return $stmt->execute([
        'name' => $name,
        'email' => $email,
        'password_hash' => $passwordHash,
        'phone' => $phone,
        'role' => $role,
        'theme' => $theme,
    ]);
}

function authenticateUser($email, $password) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        return $user;
    }
    return false;
}

function ensurePasswordResetsTable() {
    global $pdo;
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `password_resets` (' .
        '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
        '`user_id` INT UNSIGNED NOT NULL, ' .
        '`token` VARCHAR(255) NOT NULL, ' .
        '`pin_hash` VARCHAR(255) NOT NULL, ' .
        '`expires_at` DATETIME NOT NULL, ' .
        '`used` TINYINT(1) NOT NULL DEFAULT 0, ' .
        '`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
        'PRIMARY KEY (`id`), ' .
        'UNIQUE KEY `token` (`token`), ' .
        'KEY `user_id` (`user_id`), ' .
        'CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE' .
        ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;'
    );
}

function createPasswordResetRequest($email) {
    global $pdo;
    ensurePasswordResetsTable();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();
    if (!$user) {
        return false;
    }
    $token = bin2hex(random_bytes(16));
    $pin = strval(random_int(100000, 999999));
    $pinHash = password_hash($pin, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $stmt = $pdo->prepare('INSERT INTO password_resets (user_id, token, pin_hash, expires_at) VALUES (:user_id, :token, :pin_hash, :expires_at)');
    $stmt->execute([
        'user_id' => $user['id'],
        'token' => $token,
        'pin_hash' => $pinHash,
        'expires_at' => $expiresAt,
    ]);
    return ['token' => $token, 'pin' => $pin, 'user' => $user];
}

function getPasswordResetRecord($token) {
    global $pdo;
    ensurePasswordResetsTable();
    $stmt = $pdo->prepare(
        'SELECT pr.*, u.email, u.id AS user_id, u.name FROM password_resets pr JOIN users u ON pr.user_id = u.id WHERE pr.token = :token LIMIT 1'
    );
    $stmt->execute(['token' => $token]);
    $record = $stmt->fetch();
    if (!$record) {
        return null;
    }
    if ($record['used'] || strtotime($record['expires_at']) < time()) {
        return null;
    }
    return $record;
}

function verifyPasswordResetPin($token, $pin) {
    $record = getPasswordResetRecord($token);
    if (!$record) {
        return null;
    }
    if (!password_verify($pin, $record['pin_hash'])) {
        return null;
    }
    return $record;
}

function resetPasswordWithToken($token, $pin, $password) {
    global $pdo;
    $record = verifyPasswordResetPin($token, $pin);
    if (!$record) {
        return false;
    }
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
    $stmt->execute([
        'hash' => $passwordHash,
        'id' => $record['user_id'],
    ]);
    $stmt = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = :id');
    $stmt->execute(['id' => $record['id']]);
    return true;
}

function sendPasswordResetEmail($email, $token, $pin) {
    $siteUrl = getenv('APP_URL') ?: ((isset($_SERVER['HTTP_HOST']) ? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] : 'http://localhost'));
    $resetUrl = rtrim($siteUrl, '/') . '/reset_password.php?token=' . urlencode($token);
    $subject = 'Password reset request';
    $message = "<p>We received a request to reset your password.</p>" .
        "<p>Your reset PIN is <strong>{$pin}</strong>.</p>" .
        "<p>Use it on the reset page below:</p>" .
        "<p><a href=\"{$resetUrl}\">Reset your password</a></p>" .
        "<p>This link expires in one hour.</p>";
    return sendNotification($email, $subject, $message, null, 'password_reset');
}

function isAdmin() {
    $user = currentUser();
    return $user && $user['role'] === 'admin';
}

function isOrganizer() {
    $user = currentUser();
    return $user && in_array($user['role'], ['organizer', 'admin'], true);
}

function ensureAttendeesApprovalSchema() {
    global $pdo;
    try {
        $pdo->exec('ALTER TABLE attendees MODIFY COLUMN ticket_code VARCHAR(100) NULL DEFAULT NULL');
    } catch (PDOException $e) {
        // Column may already be nullable.
    }
    if (!columnExists($pdo, 'attendees', 'registration_status')) {
        $pdo->exec(
            "ALTER TABLE attendees " .
            "ADD COLUMN registration_status ENUM('Pending','Confirmed','Rejected') NOT NULL DEFAULT 'Pending' AFTER payment_status"
        );
    }
    if (!columnExists($pdo, 'attendees', 'mpesa_transaction_id')) {
        $pdo->exec('ALTER TABLE attendees ADD COLUMN mpesa_transaction_id VARCHAR(255) DEFAULT NULL AFTER registration_status');
    }
    if (!columnExists($pdo, 'attendees', 'payment_proof_path')) {
        $pdo->exec('ALTER TABLE attendees ADD COLUMN payment_proof_path VARCHAR(500) DEFAULT NULL AFTER mpesa_transaction_id');
    }
}

function generateTicketCode() {
    global $pdo;
    do {
        $code = strtoupper(bin2hex(random_bytes(4)));
        $stmt = $pdo->prepare('SELECT id FROM attendees WHERE ticket_code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
    } while ($stmt->fetch());
    return $code;
}

function handlePaymentProofUpload($fieldName = 'payment_proof') {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    $fileInfo = getimagesize($_FILES[$fieldName]['tmp_name']);
    if (!$fileInfo || !in_array($fileInfo['mime'], ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
        return false;
    }
    $uploadDir = __DIR__ . '/uploads/payment_proofs';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $extension = pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION);
    $fileName = 'proof_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . ($extension ?: 'jpg');
    $destination = $uploadDir . '/' . $fileName;
    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $destination)) {
        return false;
    }
    return 'uploads/payment_proofs/' . $fileName;
}

function sendSms($phone, $message) {
    $phone = trim((string)$phone);
    if ($phone === '') {
        return false;
    }
    $logLine = date('Y-m-d H:i:s') . " SMS to {$phone}: {$message}\n";
    @file_put_contents(__DIR__ . '/app.log', $logLine, FILE_APPEND);
    logNotification(null, $phone, 'sms', 'Ticket notification', $message, 'sent');
    return true;
}

function decrementEventTicketQuantity($eventId, $ticketType) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT ticket_options FROM events WHERE id = :id');
    $stmt->execute(['id' => $eventId]);
    $event = $stmt->fetch();
    if (!$event) {
        return false;
    }
    $ticketOptions = json_decode($event['ticket_options'], true);
    if (!is_array($ticketOptions)) {
        return false;
    }
    foreach ($ticketOptions as &$ticket) {
        if ($ticket['name'] === $ticketType) {
            $ticket['quantity'] = max(0, (int)$ticket['quantity'] - 1);
            break;
        }
    }
    unset($ticket);
    $update = $pdo->prepare('UPDATE events SET ticket_options = :ticket_options WHERE id = :id');
    return $update->execute(['ticket_options' => json_encode($ticketOptions), 'id' => $eventId]);
}

function approveRegistration($attendeeId) {
    global $pdo;
    ensureAttendeesApprovalSchema();
    $stmt = $pdo->prepare(
        'SELECT a.*, e.title AS event_title FROM attendees a ' .
        'LEFT JOIN events e ON a.event_id = e.id WHERE a.id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $attendeeId]);
    $attendee = $stmt->fetch();
    if (!$attendee || ($attendee['registration_status'] ?? '') !== 'Pending') {
        return ['success' => false, 'message' => 'Registration not found or already processed.'];
    }
    if (!empty($attendee['ticket_code'])) {
        return ['success' => false, 'message' => 'This registration already has a ticket number.'];
    }

    $ticketCode = generateTicketCode();
    $paymentStatus = (float)$attendee['amount_paid'] > 0 ? 'Paid' : 'Free';

    $update = $pdo->prepare(
        'UPDATE attendees SET ticket_code = :ticket_code, registration_status = :registration_status, payment_status = :payment_status WHERE id = :id'
    );
    $update->execute([
        'ticket_code' => $ticketCode,
        'registration_status' => 'Confirmed',
        'payment_status' => $paymentStatus,
        'id' => $attendeeId,
    ]);

    $paymentUpdate = $pdo->prepare('UPDATE payments SET status = :status WHERE attendee_id = :attendee_id');
    $paymentUpdate->execute(['status' => 'completed', 'attendee_id' => $attendeeId]);

    decrementEventTicketQuantity((int)$attendee['event_id'], $attendee['ticket_type']);

    $notifyUserId = $attendee['user_id'] ?: null;
    if (!$notifyUserId) {
        $uStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $uStmt->execute(['email' => $attendee['email']]);
        $uRow = $uStmt->fetch();
        if ($uRow) {
            $notifyUserId = $uRow['id'];
        }
    }

    $eventTitle = escape($attendee['event_title']);
    $name = escape($attendee['name']);
    $emailSubject = 'Your ticket is confirmed';
    $emailBody = "<p>Hi {$name},</p>" .
        "<p>Your payment for <strong>{$eventTitle}</strong> has been verified.</p>" .
        "<p>Your ticket number is <strong>" . escape($ticketCode) . "</strong>.</p>" .
        "<p>Please keep this number for check-in.</p>";
    sendNotification($attendee['email'], $emailSubject, $emailBody, $notifyUserId, 'registration_approved');

    $smsMessage = "Your registration for {$attendee['event_title']} is confirmed. Ticket number: {$ticketCode}";
    sendSms($attendee['phone'], $smsMessage);

    return ['success' => true, 'message' => 'Registration approved. Ticket ' . $ticketCode . ' sent to attendee.', 'ticket_code' => $ticketCode];
}

function rejectRegistration($attendeeId) {
    global $pdo;
    ensureAttendeesApprovalSchema();
    $stmt = $pdo->prepare(
        'SELECT a.*, e.title AS event_title FROM attendees a ' .
        'LEFT JOIN events e ON a.event_id = e.id WHERE a.id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $attendeeId]);
    $attendee = $stmt->fetch();
    if (!$attendee || ($attendee['registration_status'] ?? '') !== 'Pending') {
        return ['success' => false, 'message' => 'Registration not found or already processed.'];
    }

    $update = $pdo->prepare(
        'UPDATE attendees SET registration_status = :registration_status, payment_status = :payment_status WHERE id = :id'
    );
    $update->execute([
        'registration_status' => 'Rejected',
        'payment_status' => 'Rejected',
        'id' => $attendeeId,
    ]);

    $paymentUpdate = $pdo->prepare('UPDATE payments SET status = :status WHERE attendee_id = :attendee_id');
    $paymentUpdate->execute(['status' => 'failed', 'attendee_id' => $attendeeId]);

    $notifyUserId = $attendee['user_id'] ?: null;
    $emailSubject = 'Registration not approved';
    $emailBody = '<p>Hi ' . escape($attendee['name']) . ',</p>' .
        '<p>Your registration for <strong>' . escape($attendee['event_title']) . '</strong> could not be approved.</p>' .
        '<p>If you believe this is an error, please contact the event organizer.</p>';
    sendNotification($attendee['email'], $emailSubject, $emailBody, $notifyUserId, 'registration_rejected');

    return ['success' => true, 'message' => 'Registration rejected and attendee notified.'];
}
?>