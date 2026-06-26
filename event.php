<?php
require_once 'helpers.php';
$user = currentUser();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$stmt = $pdo->prepare('SELECT * FROM events WHERE id = :id');
$stmt->execute(['id' => $id]);
$event = $stmt->fetch();
if (!$event) {
    header('Location: index.php');
    exit;
}
if (!$user) {
    header('Location: login.php?next=' . urlencode('event.php?id=' . $id));
    exit;
}
$ticketOptions = json_decode($event['ticket_options'], true);
$cat = strtolower(preg_replace('/[^a-z0-9]+/','-', $event['category'] ?? 'default'));
$catClass = 'category-' . ($cat ?: 'default');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premier Hotel Event - <?= escape($event['title']) ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="page-event">
<?php include __DIR__ . '/header.php'; ?>
<main class="container">
    <h1 style="margin-top:1rem;"><?= escape($event['title']) ?></h1>
    <section class="card event-card <?= $catClass ?>">
        <h2>Event details</h2>
        <div class="event-meta">
            <span><?= date('M j, Y g:i A', strtotime($event['event_date'])) ?></span>
            <span><?= escape($event['venue']) ?></span>
            <span><?= escape($event['category']) ?></span>
        </div>
        <p><?= nl2br(escape($event['description'])) ?></p>
        <p><strong>Organizer:</strong> <?= escape($event['organizer']) ?></p>
        <h3>Ticket types</h3>
        <ul>
            <?php foreach ($ticketOptions as $ticket): ?>
                <li><strong><?= escape($ticket['name']) ?></strong> - <?= $ticket['price'] > 0 ? '$' . number_format($ticket['price'], 2) : 'Free' ?> (<?= escape($ticket['quantity']) ?> available)</li>
            <?php endforeach; ?>
        </ul>
        <div class="notice">
            <p><strong>Note:</strong> Click below to register for this event and secure your ticket.</p>
        </div>
        <a class="button" href="register.php?event_id=<?= $event['id'] ?>">Register now</a>
    </section>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>