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
$error = '';
$pdo = null;

try { $db = new Database($dbCfg); $pdo = $db->pdo(); }
catch (Throwable $e) { $error = $i18n->t('admin_setup.error_db'); }

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $token = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($csrf, $token)) {
        $error = $i18n->t('admin_setup.error_csrf');
    } else {
        $username = 'admin';
        $password = (string)($_POST['password'] ?? '');
        $confirm  = (string)($_POST['confirm'] ?? '');
        if (strlen($password) < 8) {
            $error = $i18n->t('admin_setup.error_length', ['{min}' => '8']);
        } elseif ($password !== $confirm) {
            $error = $i18n->t('admin_setup.error_match');
        } else {
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
            $flag = __DIR__ . '/../config/first_run.flag';
            if (is_file($flag)) { @unlink($flag); }
            $_SESSION['admin'] = ['id' => (int)($user['id'] ?? 0), 'username' => $username, 'role' => 'admin', 'source' => 'setup'];
            session_regenerate_id(true);
            header('Location: /admin/');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($i18n->locale()) ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($i18n->t('admin_setup.title')) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/admin/assets/styles/admin.css" rel="stylesheet">
</head>
<body>
  <div class="login-card">
    <h1><?= htmlspecialchars($i18n->t('admin_setup.title')) ?></h1>
    <p><?= htmlspecialchars($i18n->t('admin_setup.lead', ['{user}' => 'admin'])) ?></p>

    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post" action="/admin/setup.php" autocomplete="off" class="form form-2col">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <div>
        <label><?= htmlspecialchars($i18n->t('admin_setup.label_password')) ?></label>
        <input class="input" type="password" name="password" required minlength="8" placeholder="" autofocus>
      </div>
      <div>
        <label><?= htmlspecialchars($i18n->t('admin_setup.label_confirm')) ?></label>
        <input class="input" type="password" name="confirm" required minlength="8" placeholder="">
      </div>
      <div>
        <button class="btn" type="submit"><?= htmlspecialchars($i18n->t('admin_setup.button_save_continue')) ?></button>
      </div>
    </form>

    <p style="margin-top:10px"><a href="/admin/login.php"><?= htmlspecialchars($i18n->t('admin_setup.link_back_login')) ?></a></p>
  </div>
</body>
</html>
