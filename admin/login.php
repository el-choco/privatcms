<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../src/App/Database.php';

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];

if (isset($_GET['lang']) && in_array($_GET['lang'], ['de', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$t_all = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$t = $t_all['admin_login'] ?? [];

$dbCfg = $ini['database'] ?? [];
$pdo = null;
try {
    if ($dbCfg) {
        $db  = new App\Database($dbCfg);
        $pdo = $db->pdo();
    }
} catch (Throwable $e) { }

$flag = __DIR__ . '/../config/first_run.flag';
$needsSetup = is_file($flag);
if (!$needsSetup && $pdo) {
    try {
        $countAdmin = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE username='admin'")->fetchColumn();
        if ($countAdmin === 0) { $needsSetup = true; }
    } catch (Throwable $e) { $needsSetup = true; }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo && !$needsSetup) {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $stmt = $pdo->prepare('SELECT id, username, role, password_hash FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if (($user['role'] ?? 'viewer') === 'viewer') {
            $error = $t['error_viewer'] ?? 'Access denied';
        } else {
            session_regenerate_id(true);
            $_SESSION['admin'] = [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'role' => $user['role'] ?? 'admin',
                'source' => 'db',
            ];

            try {
                $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, 'login', 'User logged in', ?)");
                $logStmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            } catch (Exception $e) {
            }

            header('Location: /admin/');
            exit;
        }
    } else {
        $error = $t['error_invalid'] ?? 'Invalid credentials';
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($t['title'] ?? 'Login') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; background: #f7fafc; margin: 0; display: flex; align-items: center; justify-content: center; height: 100vh; }
    .login-card { width: 100%; max-width: 400px; background: #fff; border-radius: 12px; padding: 40px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border-top: 6px solid #3182ce; }
    .input { display: block; width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; margin-bottom: 20px; box-sizing: border-box; font-size: 16px; transition: border-color 0.2s; }
    .input:focus { border-color: #3182ce; outline: none; }
    .btn { display: block; width: 100%; padding: 12px; border-radius: 8px; background: #3182ce; color: #fff; border: 0; font-weight: bold; font-size: 16px; cursor: pointer; transition: background 0.2s; }
    .btn:hover { background: #2b6cb0; }
    .btn:disabled { background: #cbd5e0; cursor: not-allowed; }
    .error { color: #e53e3e; background: #fff5f5; border: 1px solid #feb2b2; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; text-align: center; font-weight: 600; }
    .notice { background: #ebf8ff; border: 1px solid #bee3f8; color: #2c5282; padding: 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; line-height: 1.5; }
    .lang { text-align: center; margin-bottom: 20px; font-size: 14px; font-weight: 600; }
    .lang a { color: #718096; text-decoration: none; padding: 5px; }
    .lang a.active { color: #3182ce; }
    h1 { margin: 0 0 30px 0; font-size: 1.8rem; text-align: center; color: #1a202c; }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="lang">
      <a href="?lang=de" class="<?= $currentLang === 'de' ? 'active' : '' ?>">DE</a> · 
      <a href="?lang=en" class="<?= $currentLang === 'en' ? 'active' : '' ?>">EN</a>
    </div>

    <h1><?= htmlspecialchars($t['title'] ?? 'Login') ?></h1>

    <?php if ($needsSetup): ?>
      <div class="notice">
        <strong style="display:block; margin-bottom:5px;"><?= htmlspecialchars($t['notice_setup_title'] ?? 'Setup required') ?></strong>
        <div><?= htmlspecialchars(str_replace('{user}', 'admin', $t['notice_setup_text'] ?? '')) ?></div>
        <div style="margin-top:15px">
            <a class="btn" style="text-align:center; text-decoration:none;" href="/admin/setup.php">
                <?= htmlspecialchars($t['action_setup_start'] ?? 'Start Setup') ?>
            </a>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post" action="/admin/login.php">
      <input class="input" type="text" name="username" placeholder="<?= htmlspecialchars($t['username'] ?? 'Username') ?>" required <?php if ($needsSetup) echo 'disabled'; ?>>
      <input class="input" type="password" name="password" placeholder="<?= htmlspecialchars($t['password'] ?? 'Password') ?>" required <?php if ($needsSetup) echo 'disabled'; ?>>
      <button class="btn" type="submit" <?php if ($needsSetup) echo 'disabled'; ?>>
        <?= htmlspecialchars($t['button_login'] ?? 'Sign in') ?>
      </button>
    </form>
  </div>
</body>
</html>