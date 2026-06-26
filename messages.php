<?php
require_once 'helpers.php';
$user = requireLogin();
$uid = $user['id'];
$isOrganizer = isOrganizer();
$isAdmin = isAdmin();

$eventId = isset($_REQUEST['event_id']) && $_REQUEST['event_id'] !== '' ? (int)$_REQUEST['event_id'] : null;
$participantId = isset($_REQUEST['participant_id']) ? (int)$_REQUEST['participant_id'] : 0;
$fetchThread = isset($_GET['fetch_thread']);

$errors = [];

try {
    ensureMessagesSchema();
} catch (PDOException $e) {
    // Keep the existing flow; errors will surface when messages are accessed.
}

function userCanAccessEvent($pdo, $userId, $eventId, $isOrganizer) {
    if (!$eventId) {
        return false;
    }
    if ($isOrganizer) {
        $stmt = $pdo->prepare('SELECT id FROM events WHERE id = :event_id AND created_by = :user_id LIMIT 1');
        $stmt->execute(['event_id' => $eventId, 'user_id' => $userId]);
        return (bool)$stmt->fetch();
    }
    $stmt = $pdo->prepare('SELECT event_id FROM attendees WHERE event_id = :event_id AND user_id = :user_id LIMIT 1');
    $stmt->execute(['event_id' => $eventId, 'user_id' => $userId]);
    return (bool)$stmt->fetch();
}

