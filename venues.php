<?php
require_once 'helpers.php';
$user = currentUser();
$venues = $pdo->query('SELECT * FROM venues ORDER BY name')->fetchAll();
$bookings = $pdo->query('SELECT vb.*, v.name AS venue_name, e.title AS event_title FROM venue_bookings vb LEFT JOIN venues v ON vb.venue_id = v.id LEFT JOIN events e ON vb.event_id = e.id ORDER BY vb.start_datetime ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premier Hotel Venues</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="page-venues">
<?php include __DIR__ . '/header.php'; ?>
<main class="container">
    <section class="panel">
        <h2>Available venues</h2>
        <?php if (empty($venues)): ?>
            <p>No venues are currently available. Admins can add venues from the dashboard.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>Name</th><th>Location</th><th>Capacity</th><th>Description</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($venues as $venue): ?>
                            <tr>
                                <td><?= escape($venue['name']) ?></td>
                                <td><?= escape($venue['location']) ?></td>
                                <td><?= escape($venue['capacity']) ?></td>
                                <td><?= escape($venue['description']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <section class="panel">
        <h2>Booked venue schedule</h2>
        <?php if (empty($bookings)): ?>
            <p>No venue bookings have been made yet.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>Venue</th><th>Event</th><th>Start</th><th>End</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?= escape($booking['venue_name']) ?></td>
                                <td><?= escape($booking['event_title'] ?? 'Unassigned') ?></td>
                                <td><?= escape($booking['start_datetime']) ?></td>
                                <td><?= escape($booking['end_datetime']) ?></td>
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
