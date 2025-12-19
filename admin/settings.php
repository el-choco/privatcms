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

$iniPath = __DIR__ . '/../config/config.ini';
$ini = parse_ini_file($iniPath, true, INI_SCANNER_TYPED) ?: [];
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

// Helper: merge sections and write back to INI
function ini_merge_write(string $path, array $ini, array $delta): bool {
    foreach ($delta as $section => $pairs) {
        if (!isset($ini[$section]) || !is_array($ini[$section])) { $ini[$section] = []; }
        foreach ($pairs as $k => $v) { $ini[$section][$k] = $v; }
    }
    $out = '';
    foreach ($ini as $section => $pairs) {
        $out .= "[$section]\n";
        foreach ($pairs as $k => $v) {
            if (is_bool($v)) { $v = $v ? 'true' : 'false'; }
            $out .= $k . '=' . (is_numeric($v) ? (string)$v : (string)$v) . "\n";
        }
        $out .= "\n";
    }
    return (bool)@file_put_contents($path, $out);
}

$tab = isset($_GET['tab']) ? (string)$_GET['tab'] : 'language';
$error = '';
$saved = false;
$savedMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf'] ?? '');
    $tabPost = (string)($_POST['tab'] ?? '');
    if (!hash_equals($csrf, $token)) {
        $error = $i18n->t('admin_setup.error_csrf');
    } else {
        $tab = $tabPost ?: $tab;
        try {
            $delta = [];
            switch ($tab) {
                case 'general':
                    $appTitle = trim((string)($_POST['app_title'] ?? ''));
                    $appDesc  = trim((string)($_POST['app_desc'] ?? ''));
                    $delta['app'] = [
                        'title' => $appTitle ?: ($ini['app']['title'] ?? 'PiperBlog'),
                        'description' => $appDesc ?: ($ini['app']['description'] ?? ''),
                    ];
                    break;
                case 'language':
                    $lang = strtolower((string)($_POST['lang'] ?? ''));
                    if (!isset($available[$lang])) {
                        $error = $i18n->t('settings.error_unsupported');
                    } else {
                        $_SESSION['lang'] = $lang;
                        $i18n->setLocale($lang);
                        $delta['app'] = ['lang' => $lang];
                    }
                    break;
                case 'theme':
                    $customCss = (string)($_POST['custom_css'] ?? '');
                    $delta['theme'] = ['custom_css' => $customCss];
                    break;
                case 'email':
                    $delta['email'] = [
                        'enabled' => isset($_POST['email_enabled']),
                        'smtp_host' => (string)($_POST['smtp_host'] ?? ''),
                        'smtp_port' => (int)($_POST['smtp_port'] ?? 587),
                        'smtp_user' => (string)($_POST['smtp_user'] ?? ''),
                        'smtp_pass' => (string)($_POST['smtp_pass'] ?? ''),
                        'smtp_secure' => (string)($_POST['smtp_secure'] ?? 'tls'),
                        'from' => (string)($_POST['email_from'] ?? ''),
                    ];
                    break;
                case 'database':
                    $delta['database'] = [
                        'host' => (string)($_POST['db_host'] ?? ($ini['database']['host'] ?? 'localhost')),
                        'name' => (string)($_POST['db_name'] ?? ($ini['database']['name'] ?? 'piperblog')),
                        'user' => (string)($_POST['db_user'] ?? ($ini['database']['user'] ?? 'root')),
                        'pass' => (string)($_POST['db_pass'] ?? ($ini['database']['pass'] ?? '')),
                    ];
                    break;
                case 'system':
                    $delta['system'] = [
                        'soft_delete' => isset($_POST['soft_delete']),
                        'delete_files' => isset($_POST['delete_files']),
                        'auto_cleanup' => isset($_POST['auto_cleanup']),
                    ];
                    break;
                case 'debug':
                    $delta['debug'] = [
                        'enabled' => isset($_POST['debug_enabled']),
                    ];
                    $delta['logs'] = [
                        'enabled' => isset($_POST['logs_enabled']),
                    ];
                    break;
            }
            if (!$error) {
                if (!ini_merge_write($iniPath, $ini, $delta)) {
                    $error = $i18n->t('settings.error_write');
                } else {
                    $saved = true;
                    $savedMsg = $i18n->t('settings.saved');
                    $ini = parse_ini_file($iniPath, true, INI_SCANNER_TYPED) ?: $ini; // reload
                }
            }
        } catch (Throwable $e) {
            $error = $i18n->t('settings.error_write');
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
        <a href="/admin/dashboard.php"><?= htmlspecialchars($i18n->t('nav.dashboard')) ?></a>
        <a href="/admin/posts.php"><?= htmlspecialchars($i18n->t('nav.posts')) ?></a>
        <a href="/admin/comments.php"><?= htmlspecialchars($i18n->t('nav.comments')) ?></a>
        <a href="/admin/files.php"><?= htmlspecialchars($i18n->t('nav.files')) ?></a>
        <a href="/admin/categories.php"><?= htmlspecialchars($i18n->t('nav.categories')) ?></a>
        <a href="/admin/settings.php"><?= htmlspecialchars($i18n->t('nav.settings')) ?></a>
        <a href="/admin/logout.php"><?= htmlspecialchars($i18n->t('nav.logout')) ?></a>
      </nav>
    </aside>
    <main class="admin-content">

      <div class="topbar">
        <h1 style="margin:0"><?= htmlspecialchars($i18n->t('settings.title')) ?></h1>
        <div class="lang-switch">
          <?php $cur = $i18n->locale(); ?>
          <a href="?lang=de&tab=<?= htmlspecialchars($tab) ?>" class="<?= $cur==='de'?'active':'' ?>">DE</a>
          <a href="?lang=en&tab=<?= htmlspecialchars($tab) ?>" class="<?= $cur==='en'?'active':'' ?>">EN</a>
        </div>
      </div>

      <p class="muted"><?= htmlspecialchars($i18n->t('dashboard.logged_in_as', ['{user}' => (string)($admin['username'] ?? 'admin')])) ?></p>

      <?php if ($saved): ?><div class="notice"><?= htmlspecialchars($savedMsg ?: $i18n->t('settings.saved')) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <div class="panel">
        <nav class="tab-nav">
          <a href="/admin/settings.php?tab=general" class="<?= $tab==='general'?'active':'' ?>"><?= htmlspecialchars($i18n->t('settings.tab_general')) ?></a>
          <a href="/admin/settings.php?tab=language" class="<?= $tab==='language'?'active':'' ?>"><?= htmlspecialchars($i18n->t('settings.tab_language')) ?></a>
          <a href="/admin/settings.php?tab=theme" class="<?= $tab==='theme'?'active':'' ?>"><?= htmlspecialchars($i18n->t('settings.tab_theme')) ?></a>
          <a href="/admin/settings.php?tab=email" class="<?= $tab==='email'?'active':'' ?>"><?= htmlspecialchars($i18n->t('settings.tab_email')) ?></a>
          <a href="/admin/settings.php?tab=database" class="<?= $tab==='database'?'active':'' ?>"><?= htmlspecialchars($i18n->t('settings.tab_database')) ?></a>
          <a href="/admin/settings.php?tab=system" class="<?= $tab==='system'?'active':'' ?>"><?= htmlspecialchars($i18n->t('settings.tab_system')) ?></a>
          <a href="/admin/settings.php?tab=debug" class="<?= $tab==='debug'?'active':'' ?>"><?= htmlspecialchars($i18n->t('settings.tab_debug')) ?></a>
        </nav>

        <?php if ($tab === 'general'): ?>
          <form method="post" class="form" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="tab" value="general">
            <label for="app_title"><?= htmlspecialchars($i18n->t('settings.general.title')) ?></label>
            <input class="input" type="text" id="app_title" name="app_title" value="<?= htmlspecialchars((string)($ini['app']['title'] ?? 'PiperBlog')) ?>">

            <label for="app_desc"><?= htmlspecialchars($i18n->t('settings.general.description')) ?></label>
            <input class="input" type="text" id="app_desc" name="app_desc" value="<?= htmlspecialchars((string)($ini['app']['description'] ?? '')) ?>">

            <button class="btn" type="submit"><?= htmlspecialchars($i18n->t('settings.button_save')) ?></button>
          </form>
        <?php endif; ?>

        <?php if ($tab === 'language'): ?>
          <form method="post" class="form" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="tab" value="language">
            <label for="lang"><?= htmlspecialchars($i18n->t('settings.label_language')) ?></label>
            <select class="input" name="lang" id="lang">
              <?php foreach ($available as $code => $label): ?>
                <option value="<?= htmlspecialchars($code) ?>" <?= $code === $i18n->locale() ? 'selected' : '' ?>><?= htmlspecialchars(strtoupper($code)) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn" type="submit"><?= htmlspecialchars($i18n->t('settings.button_save')) ?></button>
          </form>
        <?php endif; ?>

        <?php if ($tab === 'theme'): ?>
          <form method="post" class="form" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="tab" value="theme">
            <label for="custom_css"><?= htmlspecialchars($i18n->t('settings.theme.custom_css')) ?></label>
            <textarea class="input" id="custom_css" name="custom_css" rows="8"><?= htmlspecialchars((string)($ini['theme']['custom_css'] ?? '')) ?></textarea>
            <button class="btn" type="submit"><?= htmlspecialchars($i18n->t('settings.button_save')) ?></button>
          </form>
        <?php endif; ?>

        <?php if ($tab === 'email'): ?>
          <form method="post" class="form" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="tab" value="email">
            <label><input type="checkbox" name="email_enabled" <?= !empty($ini['email']['enabled']) ? 'checked' : '' ?>> <?= htmlspecialchars($i18n->t('settings.email.enable')) ?></label>
            <label for="smtp_host"><?= htmlspecialchars($i18n->t('settings.email.smtp_host')) ?></label>
            <input class="input" type="text" id="smtp_host" name="smtp_host" value="<?= htmlspecialchars((string)($ini['email']['smtp_host'] ?? '')) ?>">
            <label for="smtp_port"><?= htmlspecialchars($i18n->t('settings.email.smtp_port')) ?></label>
            <input class="input" type="number" id="smtp_port" name="smtp_port" value="<?= htmlspecialchars((string)($ini['email']['smtp_port'] ?? '587')) ?>">
            <label for="smtp_user"><?= htmlspecialchars($i18n->t('settings.email.smtp_user')) ?></label>
            <input class="input" type="text" id="smtp_user" name="smtp_user" value="<?= htmlspecialchars((string)($ini['email']['smtp_user'] ?? '')) ?>">
            <label for="smtp_pass"><?= htmlspecialchars($i18n->t('settings.email.smtp_pass')) ?></label>
            <input class="input" type="password" id="smtp_pass" name="smtp_pass" value="<?= htmlspecialchars((string)($ini['email']['smtp_pass'] ?? '')) ?>">
            <label for="smtp_secure"><?= htmlspecialchars($i18n->t('settings.email.smtp_secure')) ?></label>
            <select class="input" id="smtp_secure" name="smtp_secure">
              <?php foreach (['none','ssl','tls'] as $opt): ?>
                <option value="<?= htmlspecialchars($opt) ?>" <?= ($ini['email']['smtp_secure'] ?? 'tls') === $opt ? 'selected' : '' ?>><?= htmlspecialchars(strtoupper($opt)) ?></option>
              <?php endforeach; ?>
            </select>
            <label for="email_from"><?= htmlspecialchars($i18n->t('settings.email.from')) ?></label>
            <input class="input" type="email" id="email_from" name="email_from" value="<?= htmlspecialchars((string)($ini['email']['from'] ?? '')) ?>">
            <button class="btn" type="submit"><?= htmlspecialchars($i18n->t('settings.button_save')) ?></button>
          </form>
        <?php endif; ?>

        <?php if ($tab === 'database'): ?>
          <form method="post" class="form" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="tab" value="database">
            <p class="muted"><?= htmlspecialchars($i18n->t('settings.database.note')) ?></p>
            <label for="db_host">Host</label>
            <input class="input" type="text" id="db_host" name="db_host" value="<?= htmlspecialchars((string)($ini['database']['host'] ?? 'localhost')) ?>">
            <label for="db_name">Name</label>
            <input class="input" type="text" id="db_name" name="db_name" value="<?= htmlspecialchars((string)($ini['database']['name'] ?? 'piperblog')) ?>">
            <label for="db_user">User</label>
            <input class="input" type="text" id="db_user" name="db_user" value="<?= htmlspecialchars((string)($ini['database']['user'] ?? 'root')) ?>">
            <label for="db_pass">Pass</label>
            <input class="input" type="password" id="db_pass" name="db_pass" value="<?= htmlspecialchars((string)($ini['database']['pass'] ?? '')) ?>">
            <button class="btn" type="submit"><?= htmlspecialchars($i18n->t('settings.button_save')) ?></button>
          </form>
        <?php endif; ?>

        <?php if ($tab === 'system'): ?>
          <form method="post" class="form" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="tab" value="system">
            <label><input type="checkbox" name="soft_delete" <?= !empty($ini['system']['soft_delete']) ? 'checked' : '' ?>> <?= htmlspecialchars($i18n->t('settings.system.soft_delete')) ?></label>
            <label><input type="checkbox" name="delete_files" <?= !empty($ini['system']['delete_files']) ? 'checked' : '' ?>> <?= htmlspecialchars($i18n->t('settings.system.delete_files')) ?></label>
            <label><input type="checkbox" name="auto_cleanup" <?= !empty($ini['system']['auto_cleanup']) ? 'checked' : '' ?>> <?= htmlspecialchars($i18n->t('settings.system.auto_cleanup')) ?></label>
            <button class="btn" type="submit"><?= htmlspecialchars($i18n->t('settings.button_save')) ?></button>
          </form>
        <?php endif; ?>

        <?php if ($tab === 'debug'): ?>
          <form method="post" class="form" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="tab" value="debug">
            <label><input type="checkbox" name="debug_enabled" <?= !empty($ini['debug']['enabled']) ? 'checked' : '' ?>> <?= htmlspecialchars($i18n->t('settings.debug.enable')) ?></label>
            <label><input type="checkbox" name="logs_enabled" <?= !empty($ini['logs']['enabled']) ? 'checked' : '' ?>> <?= htmlspecialchars($i18n->t('settings.logs.enable')) ?></label>
            <button class="btn" type="submit"><?= htmlspecialchars($i18n->t('settings.button_save')) ?></button>
          </form>
        <?php endif; ?>

      </div>

      <section class="panel" style="margin-top:16px">
        <h3>Admin</h3>
        <p class="muted">Falls nötig, kannst du das Admin-Passwort über die Setup-Seite neu setzen.</p>
        <a class="btn" href="/admin/setup.php">Setup öffnen</a>
      </section>
    </main>
  </div>
</body>
</html>.