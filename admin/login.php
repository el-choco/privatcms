<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../src/App/Database.php';
require_once __DIR__ . '/../src/App/I18n.php';
use App\Database;
use App\I18n;

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$langOverride = isset($_GET['lang']) ? (string)$_GET['lang'] : null;
$i18n = I18n::fromConfig($ini, $langOverride);

$dbCfg = $ini['database'] ?? [];
$pdo = null;
try {
    if ($dbCfg) {
        $db  = new Database($dbCfg);
        $pdo = $db->pdo();
    }
} catch (Throwable $e) { /* Setup-Hinweis unten */ }

// Setup-Erkennung
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
        $error = $i18n->t('admin_login.error_invalid');
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($i18n->locale()) ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($i18n->t('admin_login.title')) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/admin/assets/styles/admin.css" rel="stylesheet">
  <style>
    .login-card{max-width:480px;margin:60px auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px}
    .input{display:block;width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:10px}
    .btn{display:inline-block;padding:10px 14px;border-radius:8px;background:#1877f2;color:#fff;border:0}
    .error{color:#b91c1c;margin-bottom:12px}
    .notice{background:#FEF3C7;border:1px solid #F59E0B;color:#92400E;padding:12px;border-radius:8px;margin-bottom:12px}
    .lang{margin-top:8px;font-size:12px}
  </style>
</head>
<body>
  <div class="login-card">
    <h1><?= htmlspecialchars($i18n->t('admin_login.title')) ?></h1>

    <div class="lang">
      <a href="?lang=de">DE</a> · <a href="?lang=en">EN</a>
    </div>

    <?php if ($needsSetup): ?>
      <div class="notice">
        <strong><?= htmlspecialchars($i18n->t('admin_login.notice_setup_title')) ?></strong>
        <div><?= htmlspecialchars($i18n->t('admin_login.notice_setup_text', ['{user}' => 'admin'])) ?></div>
        <div style="margin-top:8px"><a class="btn" href="/admin/setup.php"><?= htmlspecialchars($i18n->t('admin_login.action_setup_start')) ?></a></div>
      </div>
    <?php endif; ?>

    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post" action="/admin/login.php">
      <input class="input" type="text" name="username" placeholder="<?= htmlspecialchars($i18n->t('admin_login.username')) ?>" required <?php if ($needsSetup) echo 'disabled'; ?>>
      <input class="input" type="password" name="password" placeholder="<?= htmlspecialchars($i18n->t('admin_login.password')) ?>" required <?php if ($needsSetup) echo 'disabled'; ?>>
      <button class="btn" type="submit" <?php if ($needsSetup) echo 'disabled'; ?>><?= htmlspecialchars($i18n->t('admin_login.button_login')) ?></button>
    </form>

    <p style="margin-top:10px"><a href="/admin/setup.php"><?= htmlspecialchars($i18n->t('admin_login.action_reset_password')) ?></a></p>
  </div>
</body>
</html>
