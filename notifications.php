<?php
require_once 'helpers.php';
$user = requireLogin();

// mark single notification as read
if (isset($_GET['mark_read'])) {
    $nid = (int)$_GET['mark_read'];
    $u = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid');
    $u->execute(['id' => $nid, 'uid' => $user['id']]);
    header('Location: notifications.php');
    exit;
}
// mark single notification as unread
if (isset($_GET['mark_unread'])) {
    $nid = (int)$_GET['mark_unread'];
    $u = $pdo->prepare('UPDATE notifications SET is_read = 0 WHERE id = :id AND user_id = :uid');
    $u->execute(['id' => $nid, 'uid' => $user['id']]);
    header('Location: notifications.php');
    exit;
}
// delete single notification
if (isset($_GET['delete_notification'])) {
    $nid = (int)$_GET['delete_notification'];
    $u = $pdo->prepare('DELETE FROM notifications WHERE id = :id AND user_id = :uid');
    $u->execute(['id' => $nid, 'uid' => $user['id']]);
    header('Location: notifications.php');
    exit;
}
// mark all as read
if (isset($_POST['mark_all'])) {
    $u = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :uid');
    $u->execute(['uid' => $user['id']]);
}

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC');
$stmt->execute(['user_id' => $user['id']]);
$notes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Notifications</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<main class="container">
    <section class="panel">
        <h2>Your notifications</h2>
        <?php if (empty($notes)): ?>
            <p>No notifications yet.</p>
        <?php else: ?>
            <div class="table-actions" style="margin-bottom:1rem; display:flex;gap:1rem; flex-wrap:wrap; align-items:center;">
                <form method="post" action="notifications.php">
                    <button type="submit" name="mark_all" class="button secondary">Mark all as read</button>
                </form>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>Time</th><th>Subject</th><th>Message</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($notes as $n): ?>
                        <tr class="<?= $n['is_read'] ? 'notification-read' : 'notification-unread' ?>">
                            <td><?= escape($n['created_at']) ?></td>
                            <td><?= escape($n['subject']) ?></td>
                            <td><?= $n['message'] ?></td>
                            <td><?= $n['is_read'] ? 'Read' : 'Unread' ?></td>
                            <td>
                                <?php if ($n['is_read']): ?>
                                    <a class="button small" href="notifications.php?mark_unread=<?= escape($n['id']) ?>">Mark unread</a>
                                <?php else: ?>
                                    <a class="button small" href="notifications.php?mark_read=<?= escape($n['id']) ?>">Mark read</a>
                                <?php endif; ?>
                                <a class="button small danger" href="notifications.php?delete_notification=<?= escape($n['id']) ?>" onclick="return confirm('Delete this notification?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
