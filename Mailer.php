<?php

class Mailer {
    private $host;
    private $port;
    private $username;
    private $password;
    private $from;
    private $fromName;

    public function __construct() {
        $this->host = getenv('MAIL_HOST') ?: 'localhost';
        $this->port = getenv('MAIL_PORT') ?: 587;
        $this->username = getenv('MAIL_USER') ?: '';
        $this->password = getenv('MAIL_PASS') ?: '';
        $this->from = getenv('MAIL_FROM') ?: 'noreply@localhost';
        $this->fromName = getenv('MAIL_FROM_NAME') ?: 'App';
    }

    public function send($to, $subject, $htmlBody, $textBody = null) {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (empty($this->username) || empty($this->host)) {
            return $this->sendViaPhpMail($to, $subject, $htmlBody);
        }

        return $this->sendViaSMTP($to, $subject, $htmlBody, $textBody);
    }

    private function sendViaPhpMail($to, $subject, $htmlBody) {
        $headers = "From: {$this->fromName} <{$this->from}>\r\n";
        $headers .= "Reply-To: {$this->from}\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

        return @mail($to, $subject, $htmlBody, $headers);
    }

    private function sendViaSMTP($to, $subject, $htmlBody, $textBody = null) {
        $textBody = $textBody ?: strip_tags($htmlBody);

        $boundary = md5(time() . rand());

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $textBody . "\r\n\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $htmlBody . "\r\n\r\n";

        $body .= "--{$boundary}--\r\n";

        $headers = "From: {$this->fromName} <{$this->from}>\r\n";
        $headers .= "Reply-To: {$this->from}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

        try {
            $socket = $this->createSocket();
            if (!$socket) {
                return false;
            }

            $this->sendCommand($socket, "", 220);

            $hostname = php_uname('n') ?: 'localhost';
            $this->sendCommand($socket, "EHLO {$hostname}\r\n", 250);

            if ($this->port == 587) {
                $this->sendCommand($socket, "STARTTLS\r\n", 220);
                stream_context_set_option($socket, 'ssl', 'allow_self_signed', true);
                stream_context_set_option($socket, 'ssl', 'verify_peer', false);
                @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->sendCommand($socket, "EHLO {$hostname}\r\n", 250);
            }

            $this->sendCommand($socket, "AUTH LOGIN\r\n", 334);
            $this->sendCommand($socket, base64_encode($this->username) . "\r\n", 334);
            $this->sendCommand($socket, base64_encode($this->password) . "\r\n", 235);

            $this->sendCommand($socket, "MAIL FROM:<{$this->from}>\r\n", 250);
            $this->sendCommand($socket, "RCPT TO:<{$to}>\r\n", 250);
            $this->sendCommand($socket, "DATA\r\n", 354);

            fwrite($socket, "Subject: {$subject}\r\n{$headers}\r\n{$body}\r\n.\r\n");

            $this->sendCommand($socket, "", 250);
            $this->sendCommand($socket, "QUIT\r\n", 221);

            fclose($socket);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private function createSocket() {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ]);

        $socket = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            throw new Exception("Failed to connect to SMTP server: {$errstr} ({$errno})");
        }

        return $socket;
    }

    private function sendCommand(&$socket, $cmd, $expectedCode) {
        if ($cmd) {
            fwrite($socket, $cmd);
        }

        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }

        $code = (int)substr($response, 0, 3);
        if ($expectedCode && $code !== $expectedCode) {
            throw new Exception("SMTP Error (expected {$expectedCode}, got {$code}): " . trim($response));
        }

        return $response;
    }
}
?>

