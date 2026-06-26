<?php
require_once 'helpers.php';
require_once __DIR__ . '/includes/admin_helpers.php';
ensureAttendeesApprovalSchema();
$user = requireLogin('admin');

$allowedViews = ['overview', 'events', 'attendees', 'venues', 'approvals', 'logs', 'message'];
$currentView = $_GET['view'] ?? 'overview';
if (!in_array($currentView, $allowedViews, true)) {
    $currentView = 'overview';
}
if (isset($_GET['edit']) || (isset($_GET['show_form']) && $_GET['show_form'] === 'event')) {
    $currentView = 'events';
} elseif (isset($_GET['edit_venue']) || (isset($_GET['show_form']) && $_GET['show_form'] === 'venue')) {
    $currentView = 'venues';
} elseif (isset($_GET['show_form']) && $_GET['show_form'] === 'message') {
    $currentView = 'message';
} elseif (isset($_GET['show_panel']) && $_GET['show_panel'] === 'logs') {
    $currentView = 'logs';
}

$message = '';
$userMessage = '';
$approvalMessage = '';
if (!empty($_SESSION['admin_flash'])) {
    $approvalMessage = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approval_action'], $_POST['attendee_id'])) {
        $attendeeId = (int)$_POST['attendee_id'];
        if ($_POST['approval_action'] === 'approve') {
            $result = approveRegistration($attendeeId);
        } elseif ($_POST['approval_action'] === 'reject') {
            $result = rejectRegistration($attendeeId);
        } else {
            $result = ['success' => false, 'message' => 'Unknown approval action.'];
        }
        $_SESSION['admin_flash'] = $result['message'];
        $redirectView = $_POST['redirect_view'] ?? 'overview';
        if (!in_array($redirectView, $allowedViews, true)) {
            $redirectView = 'overview';
        }
        header('Location: admin.php?view=' . urlencode($redirectView));
        exit;
    } elseif (isset($_POST['message_action']) && $_POST['message_action'] === 'broadcast') {
        $subject = trim($_POST['subject'] ?? 'Announcement');
        $broadcastMessage = trim($_POST['message'] ?? '');
        if ($broadcastMessage === '') {
            $message = 'Broadcast message cannot be empty.';
        } else {
            $stmt = $pdo->query('SELECT id, email FROM users');
            $allUsers = $stmt->fetchAll();
            $insert = $pdo->prepare('INSERT INTO messages (from_user_id, to_user_id, subject, message) VALUES (:from_user_id, :to_user_id, :subject, :message)');
            foreach ($allUsers as $recipient) {
                if ($recipient['id'] === $user['id']) {
                    continue;
                }
                $insert->execute([
                    'from_user_id' => $user['id'],
                    'to_user_id' => $recipient['id'],
                    'subject' => $subject,
                    'message' => $broadcastMessage,
                ]);
                // send email notification as well
                if (!empty($recipient['email']) && filter_var($recipient['email'], FILTER_VALIDATE_EMAIL)) {
                    // best-effort: log and continue on failure
                    sendNotification($recipient['email'], $subject, $broadcastMessage, $recipient['id'], 'broadcast');
                }
            }
            $message = 'Broadcast message queued to all registered users.';
            $_SESSION['admin_flash'] = $message;
            header('Location: admin.php?view=message');
            exit;
        }
    } elseif (isset($_POST['user_action'])) {
        $action = $_POST['user_action'];
        $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        if ($action === 'save' && $userId > 0) {
            $name = trim($_POST['user_name'] ?? '');
            $email = trim($_POST['user_email'] ?? '');
            $phone = trim($_POST['user_phone'] ?? '');
            $role = trim($_POST['user_role'] ?? 'user');
            $password = trim($_POST['user_password'] ?? '');
            if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $userMessage = 'Name and a valid email are required.';
            } else {
                $updateFields = 'name = :name, email = :email, phone = :phone, role = :role';
                $params = [
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'role' => $role,
                    'id' => $userId,
                ];
                if ($password !== '') {
                    $updateFields .= ', password_hash = :password_hash';
                    $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }
                $stmt = $pdo->prepare("UPDATE users SET {$updateFields} WHERE id = :id");
                $stmt->execute($params);
                $userMessage = 'User updated successfully.';
            }
        }
        if ($action === 'delete' && isset($_POST['user_id'])) {
            $deleteId = (int)$_POST['user_id'];
            if ($deleteId === $user['id']) {
                $userMessage = 'You cannot delete your own account while logged in.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
                $stmt->execute(['id' => $deleteId]);
                $userMessage = 'User account deleted.';
            }
        }
    } else {
        $eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $eventDate = trim($_POST['event_date'] ?? '');
        $venue = trim($_POST['venue'] ?? '');
        $organizer = trim($_POST['organizer'] ?? '');
        $organizerEmail = trim($_POST['organizer_email'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $ticketOptionsText = trim($_POST['ticket_options'] ?? '');
        $ticketOptions = [];

        foreach (explode("\n", $ticketOptionsText) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 3) continue;
            $ticketOptions[] = [
                'name' => $parts[0],
                'price' => (float)$parts[1],
                'quantity' => (int)$parts[2],
            ];
        }
        if ($title === '' || $eventDate === '' || $venue === '' || empty($ticketOptions)) {
            $message = 'Title, date, venue, and at least one ticket type are required.';
        } else {
            if ($eventId > 0) {
                $stmt = $pdo->prepare('UPDATE events SET title = :title, description = :description, event_date = :event_date, venue = :venue, organizer = :organizer, organizer_email = :organizer_email, category = :category, ticket_options = :ticket_options WHERE id = :id');
                $stmt->execute([
                    'title' => $title,
                    'description' => $description,
                    'event_date' => $eventDate,
                    'venue' => $venue,
                    'organizer' => $organizer,
                    'organizer_email' => $organizerEmail,
                    'category' => $category,
                    'ticket_options' => json_encode($ticketOptions),
                    'id' => $eventId,
                ]);
                $message = 'Event updated successfully.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO events (title, description, event_date, venue, organizer, organizer_email, category, ticket_options) VALUES (:title, :description, :event_date, :venue, :organizer, :organizer_email, :category, :ticket_options)');
                $stmt->execute([
                    'title' => $title,
                    'description' => $description,
                    'event_date' => $eventDate,
                    'venue' => $venue,
                    'organizer' => $organizer,
                    'organizer_email' => $organizerEmail,
                    'category' => $category,
                    'ticket_options' => json_encode($ticketOptions),
                ]);
                $message = 'Event created successfully.';
            }
            $_SESSION['admin_flash'] = $message;
            header('Location: admin.php?view=events');
            exit;
        }
    }
}
if (isset($_GET['delete'])) {
    $eventId = (int)$_GET['delete'];
    $stmt = $pdo->prepare('DELETE FROM attendees WHERE event_id = :event_id');
    $stmt->execute(['event_id' => $eventId]);
    $stmt = $pdo->prepare('DELETE FROM events WHERE id = :id');
    $stmt->execute(['id' => $eventId]);
    header('Location: admin.php?view=events');
    exit;
}
if (isset($_GET['delete_user'])) {
    $deleteId = (int)$_GET['delete_user'];
    if ($deleteId !== $user['id']) {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $deleteId]);
    }
    header('Location: admin.php?view=overview');
    exit;
}
$events = $pdo->query('SELECT * FROM events ORDER BY event_date ASC')->fetchAll();
$pendingApprovals = $pdo->query(
    'SELECT a.*, e.title AS event_title FROM attendees a ' .
    'LEFT JOIN events e ON a.event_id = e.id ' .
    "WHERE a.registration_status = 'Pending' ORDER BY a.registered_at ASC"
)->fetchAll();
$pendingCount = count($pendingApprovals);
$pendingPreview = array_slice($pendingApprovals, 0, 5);

