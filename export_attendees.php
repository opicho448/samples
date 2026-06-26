<?php
require_once 'db.php';
$stmt = $pdo->query('SELECT a.id, e.title AS event_title, a.name, a.email, a.phone, a.ticket_type, a.ticket_code, a.payment_method, a.payment_status, a.registration_status, a.mpesa_transaction_id, a.amount_paid, a.registered_at FROM attendees a LEFT JOIN events e ON a.event_id = e.id ORDER BY a.registered_at DESC');
$attendees = $stmt->fetchAll();
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="attendees.csv"');
$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'Event', 'Name', 'Email', 'Phone', 'Ticket Type', 'Ticket Code', 'Payment Method', 'Payment Status', 'Registration Status', 'M-Pesa ID', 'Amount Paid', 'Registered At']);
foreach ($attendees as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit;
?>