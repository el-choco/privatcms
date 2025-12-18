<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../src/App/Database.php';
require_once __DIR__ . '/../src/App/Router.php';
require_once __DIR__ . '/../src/App/CSRF.php';

use App\Database;
use App\CSRF;

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED);
$db  = new Database($ini['database']);
$pdo = $db->pdo();

$stmt = $pdo->query('
  SELECT p.id, p.title, p.excerpt, p.hero_image, p.created_at, c.name AS category
  FROM posts p
  LEFT JOIN categories c ON c.id = p.category_id
  WHERE p.status = "published"
  ORDER BY p.created_at DESC
  LIMIT 24
');
$posts = $stmt->fetchAll();
?><!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($ini['app']['title'] ?? 'PiperBlog') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/assets/styles/main.css" rel="stylesheet">
  <link href="/assets/styles/cards.css" rel="stylesheet">
</head>
<body>
  <header class="site-header">
    <div class="container">
      <h1 class="site-title"><?= htmlspecialchars($ini['app']['title'] ?? 'PiperBlog') ?></h1>
      <nav class="site-nav"><a href="/admin/" class="btn btn-sm">Admin</a></nav>
    </div>
  </header>

  <main class="container">
    <!-- Collapsible composer (hidden by default, to be wired later) -->
    <section class="composer" hidden>
      <button class="btn">Neuen Beitrag erstellen</button>
      <div class="composer-body" hidden>
        <form method="post" action="/admin/post-create.php">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(CSRF::token()) ?>">
          <input class="input" type="text" name="title" placeholder="Titel">
          <textarea class="input" rows="5" name="content" placeholder="Dein Text ..."></textarea>
          <button class="btn">Speichern</button>
        </form>
      </div>
    </section>

    <section class="posts-grid">
      <?php foreach ($posts as $p): ?>
        <article class="post-card">
          <a class="post-card__media" href="/article.php?id=<?= (int)$p['id'] ?>">
            <?php if (!empty($p['hero_image'])): ?>
              <img src="/uploads/images/<?= htmlspecialchars($p['hero_image']) ?>" alt="<?= htmlspecialchars($p['title']) ?>" loading="lazy">
            <?php else: ?>
              <div class="post-card__placeholder">No image</div>
            <?php endif; ?>
          </a>
          <div class="post-card__content">
            <h2 class="post-card__title"><a href="/article.php?id=<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['title']) ?></a></h2>
            <div class="post-card__meta">
              <?php if (!empty($p['category'])): ?><span class="badge"><?= htmlspecialchars($p['category']) ?></span><?php endif; ?>
              <span class="date"><?= date('d.m.Y', strtotime($p['created_at'])) ?></span>
            </div>
            <p class="post-card__excerpt"><?= htmlspecialchars($p['excerpt'] ?? '') ?></p>
            <a class="post-card__cta" href="/article.php?id=<?= (int)$p['id'] ?>">Weiterlesen</a>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  </main>
</body>
</html>
