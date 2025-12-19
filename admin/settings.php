<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin'])) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../src/App/Database.php';
require_once __DIR__ . '/../src/App/I18n.php';
use App\Database;
use App\I18n;

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$langOverride = isset($_GET['lang']) ? (string)$_GET['lang'] : null;
$i18n = I18n::fromConfig($ini, $langOverride);

$dbCfg = $ini['database'] ?? [];
$pdo = null;
try { $db = new Database($dbCfg); $pdo = $db->pdo(); } catch (Throwable $e) {}

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

$baseLangDir = dirname(__DIR__) . '/config/lang';
$available = [];
if (is_dir($baseLangDir)) {
    foreach (glob($baseLangDir . '/*.ini') as $file) {
        $code = strtolower(pathinfo($file, PATHINFO_FILENAME));
        $available[$code] = $code;
    }
}
if (!$available) { $available = ['de' => 'de', 'en' => 'en']; }

$error = '';
$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($csrf, $token)) {
        $error = $i18n->t('admin_setup.error_csrf');
    } else {
        $lang = strtolower((string)($_POST['lang'] ?? ''));
        if (!isset($available[$lang])) {
            $error = 'Unsupported language';
        } else {
            $_SESSION['lang'] = $lang;
            $i18n->setLocale($lang);
            $saved = true;
        }
    }
}

$admin = $_SESSION['admin'] ?? ['username' => 'admin'];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($i18n->locale()) ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($i18n->t('settings.title')) ?> – PiperBlog Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/admin/assets/styles/admin.css" rel="stylesheet">
</head>
<body>
  <div class="admin-layout">
    <aside class="admin-sidebar">
      <h2 class="brand">Admin</h2>
      <nav>
        <a href="/admin/dashboard.php">Dashboard</a>
        <a href="/admin/posts.php">Posts</a>
        <a href="/admin/comments.php">Comments</a>
        <a href="/admin/files.php">Files</a>
        <a href="/admin/categories.php">Categories</a>
        <a href="/admin/settings.php">Settings</a>
        <a href="/admin/logout.php">Logout</a>
      </nav>
    </aside>
    <main class="admin-content">
      <h1 style="margin-top:0"><?= htmlspecialchars($i18n->t('settings.title')) ?></h1>
      <p class="muted"><?= htmlspecialchars($i18n->t('dashboard.logged_in_as', ['{user}' => (string)($admin['username'] ?? 'admin')])) ?></p>

      <?php if ($saved): ?><div class="notice"><?= htmlspecialchars($i18n->t('settings.saved')) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <section class="panel">
        <h3><?= htmlspecialchars($i18n->t('settings.lead_language')) ?></h3>
        <form method="post" action="/admin/settings.php" class="form" autocomplete="off">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <label for="lang"><?= htmlspecialchars($i18n->t('settings.label_language')) ?></label>
          <select class="input" name="lang" id="lang">
            <?php foreach ($available as $code => $label): ?>
              <option value="<?= htmlspecialchars($code) ?>" <?= $code === $i18n->locale() ? 'selected' : '' ?>><?= htmlspecialchars(strtoupper($code)) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn" type="submit"><?= htmlspecialchars($i18n->t('settings.button_save')) ?></button>
        </form>
      </section>

      <section class="panel" style="margin-top:16px">
        <h3>Admin</h3>
        <p class="muted">Falls nötig, kannst du das Admin-Passwort über die Setup-Seite neu setzen.</p>
        <a class="btn" href="/admin/setup.php">Setup öffnen</a>
      </section>
    </main>
  </div>
</body>
</html>