function getEventThreadMessages($pdo, $eventId, $currentUserId, $otherUserId) {
    if ($eventId === null) {
        $stmt = $pdo->prepare(
            'SELECT m.*, u_from.name AS from_name, u_to.name AS to_name
             FROM messages m
             LEFT JOIN users u_from ON m.from_user_id = u_from.id
             LEFT JOIN users u_to ON m.to_user_id = u_to.id
             WHERE (m.event_id IS NULL OR m.event_id = 0)
               AND ((m.from_user_id = :current_user1 AND m.to_user_id = :other_user1)
                 OR (m.from_user_id = :other_user2 AND m.to_user_id = :current_user2))
             ORDER BY m.created_at ASC'
        );
        $stmt->execute([
            'current_user1' => $currentUserId,
            'other_user1' => $otherUserId,
            'other_user2' => $otherUserId,
            'current_user2' => $currentUserId,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT m.*, u_from.name AS from_name, u_to.name AS to_name
             FROM messages m
             LEFT JOIN users u_from ON m.from_user_id = u_from.id
             LEFT JOIN users u_to ON m.to_user_id = u_to.id
             WHERE m.event_id = :event_id
               AND ((m.from_user_id = :current_user1 AND m.to_user_id = :other_user1)
                 OR (m.from_user_id = :other_user2 AND m.to_user_id = :current_user2))
             ORDER BY m.created_at ASC'
        );
        $stmt->execute([
            'event_id' => $eventId,
            'current_user1' => $currentUserId,
            'other_user1' => $otherUserId,
            'other_user2' => $otherUserId,
            'current_user2' => $currentUserId,
        ]);
    }
    return $stmt->fetchAll();
}

function markThreadAsRead($pdo, $eventId, $toUserId, $fromUserId) {
    if ($eventId === null) {
        $stmt = $pdo->prepare(
            'UPDATE messages SET is_read = 1
             WHERE (event_id IS NULL OR event_id = 0)
               AND to_user_id = :to_user_id
               AND from_user_id = :from_user_id
               AND is_read = 0'
        );
        $stmt->execute([
            'to_user_id' => $toUserId,
            'from_user_id' => $fromUserId,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'UPDATE messages SET is_read = 1
             WHERE event_id = :event_id
               AND to_user_id = :to_user_id
               AND from_user_id = :from_user_id
               AND is_read = 0'
        );
        $stmt->execute([
            'event_id' => $eventId,
            'to_user_id' => $toUserId,
            'from_user_id' => $fromUserId,
        ]);
    }
}

function getConversationConditions($eventId, $userId, $participantId) {
    $condition = '((from_user_id = :user_id1 AND to_user_id = :participant_id1) OR (from_user_id = :participant_id2 AND to_user_id = :user_id2))';
    $params = [
        'user_id1'        => $userId,
        'participant_id1' => $participantId,
        'participant_id2' => $participantId,
        'user_id2'        => $userId,
    ];
    if ($eventId === null) {
        $condition = '(event_id IS NULL OR event_id = 0) AND ' . $condition;
    } else {
        $condition = 'event_id = :event_id AND ' . $condition;
        $params['event_id'] = $eventId;
    }
    return [$condition, $params];
}

function isConversationArchived(PDO $pdo, $eventId, int $userId, int $participantId): bool {
    if ($eventId === null || $eventId === 0) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM messages
             WHERE (event_id IS NULL OR event_id = 0)
               AND (
                   (from_user_id = :user_id1 AND to_user_id = :other_id1)
                   OR (from_user_id = :other_id2 AND to_user_id = :user_id2)
               )
               AND is_archived = 1'
        );
        $stmt->execute([
            'user_id1'  => $userId,
            'other_id1' => $participantId,
            'other_id2' => $participantId,
            'user_id2'  => $userId,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM messages
             WHERE event_id = :event_id
               AND (
                   (from_user_id = :user_id1 AND to_user_id = :other_id1)
                   OR (from_user_id = :other_id2 AND to_user_id = :user_id2)
               )
               AND is_archived = 1'
        );
        $stmt->execute([
            'event_id'  => $eventId,
            'user_id1'  => $userId,
            'other_id1' => $participantId,
            'other_id2' => $participantId,
            'user_id2'  => $userId,
        ]);
    }
    return (int)$stmt->fetchColumn() > 0;
}

function userCanAccessConversation($pdo, $userId, $eventId, $participantId, $isOrganizer, $isAdmin) {
    if (!$participantId) {
        return false;
    }
    if ($eventId === null) {
        return $isAdmin || $isOrganizer;
    }
    return userCanAccessEvent($pdo, $userId, $eventId, $isOrganizer);
}

function insertChatMessage(PDO $pdo, $eventId, int $fromUserId, int $toUserId, string $subject, string $messageText, ?string $photoUrl): void {
    ensureMessagesSchema();

    if ($messageText === '' && $photoUrl !== null) {
        $messageText = '[Photo attachment]';
    }
    if ($messageText === '') {
        $messageText = ' ';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO messages (event_id, from_user_id, to_user_id, subject, message, photo_url, is_read)
         VALUES (:event_id, :from_user_id, :to_user_id, :subject, :message, :photo_url, :is_read)'
    );
    $stmt->bindValue(':event_id', $eventId, $eventId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':from_user_id', $fromUserId, PDO::PARAM_INT);
    $stmt->bindValue(':to_user_id', $toUserId, PDO::PARAM_INT);
    $stmt->bindValue(':subject', $subject, PDO::PARAM_STR);
    $stmt->bindValue(':message', $messageText, PDO::PARAM_STR);
    $stmt->bindValue(':photo_url', $photoUrl, $photoUrl === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':is_read', 0, PDO::PARAM_INT);
    $stmt->execute();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eventId = isset($_POST['event_id']) && $_POST['event_id'] !== '' ? (int)$_POST['event_id'] : null;
    $participantId = isset($_POST['participant_id']) ? (int)$_POST['participant_id'] : 0;

    if (isset($_POST['archive_conversation']) || isset($_POST['unarchive_conversation']) || isset($_POST['delete_conversation'])) {
        if (!$participantId || !userCanAccessConversation($pdo, $uid, $eventId, $participantId, $isOrganizer, $isAdmin)) {
            $errors[] = 'Unable to manage this conversation.';
        } else {
            list($condition, $params) = getConversationConditions($eventId, $uid, $participantId);
            if (isset($_POST['delete_conversation'])) {
                $stmt = $pdo->prepare('DELETE FROM messages WHERE ' . $condition);
                $stmt->execute($params);
            } else {
                $setValue = isset($_POST['archive_conversation']) ? 1 : 0;
                $stmt = $pdo->prepare('UPDATE messages SET is_archived = :archived WHERE ' . $condition);
                $params['archived'] = $setValue;
                $stmt->execute($params);
            }
        }
        $redirectUrl = 'messages.php?participant_id=' . $participantId;
        if ($eventId !== null) {
            $redirectUrl .= '&event_id=' . $eventId;
        }
        header('Location: ' . $redirectUrl);
        exit;
    }

    $messageText = trim($_POST['message'] ?? '');
    $photoUrl = null;

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $fileInfo = getimagesize($_FILES['photo']['tmp_name']);
        if ($fileInfo && in_array($fileInfo['mime'], ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
            $uploadDir = __DIR__ . '/uploads/messages';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $fileName = 'msg_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . ($extension ?: 'jpg');
            $destination = $uploadDir . '/' . $fileName;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                $photoUrl = 'uploads/messages/' . $fileName;
            }
        } else {
            $errors[] = 'Uploaded file must be a valid image.';
        }
    }

    if ($messageText === '' && $photoUrl === null) {
        $errors[] = 'Please enter a message or attach a photo before sending.';
    }

    if ($eventId && !userCanAccessEvent($pdo, $uid, $eventId, $isOrganizer)) {
        $errors[] = 'You do not have access to that event chat.';
    }

    if (empty($errors)) {
        try {
            $event = null;
            $toUserId = null;

            if ($eventId) {
                $eventStmt = $pdo->prepare('SELECT id, title, created_by FROM events WHERE id = :event_id LIMIT 1');
                $eventStmt->execute(['event_id' => $eventId]);
                $event = $eventStmt->fetch();

                if (!$event && !$isAdmin) {
                    $errors[] = 'Selected event could not be found.';
                }
            }

            if ($isAdmin) {
                if (!$participantId) {
                    $errors[] = 'Select a user to send a message.';
                } else {
                    $toUserId = $participantId;
                    $eventId = null;
                }
            } elseif ($isOrganizer) {
                if (!$participantId) {
                    $errors[] = 'Select an attendee thread to send a message.';
                } else {
                    $adminCheck = $pdo->prepare('SELECT id FROM users WHERE id = :user_id AND role = :role LIMIT 1');
                    $adminCheck->execute(['user_id' => $participantId, 'role' => 'admin']);
                    if ($adminCheck->fetch()) {
                        $toUserId = $participantId;
                        $eventId = null;
                    } else {
                        if (!$eventId) {
                            $errors[] = 'Select an event before messaging this attendee.';
                        } else {
                            $stmt = $pdo->prepare('SELECT user_id FROM attendees WHERE event_id = :event_id AND user_id = :participant_id LIMIT 1');
                            $stmt->execute(['event_id' => $eventId, 'participant_id' => $participantId]);
                            if (!$stmt->fetch()) {
                                $errors[] = 'Selected attendee is not registered for the selected event.';
                            } else {
                                $toUserId = $participantId;
                            }
                        }
                    }
                }
            } else {
                if (!$event) {
                    $errors[] = 'Select an event chat before sending.';
                } else {
                    $toUserId = $event['created_by'];
                }
            }

            if (empty($errors) && $toUserId) {
                $subject = $eventId ? 'Event chat: ' . ($event['title'] ?? 'Event') : 'System Message';
                insertChatMessage($pdo, $eventId, $uid, $toUserId, $subject, $messageText, $photoUrl);

                $redirectUrl = 'messages.php?participant_id=' . $participantId;
                if ($eventId !== null) {
                    $redirectUrl .= '&event_id=' . $eventId;
                }
                header('Location: ' . $redirectUrl);
                exit;
            }
        } catch (PDOException $e) {
            error_log('Message send failed: ' . $e->getMessage());
            $errors[] = 'Unable to send message: ' . $e->getMessage();
        }
    }
}

