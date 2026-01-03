<?php
declare(strict_types=1);
namespace App;

use PDO;

class MailService {
    private PDO $pdo;
    private array $settings = [];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->loadSettings();
    }

    private function loadSettings(): void {
        $stmt = $this->pdo->query("SELECT * FROM settings");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    public function sendNotification(string $subject, string $body): bool {
        if (($this->settings['email_enabled'] ?? '0') !== '1') return false;
        
        $to = $this->settings['admin_email'] ?? '';
        $from = $this->settings['sender_email'] ?? 'noreply@blog.local';
        $fromName = $this->settings['sender_name'] ?? 'Blog System';

        if (($this->settings['smtp_active'] ?? '0') === '1') {
            return $this->sendViaSMTP($to, $subject, $body, $from, $fromName);
        } else {
            // Fallback auf PHP mail()
            $headers = "From: $fromName <$from>\r\n" .
                       "Reply-To: $from\r\n" .
                       "X-Mailer: PHP/" . phpversion() . "\r\n" .
                       "Content-Type: text/plain; charset=UTF-8";
            return mail($to, $subject, $body, $headers);
        }
    }

    private function sendViaSMTP($to, $subject, $body, $from, $fromName): bool {
        $host = $this->settings['smtp_host'] ?? '';
        $port = (int)($this->settings['smtp_port'] ?? 587);
        $user = $this->settings['smtp_user'] ?? '';
        $pass = $this->settings['smtp_pass'] ?? '';

        try {
            $socket = fsockopen($host, $port, $errno, $errstr, 10);
            if (!$socket) return false;

            $this->serverCmd($socket, "EHLO " . $_SERVER['SERVER_NAME']);
            
            // STARTTLS wenn nötig (bei Port 587 üblich)
            if ($port === 587) {
                $this->serverCmd($socket, "STARTTLS");
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->serverCmd($socket, "EHLO " . $_SERVER['SERVER_NAME']);
            }

            // Auth
            if (!empty($user)) {
                $this->serverCmd($socket, "AUTH LOGIN");
                $this->serverCmd($socket, base64_encode($user));
                $this->serverCmd($socket, base64_encode($pass));
            }

            $this->serverCmd($socket, "MAIL FROM: <$from>");
            $this->serverCmd($socket, "RCPT TO: <$to>");
            $this->serverCmd($socket, "DATA");

            $msg  = "From: $fromName <$from>\r\n";
            $msg .= "To: <$to>\r\n";
            $msg .= "Subject: $subject\r\n";
            $msg .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
            $msg .= $body . "\r\n.\r\n";

            fwrite($socket, $msg);
            $response = fgets($socket, 512);
            
            $this->serverCmd($socket, "QUIT");
            fclose($socket);

            return true;
        } catch (\Exception $e) {
            error_log("SMTP Error: " . $e->getMessage());
            return false;
        }
    }

    private function serverCmd($socket, $cmd) {
        fwrite($socket, $cmd . "\r\n");
        return fgets($socket, 512);
    }
}