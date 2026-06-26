<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'helpers.php';

$testEmail = 'samuelopicho448@gmail.com';
$testSubject = 'Email Setup Test';
$testMessage = '<h2>Email Configuration Test</h2><p>If you receive this email, your SMTP configuration is working correctly!</p>';

echo "Testing email configuration...\n";
echo "MAIL_HOST: " . (getenv('MAIL_HOST') ?: 'not set') . "\n";
echo "MAIL_PORT: " . (getenv('MAIL_PORT') ?: 'not set') . "\n";
echo "MAIL_USER: " . (getenv('MAIL_USER') ?: 'not set') . "\n";
echo "MAIL_FROM: " . (getenv('MAIL_FROM') ?: 'not set') . "\n\n";

try {
    $mailer = new Mailer();
    $result = $mailer->send($testEmail, $testSubject, $testMessage);
    if ($result) {
        echo "✓ Email sent successfully!\n";
    } else {
        echo "✗ Failed to send email\n";
    }
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>
