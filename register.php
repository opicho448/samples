<?php
require_once 'helpers.php';
ensureAttendeesApprovalSchema();
$user = currentUser();
$eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
$stmt = $pdo->prepare('SELECT * FROM events WHERE id = :id');
$stmt->execute(['id' => $eventId]);
$event = $stmt->fetch();
if (!$event) {
    header('Location: index.php');
    exit;
}
$ticketOptions = json_decode($event['ticket_options'], true);
$errors = [];
$success = false;
$amountPaid = 0;
$paymentMethod = 'None';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $ticketType = $_POST['ticket_type'] ?? '';
    $paymentMethod = $_POST['payment_method'] ?? 'None';
    $mpesaTransactionId = trim($_POST['mpesa_transaction_id'] ?? '');

    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if ($ticketType === '') {
        $errors[] = 'Please select a ticket type.';
    }

    $selectedTicket = null;
    foreach ($ticketOptions as &$ticket) {
        if ($ticket['name'] === $ticketType) {
            $selectedTicket = &$ticket;
            break;
        }
    }
    if (!$selectedTicket) {
        $errors[] = 'Selected ticket option is invalid.';
    }
    if ($selectedTicket && $selectedTicket['quantity'] <= 0) {
        $errors[] = 'Selected ticket type is sold out.';
    }

    $amountPaid = $selectedTicket ? (float)$selectedTicket['price'] : 0;
    $requiresPaymentProof = $amountPaid > 0 && $paymentMethod === 'Mobile Money';
    $paymentProofPath = null;

    if ($requiresPaymentProof) {
        if ($mpesaTransactionId === '') {
            $errors[] = 'Please enter your M-Pesa transaction ID.';
        }
        $uploadResult = handlePaymentProofUpload('payment_proof');
        if ($uploadResult === false) {
            $errors[] = 'Payment proof must be a valid image (PNG, JPEG, GIF, or WebP).';
        } elseif ($uploadResult !== null) {
            $paymentProofPath = $uploadResult;
        }
    }

    if (empty($errors)) {
        $paymentStatus = $amountPaid > 0 ? 'Pending' : 'Pending';
        $user = currentUser();
        $stmt = $pdo->prepare(
            'INSERT INTO attendees (event_id, user_id, name, email, phone, ticket_type, ticket_code, payment_method, payment_status, registration_status, mpesa_transaction_id, payment_proof_path, amount_paid) ' .
            'VALUES (:event_id, :user_id, :name, :email, :phone, :ticket_type, :ticket_code, :payment_method, :payment_status, :registration_status, :mpesa_transaction_id, :payment_proof_path, :amount_paid)'
        );
        $stmt->execute([
            'event_id' => $eventId,
            'user_id' => $user ? $user['id'] : null,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'ticket_type' => $ticketType,
            'ticket_code' => null,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'registration_status' => 'Pending',
            'mpesa_transaction_id' => $mpesaTransactionId !== '' ? $mpesaTransactionId : null,
            'payment_proof_path' => $paymentProofPath,
            'amount_paid' => $amountPaid,
        ]);
        $attendeeId = $pdo->lastInsertId();

        if ($amountPaid > 0) {
            $transactionId = $mpesaTransactionId !== '' ? $mpesaTransactionId : ('PENDING_' . $attendeeId);
            $paymentStmt = $pdo->prepare(
                'INSERT INTO payments (attendee_id, event_id, amount, currency, gateway, status, transaction_id, meta) ' .
                'VALUES (:attendee_id, :event_id, :amount, :currency, :gateway, :status, :transaction_id, :meta)'
            );
            $paymentStmt->execute([
                'attendee_id' => $attendeeId,
                'event_id' => $eventId,
                'amount' => $amountPaid,
                'currency' => 'KES',
                'gateway' => 'M-PESA',
                'status' => 'pending',
                'transaction_id' => $transactionId,
                'meta' => json_encode([
                    'phone' => $phone,
                    'mpesa_transaction_id' => $mpesaTransactionId,
                    'payment_proof_path' => $paymentProofPath,
                ]),
            ]);
        }

        $notifyUserId = $user ? $user['id'] : null;
        if (!$notifyUserId) {
            $uStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $uStmt->execute(['email' => $email]);
            $uRow = $uStmt->fetch();
            if ($uRow) {
                $notifyUserId = $uRow['id'];
            }
        }
        sendNotification(
            $email,
            'Registration received – pending approval',
            '<p>Hi ' . escape($name) . ',</p>' .
            '<p>We received your registration for <strong>' . escape($event['title']) . '</strong>.</p>' .
            '<p>Your registration is pending admin approval. You will receive your ticket number once payment is verified.</p>',
            $notifyUserId,
            'registration_pending'
        );
        if (!empty($event['organizer_email']) && filter_var($event['organizer_email'], FILTER_VALIDATE_EMAIL)) {
            sendNotification(
                $event['organizer_email'],
                'New registration pending approval',
                '<p>A new attendee registered for ' . escape($event['title']) . ' and is awaiting payment verification.</p>' .
                '<p>Name: ' . escape($name) . '</p><p>Email: ' . escape($email) . '</p>' .
                ($mpesaTransactionId !== '' ? '<p>M-Pesa ID: ' . escape($mpesaTransactionId) . '</p>' : ''),
                null,
                'manager_notification'
            );
        }
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premier Hotel Registration - <?= escape($event['title']) ?></title>
    <link rel="stylesheet" href="styles.css">
    <script src="script.js" defer></script>
</head>
<body class="page-register">
<?php include __DIR__ . '/header.php'; ?>
<main class="container">
    <section class="form-card">
        <h2><?= escape($event['title']) ?></h2>
        <?php if ($success): ?>
            <div class="notice">
                <h3>Registration submitted</h3>
                <p>Thank you, <?= escape($name) ?>.</p>
                <p><strong>Your registration is pending admin approval.</strong> You will receive your ticket number once payment is verified.</p>
                <?php if ($amountPaid > 0): ?>
                    <p>Payment method: <?= escape($paymentMethod) ?>. Amount: $<?= number_format($amountPaid, 2) ?>.</p>
                <?php else: ?>
                    <p>Ticket type: <?= escape($ticketType) ?> (Free).</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php if ($errors): ?>
                <div class="notice" style="border-left-color:#c0392b;background:#fff1f0;color:#8a1f1f;">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= escape($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="post" action="register.php?event_id=<?= $eventId ?>" enctype="multipart/form-data">
                <label for="name">Full name</label>
                <input type="text" id="name" name="name" value="<?= escape($_POST['name'] ?? '') ?>">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" value="<?= escape($_POST['email'] ?? '') ?>">
                <label for="phone">Phone number</label>
                <input type="tel" id="phone" name="phone" value="<?= escape($_POST['phone'] ?? '') ?>">
                <label for="ticket_type">Ticket type</label>
                <select id="ticket_type" name="ticket_type">
                    <option value="">Select ticket</option>
                    <?php foreach ($ticketOptions as $ticket): ?>
                        <option value="<?= escape($ticket['name']) ?>" data-price="<?= escape($ticket['price']) ?>" <?= (isset($_POST['ticket_type']) && $_POST['ticket_type'] === $ticket['name']) ? 'selected' : '' ?>><?= escape($ticket['name']) ?> - <?= $ticket['price'] > 0 ? '$' . number_format($ticket['price'], 2) : 'Free' ?> (<?= escape($ticket['quantity']) ?> available)</option>
                    <?php endforeach; ?>
                </select>
                <p>Estimated price: <span id="ticket_price"></span></p>
                <label for="payment_method">Payment method</label>
                <select id="payment_method" name="payment_method">
                    <option value="Mobile Money">Mobile Money</option>
                    <option value="PayPal">PayPal</option>
                    <option value="Stripe">Stripe</option>
                    <option value="Credit Card">Credit Card</option>
                    <option value="None">None</option>
                </select>
                <div id="payment_proof_fields">
                    <label for="mpesa_transaction_id">M-Pesa transaction ID</label>
                    <input type="text" id="mpesa_transaction_id" name="mpesa_transaction_id" placeholder="e.g. QGH7X2K9LM" value="<?= escape($_POST['mpesa_transaction_id'] ?? '') ?>">
                    <label for="payment_proof">Payment proof (screenshot, optional)</label>
                    <input type="file" id="payment_proof" name="payment_proof" accept="image/png,image/jpeg,image/gif,image/webp">
                    <p class="form-hint" style="font-size:0.9rem;color:#666;margin-top:0.25rem;">Pay via M-Pesa, then paste your confirmation code or upload a screenshot of the payment message.</p>
                </div>
                <input type="submit" value="Complete registration" class="button">
            </form>
        <?php endif; ?>
    </section>
</main>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
