<?php
require_once 'helpers.php';

$user = currentUser();
$selectedRole = $_SESSION['selected_role'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedRole = $_POST['role'] ?? null;
    $allowedRoles = ['attendee', 'organizer', 'admin'];
    if ($postedRole && in_array($postedRole, $allowedRoles, true)) {
        $_SESSION['selected_role'] = $postedRole;
    } else {
        unset($_SESSION['selected_role']);
    }
    header('Location: dashboard.php');
    exit;
}

if (isset($_GET['clear_role'])) {
    unset($_SESSION['selected_role']);
    header('Location: dashboard.php');
    exit;
}

$roleLabels = $roleLabels ?? [
    'attendee' => 'Attendee',
    'organizer' => 'Organizer',
    'admin' => 'Administrator',
];

$upcomingEvents = $pdo->prepare('SELECT id, title, description, event_date FROM events WHERE event_date >= NOW() ORDER BY event_date ASC LIMIT 6');
$upcomingEvents->execute();
$upcomingEvents = $upcomingEvents->fetchAll();
$pastEventsCountStmt = $pdo->prepare('SELECT COUNT(*) FROM events WHERE event_date < NOW()');
$pastEventsCountStmt->execute();
$pastEventsCount = (int)$pastEventsCountStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Premier Hotel Dashboard</title>
    <link rel="icon" href="images/hotel_logo.png">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="page-dashboard">
<?php include __DIR__ . '/header.php'; ?>
<main>
    <section class="hero-large float-up">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <h1>PREMIER HOTEL EVENT MANAGEMENT</h1>
            <p>Explore resort-inspired events, venue planning, and guest experiences that match every celebration.</p>
            <div class="hero-button-group">
                <form method="post" action="dashboard.php">
                    <input type="hidden" name="role" value="attendee">
                    <button type="submit" class="button">Attendee</button>
                </form>
                <form method="post" action="dashboard.php">
                    <input type="hidden" name="role" value="organizer">
                    <button type="submit" class="button">Organizer</button>
                </form>
                <form method="post" action="dashboard.php">
                    <input type="hidden" name="role" value="admin">
                    <button type="submit" class="button">Admin</button>
                </form>
            </div>
        </div>
    </section>

    <div class="container">
        <div class="marquee"><p>Welcome — Explore curated events, venue bookings, and exclusive experiences. Book early for best seats!</p></div>

        <section class="panel upcoming-events-panel">
            <h2>Upcoming events</h2>
            <?php if (!empty($upcomingEvents)): ?>
                <div class="grid upcoming-events-grid">
                    <?php foreach ($upcomingEvents as $event): ?>
                        <?php $eventLink = $user ? 'event.php?id=' . $event['id'] : 'login.php?next=' . urlencode('event.php?id=' . $event['id']); ?>
                        <a class="event-card" href="<?= escape($eventLink) ?>">
                            <div class="event-card-content">
                                <h3><?= escape($event['title']) ?></h3>
                                <div class="event-meta">
                                    <span><?= date('M j, Y', strtotime($event['event_date'])) ?></span>
                                </div>
                                <p class="event-description"><?= nl2br(escape(substr($event['description'], 0, 180))) ?><?= strlen($event['description']) > 180 ? '...' : '' ?></p>
                            </div>
                            
                        </a>
                    <?php endforeach; ?>
                </div>
               <br><br>
            <?php else: ?>
                <p>No upcoming events are available right now.</p>
            <?php endif; ?>
            <?php if ($pastEventsCount > 0): ?>
                <div class="panel past-events-panel">
                    <h3>Past events</h3>
                    <p>There are <?= escape($pastEventsCount) ?> past events available for review.</p>
                    <a class="button secondary" href="index.php?past=1">View past events</a>
                </div>
            <?php endif; ?>
        </section>

        <?php if (!empty($_SESSION['selected_role'])):
            $selectedRole = $_SESSION['selected_role'];
        ?>
            <div class="modal-overlay role-modal" id="roleModal">
                <div class="modal-card">
                    <a class="modal-close" href="dashboard.php?clear_role=1" aria-label="Close">×</a>
                    <div class="modal-content">
                        <h2><?= escape($roleLabels[$selectedRole] ?? $selectedRole) ?></h2>
                        <?php if ($selectedRole === 'admin'): ?>
                            <p>Admin access requires existing administrator credentials. Please log in using an admin account.</p>
                        <?php else: ?>
                            <p>Continue by logging in or registering for the selected role.</p>
                        <?php endif; ?>
                        <div class="button-group role-button-group">
                            <?php if ($selectedRole === 'admin'): ?>
                                <a class="button" href="login.php">Login as Admin</a>
                            <?php else: ?>
                                <a class="button" href="login.php">Login</a>
                                <a class="button" href="register_user.php?role=<?= urlencode($selectedRole) ?>">Sign up</a>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <a class="modal-link" href="dashboard.php?clear_role=1">Choose a different role</a>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var modal = document.getElementById('roleModal');
                    if (modal) {
                        modal.classList.add('show');
                        modal.addEventListener('click', function(event) {
                            if (event.target === modal) {
                                modal.classList.remove('show');
                            }
                        });
                    }
                });
            </script>
        <?php endif; ?>
    </div>
</main>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
