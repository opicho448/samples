<?php
require_once 'helpers.php';
$user = currentUser();
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$showPast = isset($_GET['past']) && $_GET['past'] === '1';

$sql = 'SELECT * FROM events WHERE 1=1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (title LIKE :s OR description LIKE :s OR organizer LIKE :s)';
    $params['s'] = "%$search%";
}
if ($category !== '') {
    $sql .= ' AND category = :category';
    $params['category'] = $category;
}
if ($showPast) {
    $sql .= ' AND event_date < NOW()';
    $sql .= ' ORDER BY event_date DESC';
} else {
    $sql .= ' AND event_date >= NOW()';
    $sql .= ' ORDER BY event_date ASC';
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();
$categories = $pdo->query('SELECT DISTINCT category FROM events ORDER BY category')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PREMIER HOTEL EVENT REGISTRATION AND MANAGEMENT SYSTEM</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="page-events">
<?php include __DIR__ . '/header.php'; ?>
<main class="container">
    <section class="panel">
        <h2><?= $showPast ? 'Past events' : 'Find events' ?></h2>
        <form method="get" action="index.php">
            <?php if ($showPast): ?>
                <input type="hidden" name="past" value="1">
            <?php endif; ?>
            <label>Search events</label>
            <input type="search" name="search" value="<?= escape($search) ?>" placeholder="Search by title, organizer, or description">
            <label>Category</label>
            <select name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= escape($cat['category']) ?>" <?= $category === $cat['category'] ? 'selected' : '' ?>><?= escape($cat['category']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="submit" value="Search" class="button">
            <?php if ($showPast): ?>
                <a class="button secondary" href="index.php">View upcoming events</a>
            <?php else: ?>
                <a class="button secondary" href="index.php?past=1">View past events</a>
            <?php endif; ?>
        </form>
    </section><br><br>
    <section class="grid grid-3">
        <?php if (count($events) === 0): ?>
            <div class="card">
                <h2>No events found</h2>
                <p>Try another search or check back later for upcoming events.</p>
            </div> 
        <?php endif; ?>
        <?php foreach ($events as $event): ?>
            <?php $cat = strtolower(preg_replace('/[^a-z0-9]+/','-', $event['category'] ?? 'default')); $catClass = 'category-' . ($cat ?: 'default'); ?>
            <div class="card event-card <?= $catClass ?>"> 
                <div class="card-content" >
                    <h2><?= escape($event['title']) ?></h2>
                    <div class="event-meta">
                        <span><?= date('M j, Y g:i A', strtotime($event['event_date'])) ?></span>
                        <span><?= escape($event['venue']) ?></span>
                        <span><?= escape($event['category']) ?></span>
                    </div>
                    <p><?= nl2br(escape($event['description'])) ?></p>
                    <p><strong>Organizer:</strong> <?= escape($event['organizer']) ?></p>
                    <a class="button" href="event.php?id=<?= $event['id'] ?>">View event</a>
                </div>
            </div>
        <?php endforeach; ?>
    </section>
</main>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
