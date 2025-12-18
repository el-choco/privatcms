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
    // DB optional fürs Login – File-Fallback deckt ab
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $authed = false;

    // 1) File-based auth (config/admin.ini)
    $adminIni = __DIR__ . '/../config/admin.ini';
    if (is_file($adminIni)) {
        $cfg = parse_ini_file($adminIni, true, INI_SCANNER_TYPED);
        if (!empty($cfg['admin']['username']) && !empty($cfg['admin']['password_hash'])) {
            $au = $cfg['admin'];
            if (hash_equals($au['username'], $username) && password_verify($password, $au['password_hash'])) {
                $authed = true;
                $_SESSION['admin'] = [
                    'id' => 0,
                    'username' => $au['username'],
                    'role' => 'admin',
                    'source' => 'file',
                ];
            }
        }
    }

    // 2) DB-based auth (nur wenn nicht schon per File authed)
    if (!$authed && $pdo) {
        $stmt = $pdo->prepare('SELECT id, username, role, password_hash FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $authed = true;
            $_SESSION['admin'] = [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'role' => $user['role'] ?? 'admin',
                'source' => 'db',
            ];
        }
    }

    if ($authed) {
        session_regenerate_id(true);
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
  </div>
</body>
</html>
