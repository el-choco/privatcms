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
    'comments_total'   => 0,
    'categories_total' => 0,
    'users_total'      => 0,
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
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($i18n->locale()) ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($i18n->t('dashboard.title')) ?> – PiperBlog Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/admin/assets/styles/admin.css" rel="stylesheet">
  <style>
    .cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:16px}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px}
    .metric{font-size:28px;font-weight:700;margin-top:4px}
    .muted{color:#6b7280;font-size:12px}
    .grid{display:grid;grid-template-columns:1fr;gap:16px}
    @media(min-width:960px){.grid{grid-template-columns:2fr 1fr}}
    .panel{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px}
    .panel h3{margin:0 0 10px;font-size:16px}
    .list{list-style:none;margin:0;padding:0}
    .list li{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f1f5f9}
    .list li:last-child{border-bottom:none}
    .badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;border:1px solid #e5e7eb}
    .btn{display:inline-block;padding:8px 12px;border-radius:8px;background:#1877f2;color:#fff;text-decoration:none}
    .quick{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px}
    .quick a{display:block;text-align:center;padding:10px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;text-decoration:none;color:#111827}
    .quick a:hover{background:#f8fafc}
  </style>
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
        <div class="card"><div class="muted"><?= htmlspecialchars($i18n->t('dashboard.cards_posts_total')) ?></div><div class="metric"><?= $stats['posts_total'] ?></div></div>
        <div class="card"><div class="muted"><?= htmlspecialchars($i18n->t('dashboard.cards_published')) ?></div><div class="metric"><?= $stats['posts_published'] ?></div></div>
        <div class="card"><div class="muted"><?= htmlspecialchars($i18n->t('dashboard.cards_drafts')) ?></div><div class="metric"><?= $stats['posts_drafts'] ?></div></div>
        <div class="card"><div class="muted"><?= htmlspecialchars($i18n->t('dashboard.cards_comments')) ?></div><div class="metric"><?= $stats['comments_total'] ?></div></div>
        <div class="card"><div class="muted"><?= htmlspecialchars($i18n->t('dashboard.cards_categories')) ?></div><div class="metric"><?= $stats['categories_total'] ?></div></div>
        <div class="card"><div class="muted"><?= htmlspecialchars($i18n->t('dashboard.cards_users')) ?></div><div class="metric"><?= $stats['users_total'] ?></div></div>
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
                    <a class="btn" style="margin-left:10px" href="/article.php?id=<?= (int)$p['id'] ?>"><?= htmlspecialchars($i18n->t('dashboard.button_open')) ?></a>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>

        <section class="panel">
          <h3><?= htmlspecialchars($i18n->t('dashboard.panel_quick')) ?></h3>
          <div class="quick">
            <a href="/admin/post-create.php"><?= htmlspecialchars($i18n->t('dashboard.quick_new_post')) ?></a>
            <a href="/admin/posts.php"><?= htmlspecialchars($i18n->t('dashboard.quick_manage_posts')) ?></a>
            <a href="/admin/comments.php"><?= htmlspecialchars($i18n->t('dashboard.quick_comments')) ?></a>
            <a href="/admin/files.php"><?= htmlspecialchars($i18n->t('dashboard.quick_files')) ?></a>
            <a href="/admin/categories.php"><?= htmlspecialchars($i18n->t('dashboard.quick_categories')) ?></a>
            <a href="/admin/settings.php"><?= htmlspecialchars($i18n->t('dashboard.quick_settings')) ?></a>
          </div>
        </section>
      </div>
    </main>
  </div>
</body>
</html>
