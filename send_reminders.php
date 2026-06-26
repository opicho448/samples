<?php
require_once 'helpers.php';

do {
    $siteUrl = siteUrl();
    $stmt = $pdo->prepare('SELECT e.id, e.title, e.event_date, e.venue, e.organizer_email, a.email AS attendee_email, a.name AS attendee_name FROM events e JOIN attendees a ON a.event_id = e.id WHERE e.event_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)');
    $stmt->execute();
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        if (!filter_var($row['attendee_email'], FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $eventTime = escape(date('F j, Y g:i A', strtotime($row['event_date'])));
        $replyLink = $siteUrl . '/my_events.php';
        $message = "<p>Hi " . escape($row['attendee_name']) . ",</p>" .
            "<p>This is a reminder for your registration to <strong>" . escape($row['title']) . "</strong> on " . $eventTime . " at " . escape($row['venue']) . ".</p>" .
            "<p>If you want to confirm or update your RSVP, please visit <a href=\"" . $replyLink . "\">your registrations</a>.</p>";
        sendNotification($row['attendee_email'], 'Event reminder', $message, null, 'event_reminder');
    }

    $managerRows = $pdo->prepare('SELECT DISTINCT e.organizer_email, e.title, e.event_date FROM events e JOIN attendees a ON a.event_id = e.id WHERE e.event_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)');
    $managerRows->execute();
    $managerRows = $managerRows->fetchAll();

    foreach ($managerRows as $row) {
        if (!filter_var($row['organizer_email'], FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $message = "<p>A reminder that your event <strong>" . escape($row['title']) . "</strong> is scheduled for " . escape(date('F j, Y g:i A', strtotime($row['event_date']))) . ".</p>";
        sendNotification($row['organizer_email'], 'Event organizer reminder', $message, null, 'event_reminder');
    }

    echo "Reminders sent.\n";
    break;
} while (false);
