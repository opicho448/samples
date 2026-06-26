<?php
require_once 'helpers.php';

$theme = $_POST['theme'] ?? 'light';
$redirect = $_POST['redirect'] ?? 'dashboard.php';
$allowed = ['light', 'dark', 'blue'];
if (!in_array($theme, $allowed, true)) {
    $theme = 'light';
}
$_SESSION['theme'] = $theme;
$user = currentUser();
if ($user) {
    try {
        ensureUserThemeSchema();
        $stmt = $pdo->prepare('UPDATE users SET theme = :theme WHERE id = :id');
        $stmt->execute(['theme' => $theme, 'id' => $user['id']]);
    } catch (PDOException $e) {
        // ignore schema errors
    }
}
if (!preg_match('/^\/?[A-Za-z0-9_\-\/\.\?=&]+$/', $redirect)) {
    $redirect = 'dashboard.php';
}
header('Location: ' . $redirect);
exit;
