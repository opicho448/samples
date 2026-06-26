<?php
require_once 'helpers.php';
$error = '';
$selectedRole = $_SESSION['selected_role'] ?? null;
$next = $_GET['next'] ?? $_POST['next'] ?? null;
if (isset($_GET['role'])) {
    $selectedRole = $_GET['role'];
    $_SESSION['selected_role'] = $selectedRole;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $user = authenticateUser($email, $password);
    if ($user) {
        loginUser($user);
        unset($_SESSION['selected_role']);
        if ($next && stripos($next, 'event.php?id=') === 0) {
            redirect($next);
        }
        if ($user['role'] === 'admin') {
            redirect('admin.php');
        }
        if ($user['role'] === 'organizer') {
            redirect('organizer.php');
        }
        redirect('index.php');
    }
    $error = 'Invalid email or password.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premier Hotel Admin Login</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="page-login">
<?php include __DIR__ . '/header.php'; ?>
<main class="container">
    <section class="form-card">
        <h2>Secure access</h2>
        <?php if ($error): ?>
            <div class="notice" style="border-left-color:#c0392b;background:#0000a0;color:#8a1f1f;">
                <?= escape($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($selectedRole): ?>
            <div class="notice">
                <?= escape(ucfirst($selectedRole)) ?>
            </div>
        <?php endif; ?>
        <form method="post" action="login.php">
            <input type="hidden" name="next" value="<?= escape($next ?? '') ?>">
            <label>Email address</label>
            <input type="email" name="email" value="<?= escape($_POST['email'] ?? '') ?>">
            <label>Password</label>
            <input type="password" name="password">
            <?php if ($error): ?>
                <div style="margin-bottom:1rem;">
                    <a class="button" href="forgot_password.php" style="background:#f39c12;border-radius:8px;display:inline-block;text-align:center;">Forgot password?</a>
                </div>
            <?php endif; ?>
            <input type="submit" value="Log in" class="button">
        </form>
    </section>
</main>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>