try {
    if ($isOrganizer) {
        $eventsStmt = $pdo->prepare('SELECT id, title, event_date, created_by FROM events WHERE created_by = :user_id ORDER BY event_date DESC');
        $eventsStmt->execute(['user_id' => $uid]);
    } else {
        $eventsStmt = $pdo->prepare(
            'SELECT DISTINCT e.id, e.title, e.event_date, e.created_by, u.name AS organizer_name
             FROM events e
             JOIN attendees a ON a.event_id = e.id
             JOIN users u ON u.id = e.created_by
             WHERE a.user_id = :user_id
             ORDER BY e.event_date DESC'
        );
        $eventsStmt->execute(['user_id' => $uid]);
    }
    $events = $eventsStmt->fetchAll();
} catch (PDOException $e) {
    $events = [];
    $errors[] = 'Messaging is unavailable until the database is migrated.';
}

if ($eventId && !userCanAccessEvent($pdo, $uid, $eventId, $isOrganizer)) {
    $eventId = null;
}

$isSystemMessaging = ($participantId > 0) && !isset($_REQUEST['event_id']);

if ($eventId === null && !$isSystemMessaging && !empty($events)) {
    $eventId = $events[0]['id'];
}

$selectedEvent = null;
foreach ($events as $evt) {
    if ($evt['id'] === $eventId) {
        $selectedEvent = $evt;
        break;
    }
}

$threads = [];
$conversationMessages = [];
$selectedParticipant = null;
$selectedEventTitle = '';

if ($isAdmin && !$selectedEvent && !$participantId) {
    try {
        $allUsersStmt = $pdo->prepare(
            'SELECT u.id AS participant_user_id, u.name AS participant_name, u.email AS participant_email,
                    COALESCE(SUM(CASE WHEN m.to_user_id = :user_id1 AND m.is_read = 0 AND (m.event_id IS NULL OR m.event_id = 0) THEN 1 ELSE 0 END), 0) AS unread_count,
                    MAX(m.created_at) AS last_at
             FROM users u
             LEFT JOIN messages m ON ((m.to_user_id = :user_id2 AND m.from_user_id = u.id) OR (m.from_user_id = :user_id3 AND m.to_user_id = u.id))
                AND (m.event_id IS NULL OR m.event_id = 0)
             WHERE u.id != :user_id4
             GROUP BY u.id, u.name, u.email
             ORDER BY unread_count DESC, last_at DESC, u.name ASC'
        );
        $allUsersStmt->execute(['user_id1' => $uid, 'user_id2' => $uid, 'user_id3' => $uid, 'user_id4' => $uid]);
        $allUsers = $allUsersStmt->fetchAll();
        if (!empty($allUsers)) {
            $participantId = (int)$allUsers[0]['participant_user_id'];
        }
    } catch (PDOException $e) {
        // ignore
    }
}

