<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin'])) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../src/App/Database.php';
use App\Database;

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$dbCfg = $ini['database'] ?? [];

$pdo = null;
try {
    $db  = new Database($dbCfg);
    $pdo = $db->pdo();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<pre style="padding:16px">DB-Verbindung fehlgeschlagen. Bitte Installer/Config prüfen.\n' . htmlspecialchars($e->getMessage()) . '</pre>';
    exit;
}

// Stats
$stats = [
    'posts_total'      => 0,
    'posts_published'  => 0,
    'posts_drafts'     => 0,
    'comments_total'   => 0,
    'categories_total' => 0,
    'users_total'      => 0,
];

$stats['posts_total']      = (int)$pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
$stats['posts_published']  = (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE status='published'")->fetchColumn();
// Drafts: falls kein Status 'draft' existiert, bleibt Wert ggf. 0 – ist OK
$stats['posts_drafts']     = (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE status='draft'")->fetchColumn();

// Optional-Tabellen robust zählen
try { $stats['comments_total']   = (int)$pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn(); } catch (Throwable $e) {}
try { $stats['categories_total'] = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn(); } catch (Throwable $e) {}
try { $stats['users_total']      = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(); } catch (Throwable $e) {}

// Letzte Beiträge
$latestPosts = [];
try {
    $stmt = $pdo->query(
        "SELECT id, title, status, created_at FROM posts ORDER BY created_at DESC LIMIT 8"
    );
    $latestPosts = $stmt->fetchAll();
} catch (Throwable $e) {
    $latestPosts = [];
}

$admin = $_SESSION['admin'] ?? ['username' => 'admin'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>Dashboard – PiperBlog Admin</title>
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
      <h1 style="margin-top:0">Dashboard</h1>
      <p class="muted">Angemeldet als <?= htmlspecialchars((string)($admin['username'] ?? 'admin')) ?></p>

      <section class="cards">
        <div class="card"><div class="muted">Beiträge gesamt</div><div class="metric"><?= $stats['posts_total'] ?></div></div>
        <div class="card"><div class="muted">Veröffentlicht</div><div class="metric"><?= $stats['posts_published'] ?></div></div>
        <div class="card"><div class="muted">Entwürfe</div><div class="metric"><?= $stats['posts_drafts'] ?></div></div>
        <div class="card"><div class="muted">Kommentare</div><div class="metric"><?= $stats['comments_total'] ?></div></div>
        <div class="card"><div class="muted">Kategorien</div><div class="metric"><?= $stats['categories_total'] ?></div></div>
        <div class="card"><div class="muted">Benutzer</div><div class="metric"><?= $stats['users_total'] ?></div></div>
      </section>

      <div class="grid">
        <section class="panel">
          <h3>Neueste Beiträge</h3>
          <?php if (empty($latestPosts)): ?>
            <p class="muted">Keine Beiträge gefunden.</p>
          <?php else: ?>
            <ul class="list">
              <?php foreach ($latestPosts as $p): ?>
                <li>
                  <span>
                    <strong>#<?= (int)$p['id'] ?></strong>
                    <?= htmlspecialchars((string)$p['title'] ?? '') ?>
                  </span>
                  <span>
                    <span class="badge"><?= htmlspecialchars((string)($p['status'] ?? '')) ?></span>
                    <span class="muted" style="margin-left:8px;"><?= htmlspecialchars(date('d.m.Y', strtotime((string)$p['created_at']))) ?></span>
                    <a class="btn" style="margin-left:10px" href="/admin/post-edit.php?id=<?= (int)$p['id'] ?>">Öffnen</a>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>

        <section class="panel">
          <h3>Schnellzugriff</h3>
          <div class="quick">
            <a href="/admin/post-create.php">+ Neuer Beitrag</a>
            <a href="/admin/posts.php">Beiträge verwalten</a>
            <a href="/admin/comments.php">Kommentare</a>
            <a href="/admin/files.php">Dateien</a>
            <a href="/admin/categories.php">Kategorien</a>
            <a href="/admin/settings.php">Einstellungen</a>
          </div>
        </section>
      </div>
    </main>
  </div>
</body>
</html>
