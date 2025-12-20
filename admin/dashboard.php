<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { header('Location: /admin/login.php'); exit; }

require_once __DIR__ . '/../src/App/Database.php';
require_once __DIR__ . '/../src/App/I18n.php';
use App\Database;
use App\I18n;

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$langOverride = isset($_GET['lang']) ? (string)$_GET['lang'] : null;
$i18n = I18n::fromConfig($ini, $langOverride);

$dbCfg = $ini['database'] ?? [];
$pdo = (new Database($dbCfg))->pdo();

$stats = [
    'posts_total'      => (int)$pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn(),
    'posts_published'  => (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE status='published'")->fetchColumn(),
    'posts_drafts'     => (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE status='draft'")->fetchColumn(),
    'comments_total'   => 0, 'categories_total' => 0, 'users_total' => 0,
];
foreach (['comments_total' => 'comments', 'categories_total' => 'categories', 'users_total' => 'users'] as $k => $tbl) {
    try { $stats[$k] = (int)$pdo->query("SELECT COUNT(*) FROM {$tbl}")->fetchColumn(); } catch (Throwable $e) {}
}
$latestPosts = [];
try {
    $stmt = $pdo->query("SELECT id, title, status, created_at FROM posts ORDER BY created_at DESC LIMIT 8");
    $latestPosts = $stmt->fetchAll();
} catch (Throwable $e) {}
$admin = $_SESSION['admin'] ?? ['username' => 'admin'];

function svg($name, $cls='icon-18') {
  $icons = [
    'posts' => '<path d="M6 3h7a2 2 0 0 1 2 2v12l-4-2-4 2V5a2 2 0 0 1 2-2z"/>',
    'published' => '<path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>',
    'drafts' => '<path d="M3 6h12v12H3z"/><path d="M7 3h12v12h-4" fill="none"/>',
    'comments' => '<path d="M21 15a4 4 0  0 1-4 4H7l-4 3V7a4 4 0  0 1 4-4h10a4 4 0 0 1 4 4z" fill="none"/>',
    'categories' => '<path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z"/>',
    'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4h-2a4 4 0  0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>',
    'open' => '<path d="M5 12h11"/><path d="M12 5l7 7-7 7" />',
    'new' => '<path d="M12 5v14M5 12h14" />',
    'manage' => '<path d="M4 6h16M4 12h10M4 18h16" />',
    'file' => '<path d="M14 3H6a2 2 0 0 0-2 2v14l4-2 4 2 4-2 4 2V9z" fill="none"/>',
    'settings' => '<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0  0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21"/>',
  ];
  $path = $icons[$name] ?? '';
  return '<svg class="'.$cls.'" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.5" aria-hidden="true">'.$path.'</svg>';
}
function fmtDate(I18n $i18n, string $ts): string {
  $fmt = $i18n->t('common.date_fmt');
  return date($fmt ?: 'd.m.Y', strtotime($ts));
}
function fmtBytes($bytes): string {
  $b = (float)$bytes; if ($b <= 0) return '0 B';
  $u = ['B','KB','MB','GB','TB']; $i = (int)floor(log($b, 1024));
  return sprintf('%.1f %s', $b/pow(1024,$i), $u[$i]);
}
function yn(I18n $i18n, bool $v): string { return $v ? ($i18n->t('common.yes') ?: 'Yes') : ($i18n->t('common.no') ?: 'No'); }

/* System info */
$sys = [
  'php_version' => PHP_VERSION,
  'php_sapi' => PHP_SAPI,
  'php_ini' => php_ini_loaded_file() ?: '(none)',
  'os' => PHP_OS.' '.php_uname('r'),
  'server' => $_SERVER['SERVER_SOFTWARE'] ?? '',
  'timezone' => date_default_timezone_get(),
  'memory_limit' => ini_get('memory_limit'),
  'max_execution_time' => ini_get('max_execution_time'),
  'upload_max_filesize' => ini_get('upload_max_filesize'),
  'post_max_size' => ini_get('post_max_size'),
  'extensions' => [],
  'disk_total' => null,
  'disk_free' => null,
  'config_writable' => is_writable(__DIR__.'/../config'),
  'db_version' => null,
  'db_charset' => null,
  'db_collation' => null,
  'db_sql_mode' => null,
];
try { $sys['extensions'] = get_loaded_extensions(); sort($sys['extensions']); } catch (Throwable $e) {}
try { $sys['disk_total'] = @disk_total_space('/'); $sys['disk_free'] = @disk_free_space('/'); } catch (Throwable $e) {}
try {
  $sys['db_version'] = (string)$pdo->query("SELECT VERSION()")->fetchColumn();
  $sys['db_charset'] = (string)$pdo->query("SHOW VARIABLES LIKE 'character_set_database'")->fetch(PDO::FETCH_ASSOC)['Value'] ?? '';
  $sys['db_collation'] = (string)$pdo->query("SHOW VARIABLES LIKE 'collation_database'")->fetch(PDO::FETCH_ASSOC)['Value'] ?? '';
  $sys['db_sql_mode'] = (string)$pdo->query("SHOW VARIABLES LIKE 'sql_mode'")->fetch(PDO::FETCH_ASSOC)['Value'] ?? '';
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($i18n->locale()) ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($i18n->t('dashboard.title')) ?> – PiperBlog Admin</title>
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
        <h1 style="margin:0"><?= htmlspecialchars($i18n->t('dashboard.title')) ?></h1>
        <div class="lang-switch">
          <?php $cur = $i18n->locale(); ?>
          <a href="?lang=de" class="<?= $cur==='de'?'active':'' ?>">DE</a>
          <a href="?lang=en" class="<?= $cur==='en'?'active':'' ?>">EN</a>
        </div>
      </div>
      <p class="muted"><?= htmlspecialchars($i18n->t('dashboard.logged_in_as', ['{user}' => (string)($admin['username'] ?? 'admin')])) ?></p>

      <section class="cards">
        <div class="card"><div class="kpi"><div class="kpi-icon"><?= svg('posts') ?></div><div><div class="muted"><?= htmlspecialchars($i18n->t('dashboard.cards_posts_total')) ?></div><div class="metric"><?= (int)$stats['posts_total'] ?></div></div></div></div>
        <div class="card"><div class="kpi"><div class="kpi-icon"><?= svg('published') ?></div><div><div class="muted"><?= htmlspecialchars($i18n->t('dashboard.cards_published')) ?></div><div class="metric"><?= (int)$stats['posts_published'] ?></div></div></div></div>
        <div class="card"><div class="kpi"><div class="kpi-icon"><?= svg('drafts') ?></div><div><div class="muted"><?= htmlspecialchars($i18n->t('dashboard.cards_drafts')) ?></div><div class="metric"><?= (int)$stats['posts_drafts'] ?></div></div></div></div>
        <div class="card"><div class="kpi"><div class="kpi-icon"><?= svg('comments') ?></div><div><div class="muted"><?= htmlspecialchars($i18n->t('dashboard.cards_comments')) ?></div><div class="metric"><?= (int)$stats['comments_total'] ?></div></div></div></div>
        <div class="card"><div class="kpi"><div class="kpi-icon"><?= svg('categories') ?></div><div><div class="muted"><?= htmlspecialchars($i18n->t('dashboard.cards_categories')) ?></div><div class="metric"><?= (int)$stats['categories_total'] ?></div></div></div></div>
        <div class="card"><div class="kpi"><div class="kpi-icon"><?= svg('users') ?></div><div><div class="muted"><?= htmlspecialchars($i18n->t('dashboard.cards_users')) ?></div><div class="metric"><?= (int)$stats['users_total'] ?></div></div></div></div>
      </section>

      <div class="grid">
        <section class="panel">
          <h3><?= htmlspecialchars($i18n->t('dashboard.panel_latest_posts')) ?></h3>
          <?php if (empty($latestPosts)): ?>
            <p class="muted"><?= htmlspecialchars($i18n->t('dashboard.no_posts')) ?></p>
          <?php else: ?>
            <ul class="list">
              <?php foreach ($latestPosts as $p): ?>
                <li>
                  <span>
                    <strong>#<?= (int)$p['id'] ?></strong> <?= htmlspecialchars((string)($p['title'] ?? '')) ?>
                  </span>
                  <span>
                    <span class="badge"><?= htmlspecialchars((string)($p['status'] ?? '')) ?></span>
                    <span class="muted" style="margin-left:8px;">
                      <?= htmlspecialchars(fmtDate($i18n, (string)$p['created_at'])) ?>
                    </span>
                    <a class="btn" style="margin-left:10px" href="/admin/article.php?id=<?= (int)$p['id'] ?>">
                      <?= htmlspecialchars($i18n->t('dashboard.button_open')) ?> <?= svg('open','icon-16') ?>
                    </a>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>

        <section class="panel">
          <h3><?= htmlspecialchars($i18n->t('dashboard.panel_quick')) ?></h3>
          <div class="quick">
            <a href="/admin/posts.php"><?= svg('manage','icon-18') ?> <?= htmlspecialchars($i18n->t('dashboard.quick_manage_posts')) ?></a>
            <a href="/admin/comments.php"><?= svg('comments','icon-18') ?> <?= htmlspecialchars($i18n->t('dashboard.quick_comments')) ?></a>
            <a href="/admin/files.php"><?= svg('file','icon-18') ?> <?= htmlspecialchars($i18n->t('dashboard.quick_files')) ?></a>
            <a href="/admin/categories.php"><?= svg('categories','icon-18') ?> <?= htmlspecialchars($i18n->t('dashboard.quick_categories')) ?></a>
            <a href="/admin/settings.php"><?= svg('settings','icon-18') ?> <?= htmlspecialchars($i18n->t('dashboard.quick_settings')) ?></a>
          </div>
        </section>
      </div>

      <section class="panel">
        <h3><?= htmlspecialchars($i18n->t('dashboard.system_title')) ?></h3>
        <ul class="list">
          <li><span>PHP</span><span><?= htmlspecialchars($sys['php_version']) ?> • <?= htmlspecialchars($sys['php_sapi']) ?></span></li>
          <li><span><?= htmlspecialchars($i18n->t('dashboard.system_php_ini')) ?></span><span><?= htmlspecialchars($sys['php_ini']) ?></span></li>
          <li><span><?= htmlspecialchars($i18n->t('dashboard.system_server')) ?></span><span><?= htmlspecialchars($sys['server']) ?></span></li>
          <li><span><?= htmlspecialchars($i18n->t('dashboard.system_os')) ?></span><span><?= htmlspecialchars($sys['os']) ?></span></li>
          <li><span><?= htmlspecialchars($i18n->t('dashboard.system_timezone')) ?></span><span><?= htmlspecialchars($sys['timezone']) ?></span></li>
          <li><span><?= htmlspecialchars($i18n->t('dashboard.system_memory_limit')) ?></span><span><?= htmlspecialchars($sys['memory_limit']) ?></span></li>
          <li><span><?= htmlspecialchars($i18n->t('dashboard.system_upload_max')) ?></span><span><?= htmlspecialchars($sys['upload_max_filesize']) ?></span></li>
          <li><span><?= htmlspecialchars($i18n->t('dashboard.system_post_max')) ?></span><span><?= htmlspecialchars($sys['post_max_size']) ?></span></li>
          <li><span><?= htmlspecialchars($i18n->t('dashboard.system_max_exec')) ?></span><span><?= htmlspecialchars((string)$sys['max_execution_time']) ?>s</span></li>
          <li><span><?= htmlspecialchars($i18n->t('dashboard.system_config_writable')) ?></span><span><?= htmlspecialchars(yn($i18n, (bool)$sys['config_writable'])) ?></span></li>
          <li><span><?= htmlspecialchars($i18n->t('dashboard.system_disk')) ?></span><span><?= htmlspecialchars(fmtBytes($sys['disk_free'] ?? 0)) ?> / <?= htmlspecialchars(fmtBytes($sys['disk_total'] ?? 0)) ?></span></li>
          <li><span><?= htmlspecialchars($i18n->t('dashboard.system_db_version')) ?></span><span><?= htmlspecialchars($sys['db_version'] ?? '') ?></span></li>
          <li><span><?= htmlspecialchars($i18n->t('dashboard.system_db_charset')) ?></span><span><?= htmlspecialchars($sys['db_charset'] ?? '') ?></span></li>
          <li><span><?= htmlspecialchars($i18n->t('dashboard.system_db_collation')) ?></span><span><?= htmlspecialchars($sys['db_collation'] ?? '') ?></span></li>
          <li><span><?= htmlspecialchars($i18n->t('dashboard.system_db_sqlmode')) ?></span><span><?= htmlspecialchars($sys['db_sql_mode'] ?? '') ?></span></li>
          <li><span><?= htmlspecialchars($i18n->t('dashboard.system_extensions')) ?></span><span><?= htmlspecialchars(implode(', ', array_slice($sys['extensions'], 0, 24))) ?><?php $cnt=count($sys['extensions']); if ($cnt>24) echo ' … (+'.($cnt-24).')'; ?></span></li>
        </ul>
      </section>
    </main>
  </div>
</body>
</html>