if ($selectedEvent) {
    $selectedEventTitle = $selectedEvent['title'];
    if ($isAdmin) {
        try {
            $usersStmt = $pdo->prepare('SELECT id AS participant_user_id, name AS participant_name, email AS participant_email FROM users WHERE id != :user_id ORDER BY name');
            $usersStmt->execute(['user_id' => $uid]);
            $threads = $usersStmt->fetchAll();
        } catch (PDOException $e) {
            $threads = [];
        }

        if (!$participantId && !empty($threads)) {
            $participantId = (int)$threads[0]['participant_user_id'];
        }

        foreach ($threads as $thread) {
            if (!empty($thread['participant_user_id']) && $thread['participant_user_id'] === $participantId) {
                $selectedParticipant = $thread;
                break;
            }
        }
    } elseif ($isOrganizer) {
        try {
            $participantsStmt = $pdo->prepare(
                'SELECT a.id AS attendee_id,
                        COALESCE(u.id, 0) AS participant_user_id,
                        COALESCE(u.name, a.name) AS participant_name,
                        COALESCE(u.email, a.email) AS participant_email,
                        a.rsvp_status,
                        COALESCE(SUM(CASE WHEN m.to_user_id = :user_id1 AND m.is_read = 0 THEN 1 ELSE 0 END), 0) AS unread_count,
                        MAX(m.created_at) AS last_at
                 FROM attendees a
                 LEFT JOIN users u ON u.id = a.user_id
                 LEFT JOIN messages m ON m.event_id = a.event_id
                    AND ((m.from_user_id = :user_id2 AND m.to_user_id = u.id)
                      OR (m.from_user_id = u.id AND m.to_user_id = :user_id3))
                 WHERE a.event_id = :event_id AND (a.user_id IS NULL OR a.user_id != :user_id4)
                 GROUP BY a.id, participant_user_id, participant_name, participant_email, a.rsvp_status
                 ORDER BY unread_count DESC, last_at DESC'
            );
            $participantsStmt->execute(['user_id1' => $uid, 'user_id2' => $uid, 'user_id3' => $uid, 'user_id4' => $uid, 'event_id' => $eventId]);
            $threads = $participantsStmt->fetchAll();
        } catch (PDOException $e) {
            $threads = [];
        }

        if (!$participantId && !empty($threads)) {
            foreach ($threads as $thread) {
                if (!empty($thread['participant_user_id'])) {
                    $participantId = (int)$thread['participant_user_id'];
                    break;
                }
            }
        }

        foreach ($threads as $thread) {
            if (!empty($thread['participant_user_id']) && $thread['participant_user_id'] === $participantId) {
                $selectedParticipant = $thread;
                break;
            }
        }
    } else {
        $participantId = $selectedEvent['created_by'];
        $selectedParticipant = [
            'id' => $participantId,
            'name' => $selectedEvent['organizer_name'] ?? 'Organizer',
            'email' => '',
            'unread_count' => 0,
        ];
    }
}

if ($selectedEvent && $participantId) {
    $otherUserId = $participantId;
    try {
        $conversationMessages = getEventThreadMessages($pdo, $eventId, $uid, $otherUserId);
        if (!$fetchThread) {
            markThreadAsRead($pdo, $eventId, $uid, $otherUserId);
        }
    } catch (PDOException $e) {
        $conversationMessages = [];
    }
} elseif ($isAdmin && $participantId) {
    $selectedEventTitle = 'System Messages';
    $otherUserId = $participantId;
    try {
        $conversationMessages = getEventThreadMessages($pdo, null, $uid, $otherUserId);
        if (!$fetchThread) {
            markThreadAsRead($pdo, null, $uid, $otherUserId);
        }
    } catch (PDOException $e) {
        $conversationMessages = [];
    }
    try {
        $pStmt = $pdo->prepare('SELECT id AS participant_user_id, name AS participant_name, email AS participant_email FROM users WHERE id = :id LIMIT 1');
        $pStmt->execute(['id' => $participantId]);
        $pRow = $pStmt->fetch();
        if ($pRow) {
            $selectedParticipant = $pRow;
        }
    } catch (PDOException $e) {
        // ignore
    }
} elseif ($isOrganizer && $participantId) {
    $selectedEventTitle = 'System Messages';
    $otherUserId = $participantId;
    try {
        $adminCheck = $pdo->prepare('SELECT id FROM users WHERE id = :user_id AND role = :role LIMIT 1');
        $adminCheck->execute(['user_id' => $otherUserId, 'role' => 'admin']);
        if ($adminCheck->fetch()) {
            $conversationMessages = getEventThreadMessages($pdo, null, $uid, $otherUserId);
            if (!$fetchThread) {
                markThreadAsRead($pdo, null, $uid, $otherUserId);
            }
            $pStmt = $pdo->prepare('SELECT id AS participant_user_id, name AS participant_name, email AS participant_email FROM users WHERE id = :id LIMIT 1');
            $pStmt->execute(['id' => $participantId]);
            $pRow = $pStmt->fetch();
            if ($pRow) {
                $selectedParticipant = $pRow;
            }
        }
    } catch (PDOException $e) {
        // ignore
    }
}

