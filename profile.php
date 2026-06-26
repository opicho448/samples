<?php
require_once __DIR__ . '/helpers.php';
$user = requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $redirectUrl = trim($_POST['redirect'] ?? '');

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect($redirectUrl ?: 'dashboard.php');
    }

    $allowedRedirect = $redirectUrl && parse_url($redirectUrl, PHP_URL_SCHEME) === null && parse_url($redirectUrl, PHP_URL_HOST) === null;
    $target = $allowedRedirect ? $redirectUrl : 'dashboard.php';

    $stmt = $pdo->prepare('UPDATE users SET name = :name, email = :email, phone = :phone WHERE id = :id');
    $stmt->execute([
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'id' => $user['id'],
    ]);

    redirect($target);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>My Profile</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<main class="container">
    <section class="panel">
        <h2>Profile</h2>
        <p><strong>Name:</strong> <?= escape($user['name'] ?? '') ?></p>
        <p><strong>Email:</strong> <?= escape($user['email'] ?? '') ?></p>
        <p><strong>Phone:</strong> <?= escape($user['phone'] ?? '') ?></p>
        <p><strong>Role:</strong> <?= escape($user['role'] ?? '') ?></p>
        <div style="margin-top:1rem;">
            <a class="button" href="logout.php">Logout</a>
            <a class="button" href="dashboard.php">Back</a>
        </div>
    </section>
</main>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
