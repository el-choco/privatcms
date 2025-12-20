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

// Statistiken laden
$stats = [
    'posts_total'      => (int)$pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn(),
    'posts_published'  => (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE status='published'")->fetchColumn(),
    'comments_total'   => (int)$pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn(),
    'users_total'      => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
];

// Daten für die Listen
$latestPosts = $pdo->query("SELECT id, title, status, created_at FROM posts ORDER BY created_at DESC LIMIT 5")->fetchAll();
$latestComments = $pdo->query("SELECT author_name, content, created_at FROM comments ORDER BY created_at DESC LIMIT 5")->fetchAll();

$admin = $_SESSION['admin'] ?? ['username' => 'admin'];

function svg($name, $cls='icon-18') {
  $icons = [
    'posts' => '<path d="M6 3h7a2 2 0 0 1 2 2v12l-4-2-4 2V5a2 2 0 0 1 2-2z"/>',
    'published' => '<path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>',
    'comments' => '<path d="M21 15a4 4 0 0 1-4 4H7l-4 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z" fill="none"/>',
    'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4h-2a4 4 0  0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>',
    'open' => '<path d="M5 12h11"/><path d="M12 5l7 7-7 7" />',
    'server' => '<rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect><rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect><line x1="6" y1="6" x2="6.01" y2="6"></line><line x1="6" y1="18" x2="6.01" y2="18"></line>'
  ];
  return '<svg class="'.$cls.'" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.5">'.($icons[$name] ?? '').'</svg>';
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($i18n->locale()) ?>">
<head>
  <meta charset="utf-8">
  <title>Dashboard – PiperBlog Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/admin/assets/styles/admin.css" rel="stylesheet">
  <style>
    /* Full Width Stats */
    .stats-full { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 25px; }
    
    /* Grid für die unteren 3 Panels */
    .dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .span-full { grid-column: span 2; }
    
    .system-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    .system-table td { padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
    .system-table td:last-child { text-align: right; font-weight: bold; color: #2c3e50; }

    .comment-preview { font-size: 0.85rem; color: #555; background: #f9f9f9; padding: 5px 10px; border-radius: 4px; display: block; margin-top: 4px; }
  </style>
</head>
<body>
  <div class="admin-layout">
    <aside class="admin-sidebar">
      <h2 class="brand">Admin</h2>
      <nav>
        <a href="/admin/dashboard.php" class="active"><?= htmlspecialchars($i18n->t('nav.dashboard')) ?></a>
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
        <h1><?= htmlspecialchars($i18n->t('dashboard.title')) ?></h1>
        <div class="lang-switch">
          <a href="?lang=de" class="<?= $i18n->locale()==='de'?'active':'' ?>">DE</a>
          <a href="?lang=en" class="<?= $i18n->locale()==='en'?'active':'' ?>">EN</a>
        </div>
      </div>

      <section class="stats-full">
        <div class="card"><div class="kpi"><div class="kpi-icon"><?= svg('posts') ?></div><div><div class="muted">Beiträge</div><div class="metric"><?= $stats['posts_total'] ?></div></div></div></div>
        <div class="card"><div class="kpi"><div class="kpi-icon" style="color:#27ae60"><?= svg('published') ?></div><div><div class="muted">Online</div><div class="metric"><?= $stats['posts_published'] ?></div></div></div></div>
        <div class="card"><div class="kpi"><div class="kpi-icon" style="color:#2980b9"><?= svg('comments') ?></div><div><div class="muted">Kommentare</div><div class="metric"><?= $stats['comments_total'] ?></div></div></div></div>
        <div class="card"><div class="kpi"><div class="kpi-icon" style="color:#8e44ad"><?= svg('users') ?></div><div><div class="muted">Benutzer</div><div class="metric"><?= $stats['users_total'] ?></div></div></div></div>
      </section>

      <div class="dashboard-grid">
        <section class="panel">
          <h3><?= htmlspecialchars($i18n->t('dashboard.panel_latest_posts')) ?></h3>
          <ul class="list">
            <?php foreach ($latestPosts as $p): ?>
              <li>
                <span><strong>#<?= $p['id'] ?></strong> <?= htmlspecialchars($p['title']) ?></span>
                <span>
                  <span class="badge"><?= $p['status'] ?></span>
                  <a class="btn btn-sm" style="margin-left:10px" href="/article.php?id=<?= $p['id'] ?>">Öffnen <?= svg('open','icon-14') ?></a>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>

        <section class="panel">
          <h3><?= svg('comments', 'icon-18') ?> Neueste Kommentare</h3>
          <ul class="list">
            <?php if (empty($latestComments)): ?>
              <li class="muted">Noch keine Kommentare.</li>
            <?php else: ?>
              <?php foreach ($latestComments as $c): ?>
                <li style="flex-direction: column; align-items: flex-start;">
                  <div><strong><?= htmlspecialchars($c['author_name']) ?></strong> <small class="muted"><?= date('d.m.Y', strtotime($c['created_at'])) ?></small></div>
                  <span class="comment-preview"><?= htmlspecialchars(mb_strimwidth($c['content'], 0, 60, "...")) ?></span>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </section>

        <section class="panel span-full">
          <h3><?= svg('server', 'icon-18') ?> System Information</h3>
          <table class="system-table">
            <tr>
              <td class="muted">Server Software & PHP</td>
              <td><?= $_SERVER['SERVER_SOFTWARE'] ?> | PHP <?= PHP_VERSION ?></td>
            </tr>
            <tr>
              <td class="muted">Datenbank Version</td>
              <td><?= $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) ?></td>
            </tr>
            <tr>
              <td class="muted">Konfiguration schreibbar?</td>
              <td><?= is_writable(__DIR__ . '/../config/config.ini') ? 'Ja' : 'Nein' ?></td>
            </tr>
          </table>
        </section>
      </div>
    </main>
  </div>
</body>
</html>