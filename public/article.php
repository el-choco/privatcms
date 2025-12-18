<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../src/App/Database.php';
use App\Database;

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED);
$db  = new Database($ini['database']);
$pdo = $db->pdo();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare('SELECT * FROM posts WHERE id=? AND status="published"');
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) { http_response_code(404); echo "Not found"; exit; }
?><!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($post['title']) ?> - <?= htmlspecialchars($ini['app']['title'] ?? 'PiperBlog') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/assets/styles/main.css" rel="stylesheet">
</head>
<body>
  <main class="container">
    <article class="article">
      <h1><?= htmlspecialchars($post['title']) ?></h1>
      <?php if (!empty($post['hero_image'])): ?>
        <img src="/uploads/images/<?= htmlspecialchars($post['hero_image']) ?>" alt="" class="article-hero">
      <?php endif; ?>
      <div class="article-meta">
        <span><?= date('d.m.Y H:i', strtotime($post['created_at'])) ?></span>
      </div>
      <div class="article-body"><?= $post['content'] ?></div>
    </article>

    <section class="comments">
      <h3>Kommentare</h3>
      <!-- TODO: render and post comments -->
    </section>
  </main>
</body>
</html>
