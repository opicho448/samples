<?php
require_once 'helpers.php';
$error = '';
$success = '';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');
    $pin = trim($_POST['pin'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');
    if ($token === '' || $pin === '' || $password === '' || $confirm === '') {
        $error = 'Please complete all fields.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        if (resetPasswordWithToken($token, $pin, $password)) {
            $success = 'Your password was reset successfully. You may now <a href="login.php">log in</a>.';
        } else {
            $error = 'The reset token or PIN is invalid or expired. Please request a new reset link.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="page-login">
<?php include __DIR__ . '/header.php'; ?>
<main class="container">
    <section class="form-card">
        <h2>Reset password</h2>
        <?php if ($error): ?>
            <div class="notice" style="border-left-color:#c0392b;background:#fdecea;color:#8a1f1f;">
                <?= escape($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="notice">
                <?= $success ?>
            </div>
        <?php else: ?>
            <form method="post" action="reset_password.php">
                <input type="hidden" name="token" value="<?= escape($token) ?>">
                <label>Reset link token</label>
                <input type="text" value="<?= escape($token) ?>" disabled>
                <label>PIN from email or messages</label>
                <input type="text" name="pin" value="<?= escape($_POST['pin'] ?? '') ?>">
                <label>New password</label>
                <input type="password" name="password">
                <label>Confirm password</label>
                <input type="password" name="confirm_password">
                <input type="submit" value="Update password" class="button">
            </form>
            <p style="margin-top:1rem;">Need another reset email? <a href="forgot_password.php">Request a new code</a>.</p>
        <?php endif; ?>
    </section>
</main>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
