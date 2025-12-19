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
try { $latestPosts = $pdo->query("SELECT id, title, status, created_at FROM posts ORDER BY created_at DESC LIMIT 8")->fetchAll(); } catch (Throwable $e) {}
$admin = $_SESSION['admin'] ?? ['username' => 'admin'];

function svg($name, $cls='icon-18') {
  $icons = [
    'posts' => '<path d="M6 3h7a2 2 0 0 1 2 2v12l-4-2-4 2V5a2 2 0 0 1 2-2z"/>',
    'published' => '<path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>',
    'drafts' => '<path d="M3 6h12v12H3z"/><path d="M7 3h12v12h-4" fill="none"/>',
    'comments' => '<path d="M21 15a4 4 0 0 1-4 4H7l-4 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z" fill="none"/>',
    'categories' => '<path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z"/>',
    'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4h-2a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>',
    'open' => '<path d="M5 12h11"/><path d="M12 5l7 7-7 7" />',
    'new' => '<path d="M12 5v14M5 12h14" />',
    'manage' => '<path d="M4 6h16M4 12h10M4 18h16" />',
    'file' => '<path d="M14 3H6a2 2 0 0 0-2 2v14l4-2 4 2 4-2 4 2V9z" fill="none"/>',
    'settings' => '<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06-.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06A2 2 0 1 1 7.04 3.3l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c0 .67.39 1.27 1 1.51.64.26 1.09.88 1.09 1.6s-.45 1.34-1.09 1.6c-.61 .24-1 .84-1 1.29z" fill="none"/>',
    'tags' => '<path d="M20 10V4h-6l-8 8 6 6 8-8z"/><circle cx="14" cy="6" r="1"/>',
    'comments2' => '<path d="M21 15a4 4 0 0 1-4 4H7l-4 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z" fill="none"/>'
  ];
  $path = $icons[$name] ?? '';
  return '<svg class="'.$cls.'" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.5" aria-hidden="true">'.$path.'</svg>';
}
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
        <a href="/admin/dashboard.php"><?= htmlspecialchars($i18n->t('dashboard.title')) ?></a>
        <a href="/admin/posts.php"><?= htmlspecialchars($i18n->t('dashboard.quick_manage_posts')) ?></a>
        <a href="/admin/comments.php"><?= htmlspecialchars($i18n->t('dashboard.quick_comments')) ?></a>
        <a href="/admin/files.php"><?= htmlspecialchars($i18n->t('dashboard.quick_files')) ?></a>
        <a href="/admin/categories.php"><?= htmlspecialchars($i18n->t('dashboard.quick_categories')) ?></a>
        <a href="/admin/settings.php"><?= htmlspecialchars($i18n->t('dashboard.quick_settings')) ?></a>
        <a href="/admin/logout.php"><?= htmlspecialchars($i18n->t('common.logout')) ?></a>
      </nav>
    </aside>
    <main class="admin-content">
      <h1 style="margin-top:0"><?= htmlspecialchars($i18n->t('dashboard.title')) ?></h1>
      <p class="muted"><?= htmlspecialchars($i18n->t('dashboard.logged_in_as', ['{user}' => (string)($admin['username'] ?? 'admin')])) ?></p>

      <section class="cards">
        <div class="card">
          <div class="kpi">
            <div class="kpi-icon"><?= svg('posts') ?></div>
            <div>
              <div class="muted"><?= htmlspecialchars($i18n->t('dashboard.cards_posts_total')) ?></div>
              <div class="metric"><?= $stats['posts_total'] ?></div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="kpi">
            <div class="kpi-icon"><?= svg('published') ?></div>
            <div>
              <div class="muted"><?= htmlspecialchars($i18n->t('dashboard.cards_published')) ?></div>
              <div class="metric"><?= $stats['posts_published'] ?></div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="kpi">
            <div class="kpi-icon"><?= svg('drafts') ?></div>
            <div>
              <div class="muted"><?= htmlspecialchars($i18n->t('dashboard.cards_drafts')) ?></div>
              <div class="metric"><?= $stats['posts_drafts'] ?></div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="kpi">
            <div class="kpi-icon"><?= svg('comments') ?></div>
            <div>
              <div class="muted"><?= htmlspecialchars($i18n->t('dashboard.cards_comments')) ?></div>
              <div class="metric"><?= $stats['comments_total'] ?></div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="kpi">
            <div class="kpi-icon"><?= svg('categories') ?></div>
            <div>
              <div class="muted"><?= htmlspecialchars($i18n->t('dashboard.cards_categories')) ?></div>
              <div class="metric"><?= $stats['categories_total'] ?></div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="kpi">
            <div class="kpi-icon"><?= svg('users') ?></div>
            <div>
              <div class="muted"><?= htmlspecialchars($i18n->t('dashboard.cards_users')) ?></div>
              <div class="metric"><?= $stats['users_total'] ?></div>
            </div>
          </div>
        </div>
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
                    <strong>#<?= (int)$p['id'] ?></strong>
                    <?= htmlspecialchars((string)($p['title'] ?? '')) ?>
                  </span>
                  <span>
                    <span class="badge"><?= htmlspecialchars((string)($p['status'] ?? '')) ?></span>
                    <span class="muted" style="margin-left:8px;">
                      <?= htmlspecialchars(date($i18n->t('dashboard.date_fmt'), strtotime((string)$p['created_at']))) ?>
                    </span>
                    <a class="btn" style="margin-left:10px" href="/admin/post-edit.php?id=<?= (int)$p['id'] ?>">
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
            <a href="/admin/post-create.php"><?= svg('new','icon-18') ?> <?= htmlspecialchars($i18n->t('dashboard.quick_new_post')) ?></a>
            <a href="/admin/posts.php"><?= svg('manage','icon-18') ?> <?= htmlspecialchars($i18n->t('dashboard.quick_manage_posts')) ?></a>
            <a href="/admin/comments.php"><?= svg('comments2','icon-18') ?> <?= htmlspecialchars($i18n->t('dashboard.quick_comments')) ?></a>
            <a href="/admin/files.php"><?= svg('file','icon-18') ?> <?= htmlspecialchars($i18n->t('dashboard.quick_files')) ?></a>
            <a href="/admin/categories.php"><?= svg('tags','icon-18') ?> <?= htmlspecialchars($i18n->t('dashboard.quick_categories')) ?></a>
            <a href="/admin/settings.php"><?= svg('settings','icon-18') ?> <?= htmlspecialchars($i18n->t('dashboard.quick_settings')) ?></a>
          </div>
        </section>
      </div>
    </main>
  </div>
</body>
</html>
