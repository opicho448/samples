<?php
require_once 'helpers.php';
if (currentUser()) {
    redirect('dashboard.php');
}
$selectedRole = $_SESSION['selected_role'] ?? null;
if (isset($_GET['role'])) {
    $selectedRole = $_GET['role'];
    $_SESSION['selected_role'] = $selectedRole;
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm'] ?? '');
    $role = $_POST['role'] ?? ($selectedRole === 'organizer' ? 'organizer' : 'user');

    if ($selectedRole === 'admin') {
        $errors[] = 'Admin registration is not available. Please log in with an existing admin account.';
    }
    if ($name === '' || $email === '' || $password === '' || $confirm === '') {
        $errors[] = 'All fields are required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if (empty($errors)) {
        if (!createUser($name, $email, $password, $phone, $role)) {
            $errors[] = 'Email already exists. Please use a different email.';
        } else {
            $user = authenticateUser($email, $password);
            if ($user) {
                loginUser($user);
                unset($_SESSION['selected_role']);
                if ($user['role'] === 'organizer') {
                    redirect('organizer.php');
                }
                redirect('index.php');
            }
            $errors[] = 'Registration succeeded, but login failed. Please log in manually.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premier Hotel Create Account</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="page-user-signup">
<?php include __DIR__ . '/header.php'; ?>
<main class="container">
    <section class="form-card">
        <h2>Sign up</h2>
        <?php if ($errors): ?>
            <div class="notice" style="border-left-color:#c0392b;background:#0f2b39;color:#8a1f1f;">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= escape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if ($selectedRole): ?>
            <div class="notice">Selected role: <?= escape(ucfirst($selectedRole)) ?></div>
        <?php endif; ?>
        <form method="post" action="register_user.php">
            <input type="hidden" name="role" value="<?= escape($selectedRole === 'organizer' ? 'organizer' : 'user') ?>">
            <label>Name</label>
            <input type="text" name="name" value="<?= escape($_POST['name'] ?? '') ?>">
            <label>Email</label>
            <input type="email" name="email" value="<?= escape($_POST['email'] ?? '') ?>">
            <label>Phone</label>
            <input type="tel" name="phone" value="<?= escape($_POST['phone'] ?? '') ?>">
            <label>Password</label>
            <input type="password" name="password">
            <label>Confirm password</label>
            <input type="password" name="confirm">
            <input type="submit" value="Create account" class="button">
        </form>
    </section>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
