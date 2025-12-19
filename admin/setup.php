<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../src/App/Database.php';
use App\Database;

// DB-Konfiguration laden
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$dbCfg = $ini['database'] ?? [];
$error = '';
$success = '';

try {
    $db  = new Database($dbCfg);
    $pdo = $db->pdo();
} catch (Throwable $e) {
    $error = 'Datenbankverbindung fehlgeschlagen. Bitte Installer erneut ausführen.';
    $pdo = null;
}

// CSRF-Token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $token = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($csrf, $token)) {
        $error = 'Ungültiges Formular-Token.';
    } else {
        $username = 'admin';
        $password = (string)($_POST['password'] ?? '');
        $confirm  = (string)($_POST['confirm'] ?? '');

        // Einfache Validierung
        if (strlen($password) < 8) {
            $error = 'Passwort muss mindestens 8 Zeichen lang sein.';
        } elseif ($password !== $confirm) {
            $error = 'Passwörter stimmen nicht überein.';
        } else {
            // Admin existiert?
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            $hash = password_hash($password, PASSWORD_BCRYPT);

            if ($user) {
                $upd = $pdo->prepare('UPDATE users SET password_hash = ?, email = COALESCE(email,"admin@example.com"), role = COALESCE(role,"admin") WHERE username = ?');
                $upd->execute([$hash, $username]);
            } else {
                $ins = $pdo->prepare('INSERT INTO users (username, password_hash, email, role) VALUES (?, ?, ?, ?)');
                $ins->execute([$username, $hash, 'admin@example.com', 'admin']);
            }

            // first_run.flag entfernen
            $flag = __DIR__ . '/../config/first_run.flag';
            if (is_file($flag)) { @unlink($flag); }

            // Direkt einloggen und weiterleiten
            $_SESSION['admin'] = [
                'id' => (int)($user['id'] ?? 0),
                'username' => $username,
                'role' => 'admin',
                'source' => 'setup',
            ];
            session_regenerate_id(true);

            header('Location: /admin/');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>Admin Setup – Passwort festlegen</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/admin/assets/styles/admin.css" rel="stylesheet">
  <style>
    .card{max-width:480px;margin:60px auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px}
    .input{display:block;width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:10px}
    .btn{display:inline-block;padding:10px 14px;border-radius:8px;background:#1877f2;color:#fff;border:0}
    .error{color:#b91c1c;margin-bottom:12px}
    .success{color:#166534;margin-bottom:12px}
  </style>
</head>
<body>
  <div class="card">
    <h1>Admin-Passwort festlegen</h1>
    <p>Lege ein neues Passwort für den Benutzer <strong>admin</strong> fest.</p>
    <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <form method="post" action="/admin/setup.php" autocomplete="off">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
      <label>Neues Passwort</label>
      <input class="input" type="password" name="password" required minlength="8" placeholder="Mindestens 8 Zeichen">
      <label>Passwort bestätigen</label>
      <input class="input" type="password" name="confirm" required minlength="8" placeholder="Wiederholen">
      <button class="btn" type="submit">Speichern & Weiter</button>
    </form>
    <p style="margin-top:10px"><a href="/admin/login.php">Zurück zum Login</a></p>
  </div>
</body>
</html>
