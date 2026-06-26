<?php
require_once 'helpers.php';
$user = requireLogin();
if (!in_array($user['role'], ['organizer', 'admin'], true)) {
    redirect('dashboard.php');
}
$message = '';
$venues = $pdo->query('SELECT * FROM venues ORDER BY name')->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $eventDate = trim($_POST['event_date'] ?? '');
    $venueId = (int)($_POST['venue_id'] ?? 0);
    $category = trim($_POST['category'] ?? 'General');
    $organizerEmail = trim($_POST['organizer_email'] ?? $user['email']);
    $ticketOptionsText = trim($_POST['ticket_options'] ?? '');
    $ticketOptions = [];
    foreach (explode("\n", $ticketOptionsText) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        if (count($parts) !== 3) {
            continue;
        }
        $ticketOptions[] = [
            'name' => $parts[0],
            'price' => (float)$parts[1],
            'quantity' => (int)$parts[2],
        ];
    }
    if ($title === '' || $eventDate === '' || $venueId <= 0 || empty($ticketOptions)) {
        $message = 'Title, date, venue, and valid ticket options are required.';
    } else {
        $startDate = date('Y-m-d H:i:s', strtotime($eventDate));
        $endDate = date('Y-m-d H:i:s', strtotime($eventDate . ' +4 hours'));
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM venue_bookings WHERE venue_id = :venue_id AND NOT (end_datetime <= :start_datetime OR start_datetime >= :end_datetime)');
        $stmt->execute(['venue_id' => $venueId, 'start_datetime' => $startDate, 'end_datetime' => $endDate]);
        $conflicts = $stmt->fetchColumn();
        if ($conflicts > 0) {
            $message = 'This venue is already booked during the selected time. Choose a different time or venue.';
        } else {
            $venueStmt = $pdo->prepare('SELECT name FROM venues WHERE id = :id');
            $venueStmt->execute(['id' => $venueId]);
            $venue = $venueStmt->fetchColumn();
            $stmt = $pdo->prepare('INSERT INTO events (title, description, event_date, venue, venue_id, organizer, organizer_email, category, ticket_options, created_by) VALUES (:title, :description, :event_date, :venue, :venue_id, :organizer, :organizer_email, :category, :ticket_options, :created_by)');
            $stmt->execute([
                'title' => $title,
                'description' => $description,
                'event_date' => $startDate,
                'venue' => $venue,
                'venue_id' => $venueId,
                'organizer' => $user['name'] ?: $user['email'],
                'organizer_email' => $organizerEmail,
                'category' => $category,
                'ticket_options' => json_encode($ticketOptions),
                'created_by' => $user['id'],
            ]);
            $eventId = $pdo->lastInsertId();
            $bookingStmt = $pdo->prepare('INSERT INTO venue_bookings (venue_id, event_id, start_datetime, end_datetime, created_by) VALUES (:venue_id, :event_id, :start_datetime, :end_datetime, :created_by)');
            $bookingStmt->execute([
                'venue_id' => $venueId,
                'event_id' => $eventId,
                'start_datetime' => $startDate,
                'end_datetime' => $endDate,
                'created_by' => $user['id'],
            ]);
            $message = 'Event created and venue booked successfully.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premier Hotel Create Event</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="page-create-event">
<?php include __DIR__ . '/header.php'; ?>
<main class="container">
    <section class="panel">
        <?php if ($message): ?><div class="notice"><?= escape($message) ?></div><?php endif; ?>
        <h2>Book a venue and create your event</h2>
        <form method="post" action="create_event.php">
            <label>Event title</label>
            <input type="text" name="title" value="<?= escape($_POST['title'] ?? '') ?>">
            <label>Description</label>
            <textarea name="description" rows="5"><?= escape($_POST['description'] ?? '') ?></textarea>
            <label>Date and time</label>
            <input type="datetime-local" name="event_date" value="<?= escape($_POST['event_date'] ?? '') ?>">
            <label>Venue</label>
            <select name="venue_id">
                <option value="">Select venue</option>
                <?php foreach ($venues as $venue): ?>
                    <option value="<?= escape($venue['id']) ?>" <?= isset($_POST['venue_id']) && $_POST['venue_id'] == $venue['id'] ? 'selected' : '' ?>><?= escape($venue['name']) ?> (<?= escape($venue['location']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <label>Organizer email</label>
            <input type="email" name="organizer_email" value="<?= escape($_POST['organizer_email'] ?? $user['email']) ?>">
            <label>Category</label>
            <input type="text" name="category" value="<?= escape($_POST['category'] ?? 'Conference') ?>">
            <label>Ticket options</label>
            <textarea name="ticket_options" rows="5" placeholder="Name | Price | Quantity"><?= escape($_POST['ticket_options'] ?? "General | 0 | 100") ?></textarea>
            <input type="submit" value="Create event" class="button">
        </form>
    </section>
</main>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
