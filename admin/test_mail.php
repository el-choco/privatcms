<?php
// admin/test_mail.php
declare(strict_types=1);
session_start();

// 1. Sicherheit: Nur eingeloggte Admins
if (empty($_SESSION['admin'])) {
    die("Zugriff verweigert. Bitte erst einloggen.");
}

// 2. System laden
require_once __DIR__ . '/../src/App/Database.php';
require_once __DIR__ . '/../src/App/MailService.php';

// 3. Config & DB verbinden
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED);
$db  = new App\Database($ini['database']);
$pdo = $db->pdo();

// 4. Mail Service starten
$mailService = new App\MailService($pdo);

// Layout für die Ausgabe
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Mail Test</title>
    <style>
        body { font-family: -apple-system, sans-serif; background: #f0f2f5; padding: 40px; }
        .card { background: white; max-width: 600px; margin: 0 auto; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; color: #1877f2; }
        .success { background: #d1e7dd; color: #0f5132; padding: 15px; border-radius: 6px; border: 1px solid #badbcc; }
        .error { background: #f8d7da; color: #842029; padding: 15px; border-radius: 6px; border: 1px solid #f5c2c7; }
        .btn { display: inline-block; background: #1877f2; color: white; text-decoration: none; padding: 10px 20px; border-radius: 5px; font-weight: bold; margin-top: 20px;}
        pre { background: #333; color: #0f0; padding: 15px; overflow-x: auto; border-radius: 5px; }
    </style>
</head>
<body>
<div class="card">
    <h1>✉️ SMTP Test-Center</h1>
    <p>Versuche Test-Mail zu senden...</p>
    
    <?php
    // Der eigentliche Test
    $subject = "Test-Mail von PiperBlog";
    $body    = "Hallo!\n\nWenn du das liest, funktioniert dein E-Mail-Versand (SMTP) perfekt.\n\nGesendet am: " . date('d.m.Y H:i:s');
    
    echo "<div><strong>Status:</strong><br>";
    
    $success = $mailService->sendNotification($subject, $body);

    if ($success) {
        echo "<div class='success'>✅ <strong>ERFOLG!</strong><br>Die E-Mail wurde erfolgreich an den Server übergeben.<br>Prüfe jetzt dein Postfach (auch Spam-Ordner).</div>";
    } else {
        echo "<div class='error'>❌ <strong>FEHLER!</strong><br>Der Versand hat nicht geklappt.<br><hr><strong>Diagnose:</strong><br>" . htmlspecialchars($mailService->lastError) . "</div>";
    }
    echo "</div>";
    ?>

    <br>
    <a href="/admin/settings.php" class="btn">Zurück zu den Einstellungen</a>
</div>
</body>
</html>