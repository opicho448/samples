<?php
require_once 'helpers.php';
$user = requireLogin();
if (!in_array($user['role'], ['organizer', 'admin'], true)) {
    redirect('dashboard.php');
}
$message = '';

if (isset($_GET['delete'])) {
    $eventId = (int)$_GET['delete'];
    $stmt = $pdo->prepare('SELECT created_by FROM events WHERE id = :id');
    $stmt->execute(['id' => $eventId]);
    $event = $stmt->fetch();
    if ($event && ($event['created_by'] == $user['id'] || $user['role'] === 'admin')) {
        $stmt = $pdo->prepare('DELETE FROM attendees WHERE event_id = :event_id');
        $stmt->execute(['event_id' => $eventId]);
        $stmt = $pdo->prepare('DELETE FROM venue_bookings WHERE event_id = :event_id');
        $stmt->execute(['event_id' => $eventId]);
        $stmt = $pdo->prepare('DELETE FROM events WHERE id = :id');
        $stmt->execute(['id' => $eventId]);
        $message = 'Event deleted successfully.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eventId = (int)($_POST['event_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $eventDate = trim($_POST['event_date'] ?? '');
    $venue = trim($_POST['venue'] ?? '');
    $organizerEmail = trim($_POST['organizer_email'] ?? $user['email']);
    $category = trim($_POST['category'] ?? 'General');
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
    if ($title === '' || $eventDate === '' || $venue === '' || empty($ticketOptions)) {
        $message = 'Title, date, venue, and at least one ticket option are required.';
    } else {
        if ($eventId > 0) {
            $stmt = $pdo->prepare('SELECT created_by FROM events WHERE id = :id');
            $stmt->execute(['id' => $eventId]);
            $event = $stmt->fetch();
            if ($event && ($event['created_by'] == $user['id'] || $user['role'] === 'admin')) {
                $update = $pdo->prepare('UPDATE events SET title = :title, description = :description, event_date = :event_date, venue = :venue, organizer = :organizer, organizer_email = :organizer_email, category = :category, ticket_options = :ticket_options WHERE id = :id');
                $update->execute([
                    'title' => $title,
                    'description' => $description,
                    'event_date' => $eventDate,
                    'venue' => $venue,
                    'organizer' => $user['name'] ?: $user['email'],
                    'organizer_email' => $organizerEmail,
                    'category' => $category,
                    'ticket_options' => json_encode($ticketOptions),
                    'id' => $eventId,
                ]);
                $message = 'Event updated successfully.';
            }
        } else {
            $insert = $pdo->prepare('INSERT INTO events (title, description, event_date, venue, organizer, organizer_email, category, ticket_options, created_by) VALUES (:title, :description, :event_date, :venue, :organizer, :organizer_email, :category, :ticket_options, :created_by)');
            $insert->execute([
                'title' => $title,
                'description' => $description,
                'event_date' => $eventDate,
                'venue' => $venue,
                'organizer' => $user['name'] ?: $user['email'],
                'organizer_email' => $organizerEmail,
                'category' => $category,
                'ticket_options' => json_encode($ticketOptions),
                'created_by' => $user['id'],
            ]);
            $message = 'Event created successfully.';
        }
    }
}

$events = $pdo->prepare('SELECT * FROM events WHERE created_by = :created_by ORDER BY event_date ASC');
$events->execute(['created_by' => $user['id']]);
$events = $events->fetchAll();
$noRsvp = $pdo->prepare('SELECT a.*, e.title AS event_title, e.event_date FROM attendees a JOIN events e ON a.event_id = e.id WHERE a.rsvp_status = "no" AND e.created_by = :created_by ORDER BY a.registered_at DESC');
$noRsvp->execute(['created_by' => $user['id']]);
$noRsvp = $noRsvp->fetchAll();
$eventAttendees = $pdo->prepare('SELECT a.*, e.title AS event_title, e.event_date FROM attendees a JOIN events e ON a.event_id = e.id WHERE e.created_by = :created_by ORDER BY e.event_date DESC, a.registered_at DESC');
$eventAttendees->execute(['created_by' => $user['id']]);
$eventAttendees = $eventAttendees->fetchAll();
$showOrganizerForm = isset($_GET['create']) || isset($_GET['edit']) || $_SERVER['REQUEST_METHOD'] === 'POST';

$editEvent = null;
if (isset($_GET['edit'])) {
    $eventId = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = :id AND created_by = :created_by');
    $stmt->execute(['id' => $eventId, 'created_by' => $user['id']]);
    $editEvent = $stmt->fetch();
    if ($editEvent) {
        $editEvent['ticket_options'] = implode("\n", array_map(function ($ticket) {
            return $ticket['name'] . ' | ' . $ticket['price'] . ' | ' . $ticket['quantity'];
        }, json_decode($editEvent['ticket_options'], true)));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premier Hotel Organizer Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="page-organizer">
<?php include __DIR__ . '/header.php'; ?>
<main>
    <div class="container organizer-dashboard">
        <h1>Premier Hotel Organizer Dashboard</h1>
        <?php if ($message): ?>
            <div class="notice"><?= escape($message) ?></div>
        <?php endif; ?>
        <section class="grid organizer-grid">
            <?php if ($showOrganizerForm): ?>
            <div class="panel">
                <h2><?= $editEvent ? 'Edit event' : 'Create event' ?></h2>
                <form method="post" action="organizer.php">
                    <input type="hidden" name="event_id" value="<?= escape($editEvent['id'] ?? '0') ?>">
                    <label>Title</label>
                    <input type="text" name="title" value="<?= escape($_POST['title'] ?? $editEvent['title'] ?? '') ?>">
                    <label>Description</label>
                    <textarea name="description" rows="5"><?= escape($_POST['description'] ?? $editEvent['description'] ?? '') ?></textarea>
                    <label>Date & time</label>
                    <input type="datetime-local" name="event_date" value="<?= escape($_POST['event_date'] ?? ($editEvent ? date('Y-m-d\TH:i', strtotime($editEvent['event_date'])) : '')) ?>">
                    <label>Venue</label>
                    <input type="text" name="venue" value="<?= escape($_POST['venue'] ?? $editEvent['venue'] ?? '') ?>">
                    <label>Organizer email</label>
                    <input type="email" name="organizer_email" value="<?= escape($_POST['organizer_email'] ?? $editEvent['organizer_email'] ?? $user['email']) ?>">
                    <label>Category</label>
                    <input type="text" name="category" value="<?= escape($_POST['category'] ?? $editEvent['category'] ?? 'Conference') ?>">
                    <label>Ticket options</label>
                    <textarea name="ticket_options" rows="5" placeholder="Name | Price | Quantity"><?= escape($_POST['ticket_options'] ?? $editEvent['ticket_options'] ?? '') ?></textarea>
                    <input type="submit" value="Save event" class="button">
                    <a class="button" href="organizer.php" style="background:#95a5a6;">Cancel</a>
                </form>
            </div>
            <?php else: ?>
            <div class="panel">
                <h2>Organizer actions</h2>
                <a class="button" href="organizer.php?create=1">Create new event</a>
            </div>
            <?php endif; ?>
            <div class="panel">
                <h2>My events</h2>
            <?php if (empty($events)): ?>
                <p>You have not created any events yet.</p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr><th>Title</th><th>Date</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $event): ?>
                                <tr>
                                    <td><?= escape($event['title']) ?></td>
                                    <td><?= escape(date('M j, Y g:i A', strtotime($event['event_date']))) ?></td>
                                    <td class="actions">
                                        <a class="button" href="organizer.php?edit=<?= $event['id'] ?>">Edit</a>
                                        <a class="button" href="organizer.php?delete=<?= $event['id'] ?>" onclick="return confirm('Delete this event?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <h2>RSVPs with 'No'</h2>
        <?php if (empty($noRsvp)): ?>
            <p>No attendees have declined yet.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>Event</th><th>Name</th><th>Email</th><th>Ticket</th><th>Registered</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($noRsvp as $attendee): ?>
                            <tr>
                                <td><?= escape($attendee['event_title']) ?></td>
                                <td><?= escape($attendee['name']) ?></td>
                                <td><?= escape($attendee['email']) ?></td>
                                <td><?= escape($attendee['ticket_type']) ?></td>
                                <td><?= escape($attendee['registered_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Registered attendees</h2>
        <?php if (empty($eventAttendees)): ?>
            <p>No participants have registered for your events yet.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>Event</th><th>Name</th><th>Email</th><th>Status</th><th>Ticket</th><th>Registered</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eventAttendees as $attendee): ?>
                            <tr>
                                <td><?= escape($attendee['event_title']) ?></td>
                                <td><?= escape($attendee['name']) ?></td>
                                <td><?= escape($attendee['email']) ?></td>
                                <td><?= escape($attendee['rsvp_status'] ?: 'Pending') ?></td>
                                <td><?= escape($attendee['ticket_type']) ?></td>
                                <td><?= escape($attendee['registered_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    </div>
</main>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
