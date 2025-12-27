<?php
declare(strict_types=1);
session_start();

$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$t_temp = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$tmLang = $t_temp['test_mail'] ?? [];

if (empty($_SESSION['admin'])) {
    die($tmLang['access_denied'] ?? 'Access denied');
}

require_once __DIR__ . '/../src/App/Database.php';
require_once __DIR__ . '/../src/App/MailService.php';

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED);
$db  = new App\Database($ini['database']);
$pdo = $db->pdo();

$mailService = new App\MailService($pdo);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($tmLang['title'] ?? 'Mail Test') ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; padding: 40px; display: flex; justify-content: center; }
        .card { background: white; width: 100%; max-width: 600px; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-top: 4px solid #1877f2; }
        h1 { margin-top: 0; color: #1a202c; font-size: 1.5rem; }
        .success { background: #d1e7dd; color: #0f5132; padding: 15px; border-radius: 6px; border: 1px solid #badbcc; line-height: 1.5; }
        .error { background: #f8d7da; color: #842029; padding: 15px; border-radius: 6px; border: 1px solid #f5c2c7; line-height: 1.5; }
        .btn { display: inline-block; background: #1877f2; color: white; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: bold; margin-top: 25px; transition: 0.2s; }
        .btn:hover { background: #145dbf; }
        pre { background: #2d3748; color: #68d391; padding: 15px; overflow-x: auto; border-radius: 6px; font-size: 13px; margin-top: 10px; }
        p { color: #4a5568; }
    </style>
</head>
<body>
<div class="card">
    <h1><?= htmlspecialchars($tmLang['headline'] ?? 'SMTP Test') ?></h1>
    <p><?= htmlspecialchars($tmLang['trying'] ?? 'Sending...') ?></p>
    
    <?php
    $subject = ($tmLang['subject'] ?? 'Test') . " " . date('H:i:s');
    $body    = ($tmLang['body'] ?? 'Hello') . " " . date('d.m.Y H:i:s');
    
    echo "<div style='margin-top: 20px;'><strong>" . htmlspecialchars($tmLang['status_label'] ?? 'Status:') . "</strong><br><br>";
    
    $success = $mailService->sendNotification($subject, $body);

    if ($success) {
        echo "<div class='success'><strong>" . htmlspecialchars($tmLang['success_title'] ?? 'SUCCESS') . "</strong><br>" . ($tmLang['success_msg'] ?? 'OK') . "</div>";
    } else {
        echo "<div class='error'><strong>" . htmlspecialchars($tmLang['error_title'] ?? 'ERROR') . "</strong><br>" . htmlspecialchars($tmLang['error_msg'] ?? 'Failed') . "<br><hr style='border-color: rgba(0,0,0,0.1);'><strong>" . htmlspecialchars($tmLang['diagnosis'] ?? 'Info:') . "</strong><br><pre>" . htmlspecialchars($mailService->lastError ?? 'Unknown Error') . "</pre></div>";
    }
    echo "</div>";
    ?>

    <br>
    <a href="/admin/settings.php?tab=email" class="btn"><?= htmlspecialchars($tmLang['btn_back'] ?? 'Back') ?></a>
</div>
</body>
</html>