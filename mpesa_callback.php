<?php
require_once 'helpers.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST request required.']);
    exit;
}

$transactionId = trim($_POST['transaction_id'] ?? '');
$status = trim($_POST['status'] ?? 'completed');

if ($transactionId === '') {
    echo json_encode(['success' => false, 'message' => 'Missing transaction_id.']);
    exit;
}

$paymentQuery = $pdo->prepare('SELECT * FROM payments WHERE transaction_id = :transaction_id');
$paymentQuery->execute(['transaction_id' => $transactionId]);
$paymentRecord = $paymentQuery->fetch();
if (!$paymentRecord) {
    echo json_encode(['success' => false, 'message' => 'Payment record not found.']);
    exit;
}

$updateStmt = $pdo->prepare('UPDATE payments SET status = :status WHERE id = :id');
$updateStmt->execute(['status' => $status, 'id' => $paymentRecord['id']]);

$attendeeStmt = $pdo->prepare('SELECT * FROM attendees WHERE id = :id');
$attendeeStmt->execute(['id' => $paymentRecord['attendee_id']]);
$attendee = $attendeeStmt->fetch();
if ($attendee) {
    $paymentStatus = $status === 'completed' ? 'Paid' : 'Pending';
    $attendeeUpdate = $pdo->prepare('UPDATE attendees SET payment_status = :payment_status WHERE id = :id');
    $attendeeUpdate->execute(['payment_status' => $paymentStatus, 'id' => $attendee['id']]);
    $messageBody = "<p>Your payment status for ticket " . escape($attendee['ticket_type']) . " is now <strong>" . escape($paymentStatus) . "</strong>.</p>";
    if (($attendee['registration_status'] ?? '') === 'Confirmed' && !empty($attendee['ticket_code'])) {
        $messageBody .= "<p>Your ticket code is <strong>" . escape($attendee['ticket_code']) . "</strong>.</p>";
    } else {
        $messageBody .= "<p>Your registration is still pending admin approval. You will receive your ticket number once payment is verified.</p>";
    }
    sendNotification($attendee['email'], 'Payment update', $messageBody);
}

echo json_encode(['success' => true, 'message' => 'Payment status updated.', 'ticket_code' => $attendee['ticket_code'] ?? null]);