$totalRegistrations = (int)$pdo->query('SELECT COUNT(*) FROM attendees')->fetchColumn();
$totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(amount_paid), 0) FROM attendees WHERE registration_status = 'Confirmed'")->fetchColumn();
$activeEvents = (int)$pdo->query('SELECT COUNT(*) FROM events WHERE event_date >= NOW()')->fetchColumn();
$last7 = (int)$pdo->query("SELECT COUNT(*) FROM attendees WHERE registered_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$prev7 = (int)$pdo->query("SELECT COUNT(*) FROM attendees WHERE registered_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND registered_at < DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
if ($prev7 > 0) {
    $registrationTrendPct = (int)round((($last7 - $prev7) / $prev7) * 100);
} elseif ($last7 > 0) {
    $registrationTrendPct = 100;
} else {
    $registrationTrendPct = 0;
}

$chartRows = $pdo->query(
    "SELECT DATE(registered_at) AS reg_day, COUNT(*) AS cnt FROM attendees " .
    "WHERE registered_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY DATE(registered_at)"
)->fetchAll(PDO::FETCH_KEY_PAIR);
$registrationChart = [];
for ($i = 29; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $registrationChart[] = [
        'label' => date('M j', strtotime($day)),
        'count' => (int)($chartRows[$day] ?? 0),
    ];
}

$attendeeSearch = trim($_GET['search'] ?? '');
$attendeeStatus = trim($_GET['status'] ?? '');
$attendeeDateFrom = trim($_GET['date_from'] ?? '');
$attendeeDateTo = trim($_GET['date_to'] ?? '');
$attendeePage = max(1, (int)($_GET['page'] ?? 1));
$attendeePerPage = 20;
$attendeeWhere = ['1=1'];
$attendeeParams = [];
if ($attendeeSearch !== '') {
    $attendeeWhere[] = '(a.name LIKE :search OR a.email LIKE :search OR e.title LIKE :search)';
    $attendeeParams['search'] = '%' . $attendeeSearch . '%';
}
if ($attendeeStatus !== '' && in_array($attendeeStatus, ['Pending', 'Confirmed', 'Rejected'], true)) {
    $attendeeWhere[] = 'COALESCE(a.registration_status, a.payment_status) = :status';
    $attendeeParams['status'] = $attendeeStatus;
}
if ($attendeeDateFrom !== '') {
    $attendeeWhere[] = 'DATE(a.registered_at) >= :date_from';
    $attendeeParams['date_from'] = $attendeeDateFrom;
}
if ($attendeeDateTo !== '') {
    $attendeeWhere[] = 'DATE(a.registered_at) <= :date_to';
    $attendeeParams['date_to'] = $attendeeDateTo;
}
$attendeeWhereSql = implode(' AND ', $attendeeWhere);
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM attendees a LEFT JOIN events e ON a.event_id = e.id WHERE {$attendeeWhereSql}");
$countStmt->execute($attendeeParams);
$attendeeTotal = (int)$countStmt->fetchColumn();
$attendeeTotalPages = max(1, (int)ceil($attendeeTotal / $attendeePerPage));
if ($attendeePage > $attendeeTotalPages) {
    $attendeePage = $attendeeTotalPages;
}
$attendeeOffset = ($attendeePage - 1) * $attendeePerPage;
$listStmt = $pdo->prepare(
    "SELECT a.*, e.title AS event_title FROM attendees a LEFT JOIN events e ON a.event_id = e.id " .
    "WHERE {$attendeeWhereSql} ORDER BY a.registered_at DESC LIMIT {$attendeePerPage} OFFSET {$attendeeOffset}"
);
$listStmt->execute($attendeeParams);
$attendees = $listStmt->fetchAll();
$users = $pdo->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll();
$editUser = null;
if (isset($_GET['edit_user'])) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute(['id' => (int)$_GET['edit_user']]);
    $editUser = $stmt->fetch();
}
$editEvent = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = :id');
    $stmt->execute(['id' => (int)$_GET['edit']]);
    $editEvent = $stmt->fetch();
    if ($editEvent) {
        $editEvent['ticket_options'] = implode("\n", array_map(function($ticket) {
            return $ticket['name'] . ' | ' . $ticket['price'] . ' | ' . $ticket['quantity'];
        }, json_decode($editEvent['ticket_options'], true)));
        $editEvent['organizer_email'] = $editEvent['organizer_email'] ?? '';
    }
}
// --- Venue management for admins ---
$venueMessage = '';
if (isset($_POST['venue_action'])) {
    $action = $_POST['venue_action'];
    if ($action === 'add') {
        $vname = trim($_POST['venue_name'] ?? '');
        $vloc = trim($_POST['venue_location'] ?? '');
        $vcap = (int)($_POST['venue_capacity'] ?? 0);
        $vdesc = trim($_POST['venue_description'] ?? '');
        if ($vname !== '') {
            $stmt = $pdo->prepare('INSERT INTO venues (name, location, capacity, description) VALUES (:name, :location, :capacity, :description)');
            $stmt->execute(['name' => $vname, 'location' => $vloc, 'capacity' => $vcap ?: null, 'description' => $vdesc]);
            $venueMessage = 'Venue added.';
        }
    }
    if ($action === 'edit' && isset($_POST['venue_id'])) {
        $vid = (int)$_POST['venue_id'];
        $vname = trim($_POST['venue_name'] ?? '');
        $vloc = trim($_POST['venue_location'] ?? '');
        $vcap = (int)($_POST['venue_capacity'] ?? 0);
        $vdesc = trim($_POST['venue_description'] ?? '');
        $stmt = $pdo->prepare('UPDATE venues SET name = :name, location = :location, capacity = :capacity, description = :description WHERE id = :id');
        $stmt->execute(['name' => $vname, 'location' => $vloc, 'capacity' => $vcap ?: null, 'description' => $vdesc, 'id' => $vid]);
        $venueMessage = 'Venue updated.';
    }
    if ($action === 'delete' && isset($_POST['venue_id'])) {
        $vid = (int)$_POST['venue_id'];
        $stmt = $pdo->prepare('DELETE FROM venues WHERE id = :id');
        $stmt->execute(['id' => $vid]);
        $venueMessage = 'Venue removed.';
    }
}
$venuesList = $pdo->query('SELECT * FROM venues ORDER BY name')->fetchAll();

