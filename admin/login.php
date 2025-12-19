<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../src/App/Database.php';
use App\Database;

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$dbCfg = $ini['database'] ?? [];

$pdo = null;
try {
    if ($dbCfg) {
        $db  = new Database($dbCfg);
        $pdo = $db->pdo();
    }
} catch (Throwable $e) {
    // Wenn die DB nicht erreichbar ist, erzwingen wir Setup.
}

// Setup-Redirect: bei erstem Start (Flag) oder wenn kein Admin in der DB existiert
$flag = __DIR__ . '/../config/first_run.flag';
$needsSetup = is_file($flag);
if (!$needsSetup && $pdo) {
    try {
        $countAdmin = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE username='admin'")->fetchColumn();
        if ($countAdmin === 0) { $needsSetup = true; }
    } catch (Throwable $e) {
        $needsSetup = true;
    }
}
if ($needsSetup) {
    header('Location: /admin/setup.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $stmt = $pdo->prepare('SELECT id, username, role, password_hash FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin'] = [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'role' => $user['role'] ?? 'admin',
            'source' => 'db',
        ];
        header('Location: /admin/');
        exit;
    } else {
        $error = 'Ungültige Zugangsdaten';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>Login – PiperBlog Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/admin/assets/styles/admin.css" rel="stylesheet">
  <style>
    .login-card{max-width:420px;margin:60px auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px}
    .input{display:block;width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:10px}
    .btn{display:inline-block;padding:10px 14px;border-radius:8px;background:#1877f2;color:#fff;border:0}
    .error{color:#b91c1c;margin-bottom:12px}
  </style>
</head>
<body>
  <div class="login-card">
    <h1>Admin Login</h1>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" action="/admin/login.php">
      <input class="input" type="text" name="username" placeholder="Benutzername" required>
      <input class="input" type="password" name="password" placeholder="Passwort" required>
      <button class="btn" type="submit">Login</button>
    </form>
    <p style="margin-top:10px"><a href="/admin/setup.php">Passwort neu setzen</a></p>
  </div>
</body>
</html>
