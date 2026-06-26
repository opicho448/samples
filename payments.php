<?php
require_once 'helpers.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST request required.']);
    exit;
}

$attendeeId = isset($_POST['attendee_id']) ? (int)$_POST['attendee_id'] : 0;
$gateway = trim($_POST['gateway'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;

if ($attendeeId <= 0 || $gateway === '' || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment request.']);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM attendees WHERE id = :id');
$stmt->execute(['id' => $attendeeId]);
$attendee = $stmt->fetch();
if (!$attendee) {
    echo json_encode(['success' => false, 'message' => 'Attendee not found.']);
    exit;
}

$transactionId = strtoupper($gateway) . '_' . uniqid();
$status = 'pending';
if (strtolower($gateway) !== 'm-pesa') {
    $status = 'completed';
}

$paymentStmt = $pdo->prepare('INSERT INTO payments (attendee_id, event_id, amount, currency, gateway, status, transaction_id, meta) VALUES (:attendee_id, :event_id, :amount, :currency, :gateway, :status, :transaction_id, :meta)');
$paymentStmt->execute([
    'attendee_id' => $attendeeId,
    'event_id' => $attendee['event_id'],
    'amount' => $amount,
    'currency' => 'KES',
    'gateway' => strtoupper($gateway),
    'status' => $status,
    'transaction_id' => $transactionId,
    'meta' => json_encode(['phone' => $phone]),
]);

$ticketCode = $attendee['ticket_code'] ?? null;
if ($status === 'pending') {
    $message = "The M-PESA payment request has been sent to $phone. Please complete the prompt on your phone.";
} else {
    $message = "Payment recorded successfully.";
    if ($ticketCode) {
        $message .= " Your ticket code is $ticketCode.";
    }
}

$response = ['success' => true, 'message' => $message, 'transaction_id' => $transactionId];
if ($ticketCode) {
    $response['ticket_code'] = $ticketCode;
}

echo json_encode($response);