$venueEdit = null;
if (isset($_GET['edit_venue'])) {
    $vid = (int)$_GET['edit_venue'];
    $vstmt = $pdo->prepare('SELECT * FROM venues WHERE id = :id');
    $vstmt->execute(['id' => $vid]);
    $venueEdit = $vstmt->fetch();
}

$showEventForm = ($currentView === 'events') && ((isset($_GET['show_form']) && $_GET['show_form'] === 'event') || isset($_GET['edit']) || ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['user_action']) && !isset($_POST['venue_action']) && !isset($_POST['message_action']) && !isset($_POST['approval_action'])));
$showVenueForm = ($currentView === 'venues') && ((isset($_GET['show_form']) && $_GET['show_form'] === 'venue') || isset($_GET['edit_venue']) || (isset($_POST['venue_action']) && in_array($_POST['venue_action'], ['add', 'edit'], true)));
$showMessageForm = ($currentView === 'message');

$viewTitles = [
    'overview' => ['title' => 'Dashboard Overview', 'subtitle' => 'System health and activity at a glance'],
    'events' => ['title' => 'Events', 'subtitle' => 'Create and manage event listings'],
    'attendees' => ['title' => 'Attendees', 'subtitle' => 'Search and review all registrations'],
    'venues' => ['title' => 'Venues', 'subtitle' => 'Manage venue inventory'],
    'approvals' => ['title' => 'Approvals', 'subtitle' => 'Verify payments before issuing tickets'],
    'logs' => ['title' => 'System Logs', 'subtitle' => 'Inspect application and server logs'],
    'message' => ['title' => 'Broadcast Message', 'subtitle' => 'Send announcements to all users'],
];
$pageMeta = $viewTitles[$currentView] ?? $viewTitles['overview'];

// prepare log candidates used by the logs viewer and download handler
$logCandidates = [
    __DIR__ . '/app.log',
    __DIR__ . '/logs/app.log',
    'C:/xampp/apache/logs/error.log',
    '/var/log/apache2/error.log',
    '/var/log/httpd/error_log'
];

// handle secure log download before any HTML output
if ($currentView === 'logs' && isset($_GET['download']) && isset($_GET['file'])) {
    $fi = (int)$_GET['file'];
    if (!isset($logCandidates[$fi]) || !file_exists($logCandidates[$fi]) || !is_readable($logCandidates[$fi])) {
        header('HTTP/1.1 404 Not Found');
        echo 'Log file not found or not readable.';
        exit;
    }
    $path = $logCandidates[$fi];
    $basename = basename($path);
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $basename . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

?>

<?php include __DIR__ . '/includes/admin_layout.php'; ?>
