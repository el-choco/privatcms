<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../src/App/Database.php';
require_once __DIR__ . '/../src/App/I18n.php';
require_once __DIR__ . '/../src/App/Parsedown.php';

use App\Database;
use App\I18n;

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$i18n = I18n::fromConfig($ini, $_GET['lang'] ?? null);
$db  = new Database($ini['database'] ?? []);
$pdo = $db->pdo();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare('SELECT p.*, c.name as category_name FROM posts p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id=? AND p.status="published"');
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) { http_response_code(404); echo "Not found"; exit; }

$parsedown = new Parsedown();
$parsedown->setSafeMode(false); 

$msg = '';
$error = '';

if (!isset($_SESSION['spam_a'])) {
    $_SESSION['spam_a'] = rand(1, 10);
    $_SESSION['spam_b'] = rand(1, 10);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'comment') {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));
    $spam_ans = (int)($_POST['spam_ans'] ?? 0);

    if ($name === '' || $content === '') {
        $error = "Bitte Namen und Kommentar ausfüllen.";
    } elseif ($spam_ans !== ($_SESSION['spam_a'] + $_SESSION['spam_b'])) {
        $error = "Spam-Schutz: Falsches Ergebnis.";
    } else {
        $stmt = $pdo->prepare('INSERT INTO comments (post_id, author_name, author_email, content, status, created_at) VALUES (?, ?, ?, ?, "pending", NOW())');
        $stmt->execute([$id, $name, $email, $content]);
        $msg = "Danke! Dein Kommentar wird nach Prüfung freigeschaltet.";
        $_SESSION['spam_a'] = rand(1, 10);
        $_SESSION['spam_b'] = rand(1, 10);
    }
}

$stmt = $pdo->prepare('SELECT author_name, content, created_at FROM comments WHERE post_id = ? AND status = "approved" ORDER BY created_at DESC');
$stmt->execute([$id]);
$comments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($i18n->locale()) ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($post['title']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/assets/styles/main.css" rel="stylesheet">
  <style>
    body { margin: 0; padding: 0; background-color: #f0f2f5; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    
    /* Facebook Blauer Header */
    .top-nav { background-color: #1877f2; color: white; padding: 12px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); width: 100%; margin-bottom: 30px; }
    .nav-container { max-width: 1200px; margin: 0 auto; width: 95%; display: flex; justify-content: space-between; align-items: center; }
    .brand { font-size: 24px; font-weight: bold; text-decoration: none; color: white; }
    .btn-home { background-color: rgba(255,255,255,0.2); color: white; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; transition: background 0.2s; }
    .btn-home:hover { background-color: rgba(255,255,255,0.3); }

    .container { max-width: 1200px; margin: 0 auto; width: 95%; padding-bottom: 50px; }
    .article { background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .article-meta { color: #65676b; font-size: 0.9rem; margin-bottom: 15px; display: flex; gap: 15px; }
    .cat-badge { background: #e7f3ff; color: #1877f2; padding: 2px 8px; border-radius: 4px; font-weight: bold; font-size: 0.8rem; }
    
    .article-teaser { font-size: 1.25rem; line-height: 1.6; color: #1c1e21; font-weight: 500; margin: 20px 0 30px 0; padding: 20px; background: #f0f2f5; border-radius: 8px; border-left: 6px solid #1877f2; }
    .article-body { font-size: 1.1rem; line-height: 1.7; color: #1c1e21; }
    .article-body img { max-width: 100%; border-radius: 8px; }
    
    .comments-section { margin-top: 50px; border-top: 1px solid #ced0d4; padding-top: 30px; }
    .comment-item { background: #f0f2f5; padding: 15px; border-radius: 18px; margin-bottom: 10px; display: inline-block; min-width: 300px; }
    .comment-author { font-weight: bold; color: #050505; font-size: 0.9rem; }
    .comment-form { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #ced0d4; margin-top: 30px; }
    .btn-submit { background: #1877f2; color: white; padding: 10px 20px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; width: 100%; }
  </style>
</head>
<body>

  <nav class="top-nav">
    <div class="nav-container">
      <a href="/" class="brand">PiperBlog</a>
      <a href="/" class="btn-home">← Zur Startseite</a>
    </div>
  </nav>

  <main class="container">
    <article class="article">
      <header>
        <div class="article-meta">
            <span>📅 <?= date('d.m.Y', strtotime($post['created_at'])) ?></span>
            <?php if ($post['category_name']): ?>
                <span class="cat-badge"><?= htmlspecialchars($post['category_name']) ?></span>
            <?php endif; ?>
        </div>
        <h1 style="font-size: 2.5rem; margin: 0 0 20px 0; color: #050505;"><?= htmlspecialchars($post['title']) ?></h1>
      </header>
      
      <?php if (!empty($post['excerpt'])): ?>
        <div class="article-teaser">
            <?= nl2br(htmlspecialchars($post['excerpt'])) ?>
        </div>
      <?php endif; ?>

      <div class="article-body">
          <?= $parsedown->text($post['content']) ?>
      </div>

      <section class="comments-section">
        <h3>Kommentare (<?= count($comments) ?>)</h3>
        
        <?php foreach ($comments as $c): ?>
            <div style="margin-bottom: 15px;">
                <div class="comment-item">
                    <div class="comment-author"><?= htmlspecialchars($c['author_name']) ?></div>
                    <div style="color: #050505;"><?= nl2br(htmlspecialchars($c['content'])) ?></div>
                </div>
                <div style="font-size: 0.75rem; color: #65676b; margin-left: 12px;"> am <?= date('d.m.Y', strtotime($c['created_at'])) ?></div>
            </div>
        <?php endforeach; ?>

        <div class="comment-form">
            <h4 style="margin-top:0;">Einen Kommentar schreiben</h4>
            <form method="POST">
                <input type="hidden" name="action" value="comment">
                <input type="text" name="name" placeholder="Dein Name" required style="width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid #ced0d4; border-radius: 6px; box-sizing: border-box;">
                <textarea name="content" rows="3" placeholder="Dein Kommentar..." required style="width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid #ced0d4; border-radius: 6px; box-sizing: border-box; resize: none;"></textarea>
                
                <div style="background: #f0f2f5; padding: 10px; border-radius: 6px; margin-bottom: 10px; font-size: 0.9rem;">
                    Spam-Schutz: Was ist <?= $_SESSION['spam_a'] ?> + <?= $_SESSION['spam_b'] ?>?
                    <input type="number" name="spam_ans" required style="width: 60px; padding: 5px; margin-left: 10px; border: 1px solid #ced0d4; border-radius: 4px;">
                </div>
                
                <button type="submit" class="btn-submit">Kommentieren</button>
            </form>
        </div>
      </section>
    </article>
  </main>
</body>
</html>