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

// UTF-8 FIX
$pdo->exec("SET NAMES utf8mb4");

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
  <style>
    body { background-color: #f0f2f5; margin: 0; font-family: -apple-system, sans-serif; }
    
    /* Blauer Header */
    .site-header { background-color: #1877f2; color: white; padding: 12px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); width: 100%; margin-bottom: 30px; }
    .header-container { max-width: 1200px; margin: 0 auto; width: 95%; display: flex; justify-content: space-between; align-items: center; }
    .site-title { margin: 0; font-size: 24px; font-weight: bold; color: white; text-decoration: none; }
    .btn-admin { background-color: rgba(255,255,255,0.2); color: white; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; }

    .container { max-width: 1200px; margin: 0 auto; width: 95%; }

    /* Flexbox Grid System */
    .posts-grid { 
        display: flex; 
        flex-wrap: wrap; 
        gap: 25px; 
        margin-bottom: 50px;
    }

    /* Das "Highlight" (Der erste Beitrag) */
    .post-card:first-child {
        flex: 1 1 100%; /* Volle Breite */
        display: flex;
        flex-direction: row;
        min-height: 400px;
        border-top: 6px solid #1877f2;
    }
    
    .post-card:first-child .post-card__media { width: 60%; height: auto; }
    .post-card:first-child .post-card__content { width: 40%; padding: 40px; display: flex; flex-direction: column; justify-content: center; }
    .post-card:first-child .post-card__title { font-size: 2.2rem; }

    /* Die kleinen Cards (Alle danach) */
    .post-card {
        flex: 1 1 calc(33.333% - 25px); /* Drei pro Reihe */
        background: white;
        border-radius: 12px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        transition: transform 0.2s, box-shadow 0.2s;
        border-top: 4px solid #ccd0d5; /* Standard-Grau */
    }

    .post-card:hover { 
        transform: translateY(-5px); 
        box-shadow: 0 10px 20px rgba(0,0,0,0.1); 
        border-top-color: #1877f2; /* Wird beim Hover blau */
    }

    .post-card__media { width: 100%; height: 200px; background: #ddd; display: block; overflow: hidden; }
    .post-card__media img { width: 100%; height: 100%; object-fit: contain; transition: transform 0.5s; }
    .post-card:hover .post-card__media img { transform: scale(1.05); }

    .post-card__content { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
    .post-card__title { margin: 0 0 10px 0; font-size: 1.3rem; line-height: 1.3; }
    .post-card__title a { text-decoration: none; color: #1c1e21; }
    
    .post-card__meta { margin-bottom: 12px; display: flex; align-items: center; gap: 10px; font-size: 0.85rem; }
    .badge { background: #e7f3ff; color: #1877f2; padding: 3px 10px; border-radius: 5px; font-weight: bold; }
    .date { color: #65676b; }

    .post-card__excerpt { color: #4b4f56; line-height: 1.5; margin-bottom: 20px; font-size: 0.95rem; }
    .post-card__cta { 
        margin-top: auto; 
        background: #f0f2f5; 
        color: #1877f2; 
        text-align: center; 
        padding: 10px; 
        border-radius: 6px; 
        text-decoration: none; 
        font-weight: bold; 
        transition: background 0.2s;
    }
    .post-card__cta:hover { background: #1877f2; color: white; }

    /* Mobile Anpassung */
    @media (max-width: 900px) {
        .post-card:first-child { flex-direction: column; }
        .post-card:first-child .post-card__media, .post-card:first-child .post-card__content { width: 100%; }
        .post-card { flex: 1 1 calc(50% - 25px); }
    }
    @media (max-width: 600px) {
        .post-card { flex: 1 1 100%; }
    }
  </style>
</head>
<body>
  <header class="site-header">
    <div class="header-container">
      <a href="/" class="site-title"><?= htmlspecialchars($ini['app']['title'] ?? 'PiperBlog') ?></a>
      <nav class="site-nav"><a href="/admin/" class="btn-admin">Admin-Bereich</a></nav>
    </div>
  </header>

  <main class="container">
    <section class="posts-grid">
      <?php foreach ($posts as $p): ?>
        <article class="post-card">
          <a class="post-card__media" href="/article.php?id=<?= (int)$p['id'] ?>">
            <?php if (!empty($p['hero_image'])): ?>
              <img src="/uploads/images/<?= htmlspecialchars($p['hero_image']) ?>" alt="<?= htmlspecialchars($p['title']) ?>" loading="lazy">
            <?php else: ?>
              <div style="height:100%; background:#dfe3ee; display:flex; align-items:center; justify-content:center; color:#8b9dc3; font-weight:bold;">No Image</div>
            <?php endif; ?>
          </a>
          <div class="post-card__content">
            <div class="post-card__meta">
              <?php if (!empty($p['category'])): ?><span class="badge"><?= htmlspecialchars($p['category']) ?></span><?php endif; ?>
              <span class="date"><?= date('d.m.Y', strtotime($p['created_at'])) ?></span>
            </div>
            <h2 class="post-card__title"><a href="/article.php?id=<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['title']) ?></a></h2>
            <p class="post-card__excerpt"><?= htmlspecialchars($p['excerpt'] ?? '') ?></p>
            <a class="post-card__cta" href="/article.php?id=<?= (int)$p['id'] ?>">Weiterlesen</a>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  </main>
</body>
</html>