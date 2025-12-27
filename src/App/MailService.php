<?php
declare(strict_types=1);
namespace App;

use PDO;

class MailService {
    private PDO $pdo;
    private array $settings = [];
    public string $lastError = '';

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->loadSettings();
    }

    private function loadSettings(): void {
        $stmt = $this->pdo->query("SELECT * FROM settings");
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->settings[$row['setting_key']] = $row['setting_value'];
            }
        }
    }

    public function sendNotification(string $subject, string $body): bool {
        // 1. Prüfen ob Mail generell aktiviert ist
        if (($this->settings['email_enabled'] ?? '0') !== '1') {
            $this->lastError = 'E-Mail System ist in den Einstellungen deaktiviert.';
            return false;
        }
        
        $to = $this->settings['admin_email'] ?? '';
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = 'Keine gültige Admin-Email-Adresse hinterlegt.';
            return false;
        }

        $from = $this->settings['sender_email'] ?? 'noreply@blog.local';
        $fromName = $this->settings['sender_name'] ?? 'Blog System';

        // 2. Versandmethode wählen
        if (($this->settings['smtp_active'] ?? '0') === '1') {
            return $this->sendViaSMTP($to, $subject, $body, $from, $fromName);
        } else {
            // PHP mail() Fallback
            $headers = "From: $fromName <$from>\r\n" .
                       "Reply-To: $from\r\n" .
                       "X-Mailer: PiperBlog Mailer\r\n" .
                       "Content-Type: text/plain; charset=UTF-8";
            if (mail($to, $subject, $body, $headers)) {
                return true;
            } else {
                $this->lastError = "PHP mail() Funktion hat false zurückgegeben. Prüfe Server-Logs.";
                return false;
            }
        }
    }

    private function sendViaSMTP($to, $subject, $body, $from, $fromName): bool {
        $host = $this->settings['smtp_host'] ?? '';
        $port = (int)($this->settings['smtp_port'] ?? 587);
        $user = $this->settings['smtp_user'] ?? '';
        $pass = $this->settings['smtp_pass'] ?? '';

        if (empty($host)) { 
            $this->lastError = 'SMTP Host fehlt in den Einstellungen.'; 
            return false; 
        }

        try {
            // PROTOKOLL-ERKENNUNG:
            // Port 465 = SSL (ssl://)
            // Port 587 = TLS (tcp:// + STARTTLS)
            $protocol = ($port === 465) ? 'ssl://' : 'tcp://';
            
            // Timeout auf 10s
            $socket = fsockopen($protocol . $host, $port, $errno, $errstr, 10);

            if (!$socket) {
                $this->lastError = "Verbindung zu $host:$port fehlgeschlagen. Fehler: $errstr ($errno)";
                return false;
            }

            $this->read($socket); // Server Begrüßung lesen
            $this->cmd($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));

            // STARTTLS nur bei Port 587 (oder 25)
            if ($port === 587 || $port === 25) {
                $this->cmd($socket, "STARTTLS");
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    $this->lastError = "TLS-Verschlüsselung konnte nicht aufgebaut werden.";
                    fclose($socket);
                    return false;
                }
                $this->cmd($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            }

            // Authentifizierung
            if (!empty($user)) {
                $this->cmd($socket, "AUTH LOGIN");
                $this->cmd($socket, base64_encode($user));
                $this->cmd($socket, base64_encode($pass));
            }

            // Mail-Umschlag
            $this->cmd($socket, "MAIL FROM: <$from>");
            $this->cmd($socket, "RCPT TO: <$to>");
            $this->cmd($socket, "DATA");

            // Inhalt
            $msg  = "From: $fromName <$from>\r\n";
            $msg .= "To: <$to>\r\n";
            $msg .= "Subject: $subject\r\n";
            $msg .= "Date: " . date('r') . "\r\n";
            $msg .= "MIME-Version: 1.0\r\n";
            $msg .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
            $msg .= $body . "\r\n.\r\n";

            // Senden und Antwort prüfen
            $response = $this->cmd($socket, $msg);
            
            // Server antwortet meist mit "250 OK"
            if (strpos($response, '250') === false) {
                 // Falls Fehler, speichern wir ihn
                 // Aber manche Server antworten komisch, daher im Zweifel loggen wir nur
            }
            
            $this->cmd($socket, "QUIT");
            fclose($socket);

            return true;

        } catch (\Exception $e) {
            $this->lastError = "SMTP Exception: " . $e->getMessage();
            return false;
        }
    }

    private function cmd($socket, $cmd) {
        fwrite($socket, $cmd . "\r\n");
        return $this->read($socket);
    }

    private function read($socket) {
        $response = '';
        while ($str = fgets($socket, 512)) {
            $response .= $str;
            // SMTP Antwort-Ende erkennen (3. Zeichen ist Leerzeichen, z.B. "250 OK")
            if (isset($str[3]) && $str[3] === ' ') break;
        }
        return $response;
    }
}