if ($fetchThread) {
    $payload = [
        'messages' => [],
        'error' => null,
    ];
    if (!$participantId) {
        $payload['error'] = 'Thread not found.';
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }
    try {
        markThreadAsRead($pdo, $eventId, $uid, $participantId);
        $payload['messages'] = getEventThreadMessages($pdo, $eventId, $uid, $participantId);
    } catch (PDOException $e) {
        $payload['error'] = 'Unable to refresh messages.';
    }
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

$conversationArchived = false;
if ($participantId && userCanAccessConversation($pdo, $uid, $eventId, $participantId, $isOrganizer, $isAdmin)) {
    $conversationArchived = isConversationArchived($pdo, $eventId, $uid, $participantId);
}

$eventUnread = [];
$eventAttendeeCount = [];
if (!empty($events)) {
    $ids = implode(',', array_map('intval', array_column($events, 'id')));
    try {
        $unreadStmt = $pdo->prepare(
            'SELECT event_id, COUNT(*) AS unread_count
             FROM messages
             WHERE is_read = 0 AND to_user_id = :user_id AND event_id IN (' . $ids . ')
             GROUP BY event_id'
        );
        $unreadStmt->execute(['user_id' => $uid]);
        foreach ($unreadStmt->fetchAll() as $row) {
            $eventUnread[$row['event_id']] = $row['unread_count'];
        }
    } catch (PDOException $e) {
        $eventUnread = [];
    }
    try {
        $attendeeStmt = $pdo->prepare(
            'SELECT event_id, COUNT(*) AS attendee_count
             FROM attendees
             WHERE event_id IN (' . $ids . ')
             GROUP BY event_id'
        );
        $attendeeStmt->execute();
        foreach ($attendeeStmt->fetchAll() as $row) {
            $eventAttendeeCount[$row['event_id']] = $row['attendee_count'];
        }
    } catch (PDOException $e) {
        $eventAttendeeCount = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Messages</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="page-messages">
<?php include __DIR__ . '/header.php'; ?>
<?php
$contactName = $selectedParticipant['participant_name'] ?? ($selectedParticipant['name'] ?? 'Organizer');
$hasConversation = (bool)$participantId && ($selectedEvent || $isAdmin || $selectedEventTitle);
$listBackUrl = 'messages.php';
if ($eventId !== null) {
    $listBackUrl .= '?event_id=' . (int)$eventId;
}
?>
<main class="container chat-page">
    <section class="panel chat-intro">
        <h2>Message Center</h2>
        <p>Chat directly with event organizers and manage your event conversations.</p>
    </section>

    <div class="chat-app<?= $hasConversation ? ' has-conversation' : '' ?>" id="chatApp">
        <aside class="chat-sidebar panel">
            <h3>Event chats</h3>
            <div class="chat-sidebar-scroll">
            <?php if ($isAdmin): ?>
                <h4>All users</h4>
                <ul class="chat-thread-list">
                    <?php
                    try {
                        $allUsersStmt = $pdo->prepare(
                            'SELECT u.id AS participant_user_id, u.name AS participant_name, u.email AS participant_email,
                                    COALESCE(SUM(CASE WHEN m.to_user_id = :user_id1 AND m.is_read = 0 AND (m.event_id IS NULL OR m.event_id = 0) THEN 1 ELSE 0 END), 0) AS unread_count,
                                    MAX(m.created_at) AS last_at
                             FROM users u
                             LEFT JOIN messages m ON m.to_user_id = :user_id2 AND m.from_user_id = u.id AND (m.event_id IS NULL OR m.event_id = 0)
                             WHERE u.id != :user_id3
                             GROUP BY u.id, u.name, u.email
                             ORDER BY unread_count DESC, last_at DESC, u.name ASC'
                        );
                        $allUsersStmt->execute(['user_id1' => $uid, 'user_id2' => $uid, 'user_id3' => $uid]);
                        $allUsers = $allUsersStmt->fetchAll();
                    } catch (PDOException $e) {
                        $allUsers = [];
                    }
                    if (empty($allUsers)): ?>
                        <li class="chat-thread-empty">No users found.</li>
                    <?php else: ?>
                        <?php foreach ($allUsers as $au): ?>
                            <li class="chat-thread-item<?= (!empty($au['participant_user_id']) && $au['participant_user_id'] === $participantId) ? ' active' : '' ?>">
                                <a href="messages.php?participant_id=<?= escape($au['participant_user_id']) ?>">
                                    <?= escape($au['participant_name']) ?>
                                    <?php if (!empty($au['unread_count'])): ?>
                                        <span class="badge small"><?= escape($au['unread_count']) ?></span>
                                    <?php endif; ?>
                                </a>
                                <div class="chat-event-meta" style="margin-top:0.25rem;color:#64748b;font-size:0.85rem;"><?= escape($au['participant_email']) ?></div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            <?php elseif ($isOrganizer): ?>
                <h4>System Admins</h4>
                <ul class="chat-thread-list">
                    <?php
                    try {
                        $adminsStmt = $pdo->prepare('SELECT id AS participant_user_id, name AS participant_name, email AS participant_email FROM users WHERE role = :role AND id != :user_id ORDER BY name');
                        $adminsStmt->execute(['role' => 'admin', 'user_id' => $uid]);
                        $adminUsers = $adminsStmt->fetchAll();
                    } catch (PDOException $e) {
                        $adminUsers = [];
                    }
                    if (empty($adminUsers)): ?>
                        <li class="chat-thread-empty">No admins found.</li>
                    <?php else: ?>
                        <?php foreach ($adminUsers as $admin): ?>
                            <li class="chat-thread-item<?= (!empty($admin['participant_user_id']) && $admin['participant_user_id'] === $participantId) ? ' active' : '' ?>">
                                <a href="messages.php?participant_id=<?= escape($admin['participant_user_id']) ?>">
                                    <?= escape($admin['participant_name']) ?>
                                </a>
                                <div class="chat-event-meta" style="margin-top:0.25rem;color:#64748b;font-size:0.85rem;"><?= escape($admin['participant_email']) ?></div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>

            <?php if (empty($events)): ?>
                <p>No event chats available yet.</p>
            <?php else: ?>
                <ul class="chat-event-list">
                    <?php foreach ($events as $event): ?>
                        <li class="chat-event-item<?= $event['id'] === $eventId ? ' active' : '' ?>">
                            <a href="messages.php?event_id=<?= escape($event['id']) ?><?= $participantId ? '&participant_id=' . escape($participantId) : '' ?>">
                                <strong><?= escape($event['title']) ?></strong>
                                <?php if (!empty($eventAttendeeCount[$event['id']])): ?>
                                    <span class="badge" style="background:#3498db;" title="Registered participants"><?= escape($eventAttendeeCount[$event['id']]) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($eventUnread[$event['id']])): ?>
                                    <span class="badge"><?= escape($eventUnread[$event['id']]) ?></span>
                                <?php endif; ?>
                            </a>
                            <div class="chat-event-meta"><?= escape(date('M j, Y', strtotime($event['event_date'] ?? 'now'))) ?></div>
                            <?php if ($isOrganizer || $isAdmin): ?>
                                <ul class="chat-thread-list">
                                    <?php if (empty($threads)): ?>
                                        <li class="chat-thread-empty">No registrations yet for this event.</li>
                                    <?php else: ?>
                                        <?php foreach ($threads as $thread): ?>
                                            <?php $isActive = !empty($thread['participant_user_id']) && $thread['participant_user_id'] === $participantId; ?>
                                            <li class="chat-thread-item<?= $isActive ? ' active' : '' ?><?= empty($thread['participant_user_id']) ? ' disabled' : '' ?>">
                                                <?php if (!empty($thread['participant_user_id'])): ?>
                                                    <a href="messages.php?event_id=<?= escape($event['id']) ?>&participant_id=<?= escape($thread['participant_user_id']) ?>">
                                                <?php else: ?>
                                                    <span>
                                                <?php endif; ?>
                                                    <?= escape($thread['participant_name']) ?>
                                                    <?php if (!empty($thread['unread_count'])): ?>
                                                        <span class="badge small"><?= escape($thread['unread_count']) ?></span>
                                                    <?php endif; ?>
                                                    <?php if (empty($thread['participant_user_id'])): ?>
                                                        <small style="color:#94a3b8; margin-left:0.4rem;">No user account</small>
                                                    <?php endif; ?>
                                                <?php if (!empty($thread['participant_user_id'])): ?>
                                                    </a>
                                                <?php else: ?>
                                                    </span>
                                                <?php endif; ?>
                                                <div class="chat-event-meta" style="margin-top:0.35rem; font-size:0.85rem; color:#475569;">
                                                    <?= escape($thread['participant_email']) ?>
                                                    <?= $thread['rsvp_status'] ? '· ' . escape(ucfirst($thread['rsvp_status'])) : '' ?>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            </div>
        </aside>

        <section class="chat-panel panel" aria-label="Conversation">
            <?php if (!$selectedEvent && !$isAdmin && !$selectedEventTitle): ?>
                <div class="chat-empty-state notice">Select an event chat to view your conversation.</div>
            <?php elseif (!$participantId): ?>
                <div class="chat-empty-state notice">Select a participant thread to begin messaging.</div>
            <?php else: ?>
                <header class="chat-topbar">
                    <button type="button" class="chat-back" id="chatBackBtn" aria-label="Back to conversations" data-list-url="<?= escape($listBackUrl) ?>">
                        <span aria-hidden="true">&#8592;</span>
                    </button>
                    <div class="chat-topbar-info">
                        <h2 class="chat-topbar-name"><?= escape($contactName) ?></h2>
                        <p class="chat-topbar-sub">
                            <?= escape($selectedEventTitle ?: ($isAdmin ? 'System Messages' : '')) ?>
                            <?php if ($conversationArchived): ?>
                                <span class="badge small chat-archived-badge">Archived</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <details class="chat-topbar-menu">
                        <summary aria-label="Conversation options">&#8942;</summary>
                        <div class="chat-topbar-dropdown">
                            <form method="post" action="messages.php">
                                <input type="hidden" name="event_id" value="<?= escape($eventId ?? '') ?>">
                                <input type="hidden" name="participant_id" value="<?= escape($participantId) ?>">
                                <button type="submit" name="<?= $conversationArchived ? 'unarchive_conversation' : 'archive_conversation' ?>" class="button secondary small"><?= $conversationArchived ? 'Unarchive' : 'Archive' ?></button>
                            </form>
                            <form method="post" action="messages.php" onsubmit="return confirm('Delete this conversation? This cannot be undone.');">
                                <input type="hidden" name="event_id" value="<?= escape($eventId ?? '') ?>">
                                <input type="hidden" name="participant_id" value="<?= escape($participantId) ?>">
                                <button type="submit" name="delete_conversation" class="button danger small">Delete</button>
                            </form>
                        </div>
                    </details>
                </header>

                <?php if ($errors): ?>
                    <div class="chat-error notice error"><?= escape(implode(' ', $errors)) ?></div>
                <?php endif; ?>

                <div class="chat-body">
                    <div class="chat-messages" id="chatMessages">
                        <?php if (empty($conversationMessages)): ?>
                            <p class="chat-empty">No messages yet. Say hello!</p>
                        <?php else: ?>
                            <?php foreach ($conversationMessages as $message): ?>
                                <div class="chat-message<?= $message['from_user_id'] === $uid ? ' from-me' : ' from-them' ?>">
                                    <div class="chat-bubble">
                                        <?php if ($message['from_user_id'] !== $uid): ?>
                                            <span class="chat-sender"><?= escape($message['from_name'] ?? 'Unknown') ?></span>
                                        <?php endif; ?>
                                        <div class="chat-message-body"><?= nl2br(escape($message['message'])) ?></div>
                                        <?php if (!empty($message['photo_url'])): ?>
                                            <div class="chat-message-photo">
                                                <img src="<?= escape($message['photo_url']) ?>" alt="Attached photo">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <time class="chat-message-time" datetime="<?= escape(date('c', strtotime($message['created_at']))) ?>">
                                        <?= escape(date('M j, g:i A', strtotime($message['created_at']))) ?>
                                    </time>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <form class="chat-composer" method="post" action="messages.php" enctype="multipart/form-data" id="chatComposer">
                    <input type="hidden" name="event_id" value="<?= escape($eventId ?? '') ?>">
                    <input type="hidden" name="participant_id" value="<?= escape($participantId) ?>">
                    <div class="chat-composer-row">
                        <label class="chat-attach-btn" for="photo" title="Attach photo">
                            <span aria-hidden="true">+</span>
                            <span class="visually-hidden">Attach photo</span>
                        </label>
                        <input type="file" id="photo" name="photo" accept="image/*" class="chat-photo-input">
                        <textarea name="message" rows="1" placeholder="Message" aria-label="Message"></textarea>
                        <button type="submit" class="chat-send-btn" aria-label="Send message">
                            <span aria-hidden="true">&#10148;</span>
                        </button>
                    </div>
                    <p class="chat-photo-name" id="chatPhotoName" hidden></p>
                </form>
            <?php endif; ?>
        </section>
    </div>
</main>

<script>
    (function() {
        const eventId = <?= json_encode($eventId) ?>;
        const participantId = <?= json_encode($participantId) ?>;
        const currentUserId = <?= json_encode($uid) ?>;
        const chatApp = document.getElementById('chatApp');
        const chatContainer = document.getElementById('chatMessages');
        const chatComposer = document.getElementById('chatComposer');
        const chatBackBtn = document.getElementById('chatBackBtn');
        const photoInput = document.getElementById('photo');
        const photoName = document.getElementById('chatPhotoName');
        const mobileQuery = window.matchMedia('(max-width: 768px)');

        function formatMessageTime(raw) {
            const date = new Date(raw.replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) {
                return raw;
            }
            return date.toLocaleString(undefined, {
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit'
            });
        }

        function scrollToLatest() {
            if (!chatContainer) {
                return;
            }
            chatContainer.scrollTo({
                top: chatContainer.scrollHeight,
                behavior: 'smooth'
            });
        }

        function showListView() {
            if (chatApp) {
                chatApp.classList.add('chat-view-list');
            }
        }

        function showConversationView() {
            if (chatApp) {
                chatApp.classList.remove('chat-view-list');
            }
        }

        if (chatBackBtn && chatApp) {
            chatBackBtn.addEventListener('click', function() {
                if (mobileQuery.matches) {
                    showListView();
                    return;
                }
                const listUrl = chatBackBtn.getAttribute('data-list-url');
                if (listUrl) {
                    window.location.href = listUrl;
                }
            });
        }

        document.querySelectorAll('.chat-sidebar a').forEach(function(link) {
            link.addEventListener('click', function() {
                if (mobileQuery.matches) {
                    showConversationView();
                }
            });
        });

        if (photoInput && photoName) {
            photoInput.addEventListener('change', function() {
                if (photoInput.files && photoInput.files[0]) {
                    photoName.textContent = photoInput.files[0].name;
                    photoName.hidden = false;
                } else {
                    photoName.textContent = '';
                    photoName.hidden = true;
                }
            });
        }

        if (chatComposer) {
            const messageField = chatComposer.querySelector('textarea[name="message"]');
            if (messageField) {
                messageField.addEventListener('keydown', function(event) {
                    if (event.key === 'Enter' && !event.shiftKey) {
                        event.preventDefault();
                        if (messageField.value.trim() !== '' || (photoInput && photoInput.files && photoInput.files.length)) {
                            chatComposer.requestSubmit();
                        }
                    }
                });
                messageField.addEventListener('input', function() {
                    messageField.style.height = 'auto';
                    messageField.style.height = Math.min(messageField.scrollHeight, 120) + 'px';
                });
            }
        }

        if (!participantId || !chatContainer) {
            return;
        }

        function renderMessages(messages) {
            chatContainer.innerHTML = '';
            if (!Array.isArray(messages) || messages.length === 0) {
                const empty = document.createElement('p');
                empty.className = 'chat-empty';
                empty.textContent = 'No messages yet. Say hello!';
                chatContainer.appendChild(empty);
                scrollToLatest();
                return;
            }

            messages.forEach(function(message) {
                const isMe = Number(message.from_user_id) === Number(currentUserId);
                const item = document.createElement('div');
                item.className = 'chat-message ' + (isMe ? 'from-me' : 'from-them');

                const bubble = document.createElement('div');
                bubble.className = 'chat-bubble';

                if (!isMe) {
                    const sender = document.createElement('span');
                    sender.className = 'chat-sender';
                    sender.textContent = message.from_name || 'Unknown';
                    bubble.appendChild(sender);
                }

                const body = document.createElement('div');
                body.className = 'chat-message-body';
                body.textContent = message.message;
                bubble.appendChild(body);

                if (message.photo_url) {
                    const photoWrap = document.createElement('div');
                    photoWrap.className = 'chat-message-photo';
                    const img = document.createElement('img');
                    img.src = message.photo_url;
                    img.alt = 'Attached photo';
                    photoWrap.appendChild(img);
                    bubble.appendChild(photoWrap);
                }

                item.appendChild(bubble);

                const time = document.createElement('time');
                time.className = 'chat-message-time';
                time.dateTime = message.created_at;
                time.textContent = formatMessageTime(message.created_at);
                item.appendChild(time);

                chatContainer.appendChild(item);
            });
            scrollToLatest();
        }

        async function fetchThread() {
            try {
                let url = 'messages.php?fetch_thread=1&participant_id=' + encodeURIComponent(participantId);
                if (eventId) {
                    url += '&event_id=' + encodeURIComponent(eventId);
                }
                const response = await fetch(url);
                const payload = await response.json();
                if (payload.messages) {
                    renderMessages(payload.messages);
                }
            } catch (error) {
                console.error('Unable to refresh messages:', error);
            }
        }

        scrollToLatest();
        window.addEventListener('load', scrollToLatest);
        setInterval(fetchThread, 5000);
    })();
</script>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>