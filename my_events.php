<?php
require_once 'helpers.php';
$user = requireLogin();
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendeeId = (int)($_POST['attendee_id'] ?? 0);
    $rsvpStatus = $_POST['rsvp_status'] ?? 'maybe';
    $stmt = $pdo->prepare('UPDATE attendees SET rsvp_status = :rsvp_status WHERE id = :id AND user_id = :user_id');
    $stmt->execute(['rsvp_status' => $rsvpStatus, 'id' => $attendeeId, 'user_id' => $user['id']]);
    if ($stmt->rowCount()) {
        $message = 'Your RSVP has been updated.';
    }
}
$stmt = $pdo->prepare('SELECT a.*, e.title AS event_title, e.event_date, e.venue, e.venue_id FROM attendees a LEFT JOIN events e ON a.event_id = e.id WHERE a.user_id = :user_id ORDER BY e.event_date ASC');
$stmt->execute(['user_id' => $user['id']]);
$registrations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premier Hotel My Registrations</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="page-my-events">
<?php include __DIR__ . '/header.php'; ?>
<main class="container">
    <?php if ($message): ?>
        <div class="notice"><?= escape($message) ?></div>
    <?php endif; ?>
    <?php if (empty($registrations)): ?>
        <section class="panel">
            <h2>No registrations yet</h2>
            <p>You have not registered for any events. Browse events on the homepage to register.</p>
        </section>
    <?php else: ?>
        <section class="table-wrapper panel">
            <h2>Your registrations</h2>
            <table>
                <thead>
                    <tr><th>Event</th><th>Date</th><th>Venue</th><th>Ticket</th><th>Ticket #</th><th>Status</th><th>RSVP</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($registrations as $reg): ?>
                        <tr>
                            <td><?= escape($reg['event_title']) ?></td>
                            <td><?= escape($reg['event_date']) ?></td>
                            <td><?= escape($reg['venue']) ?></td>
                            <td><?= escape($reg['ticket_type']) ?></td>
                            <td><?= !empty($reg['ticket_code']) ? escape($reg['ticket_code']) : 'Pending approval' ?></td>
                            <td><?= escape($reg['registration_status'] ?? $reg['payment_status']) ?></td>
                            <td><?= ucfirst(escape($reg['rsvp_status'])) ?></td>
                            <td>
                                <form method="post" action="my_events.php" style="display:inline-block;">
                                    <input type="hidden" name="attendee_id" value="<?= escape($reg['id']) ?>">
                                        <select name="rsvp_status">
                                            <option value="yes" <?= $reg['rsvp_status'] === 'yes' ? 'selected' : '' ?>>Yes</option>
                                            <option value="no" <?= $reg['rsvp_status'] === 'no' ? 'selected' : '' ?>>No</option>
                                            <option value="maybe" <?= $reg['rsvp_status'] === 'maybe' ? 'selected' : '' ?>>Maybe</option>
                                        </select>
                                    <button type="submit" class="button">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
