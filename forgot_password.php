<?php
require_once 'helpers.php';
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $resetRequest = createPasswordResetRequest($email);
        if ($resetRequest) {
            sendPasswordResetEmail($resetRequest['user']['email'], $resetRequest['token'], $resetRequest['pin']);
            try {
                $stmt = $pdo->prepare('INSERT INTO messages (from_user_id, to_user_id, subject, message) VALUES (:from_user_id, :to_user_id, :subject, :message)');
                $stmt->execute([
                    'from_user_id' => null,
                    'to_user_id' => $resetRequest['user']['id'],
                    'subject' => 'Password reset PIN',
                    'message' => "A password reset request was received. Your reset PIN is {$resetRequest['pin']}. Use it to complete the reset process.",
                ]);
            } catch (PDOException $e) {
                // ignore if messages table is missing
            }
            logNotification(
                $resetRequest['user']['id'],
                $resetRequest['user']['email'],
                'password_reset',
                'Password reset requested',
                'A password reset PIN was emailed to you and stored in your message inbox.',
                'sent'
            );
            $success = 'If that email exists, a reset link and PIN have been sent. Check your email and message inbox after login.';
        } else {
            $success = 'If that email exists, a reset link and PIN have been sent. Check your email and message inbox after login.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="page-login">
<?php include __DIR__ . '/header.php'; ?>
<main class="container">
    <section class="form-card">
        <h2>Forgot password</h2>
        <p>Enter the email address for your account. If it exists, we will send reset instructions.</p>
        <?php if ($error): ?>
            <div class="notice" style="border-left-color:#c0392b;background:#fdecea;color:#8a1f1f;">
                <?= escape($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="notice">
                <?= escape($success) ?>
            </div>
        <?php endif; ?>
        <form method="post" action="forgot_password.php">
            <label>Email address</label>
            <input type="email" name="email" value="<?= escape($_POST['email'] ?? '') ?>">
            <input type="submit" value="Send reset code" class="button">
        </form>
        <p style="margin-top:1rem;"><a href="login.php">Back to login</a></p>
    </section>
</main>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
