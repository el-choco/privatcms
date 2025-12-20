<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../src/App/Database.php';
require_once __DIR__ . '/../src/App/I18n.php';
use App\Database;
use App\I18n;

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$i18n = I18n::fromConfig($ini, $_GET['lang'] ?? null);
$db  = new Database($ini['database'] ?? []);
$pdo = $db->pdo();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Post laden
$stmt = $pdo->prepare('SELECT * FROM posts WHERE id=? AND status="published"');
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) { http_response_code(404); echo "Not found"; exit; }

// 2. Kommentar-Logik (Speichern)
$msg = '';
$error = '';

// Einfacher Spam-Schutz: Mathe-Aufgabe generieren falls nicht vorhanden
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
        $error = $i18n->t('comments.error_required');
    } elseif ($spam_ans !== ($_SESSION['spam_a'] + $_SESSION['spam_b'])) {
        $error = "Spam-Schutz: Falsches Ergebnis.";
    } else {
        $stmt = $pdo->prepare('INSERT INTO comments (post_id, author_name, author_email, content, status, created_at) VALUES (?, ?, ?, ?, "pending", NOW())');
        $stmt->execute([$id, $name, $email, $content]);
        $msg = $i18n->t('comments.success_saved');
        // Mathe-Aufgabe nach Erfolg erneuern
        $_SESSION['spam_a'] = rand(1, 10);
        $_SESSION['spam_b'] = rand(1, 10);
    }
}

// 3. Freigeschaltete Kommentare laden
$stmt = $pdo->prepare('SELECT author_name, content, created_at FROM comments WHERE post_id = ? AND status = "approved" ORDER BY created_at DESC');
$stmt->execute([$id]);
$comments = $stmt->fetchAll();

?><!DOCTYPE html>
<html lang="<?= htmlspecialchars($i18n->locale()) ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($post['title']) ?> - <?= htmlspecialchars($ini['app']['title'] ?? 'PiperBlog') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/assets/styles/main.css" rel="stylesheet">
  <style>
    .comment-item { border-bottom: 1px solid #eee; padding: 1rem 0; }
    .comment-meta { font-size: 0.85rem; color: #666; margin-bottom: 0.5rem; }
    .comment-form { background: #f9f9f9; padding: 1.5rem; border-radius: 8px; margin-top: 2rem; }
    .comment-form input, .comment-form textarea { width: 100%; margin-bottom: 1rem; padding: 8px; border: 1px solid #ccc; border-radius: 4px; display: block; }
    .comment-form label { display: block; font-weight: bold; margin-bottom: 5px; }
    .msg-success { color: green; padding: 10px; border: 1px solid green; margin-bottom: 1rem; }
    .msg-error { color: red; padding: 10px; border: 1px solid red; margin-bottom: 1rem; }
  </style>
</head>
<body>
  <main class="container">
    <article class="article">
      <h1><?= htmlspecialchars($post['title']) ?></h1>
      <?php if (!empty($post['hero_image'])): ?>
        <img src="/uploads/images/<?= htmlspecialchars($post['hero_image']) ?>" alt="" class="article-hero">
      <?php endif; ?>
      <div class="article-meta">
        <span><?= date($i18n->t('common.date_fmt') ?: 'd.m.Y H:i', strtotime($post['created_at'])) ?></span>
      </div>
      <div class="article-body"><?= $post['content'] ?></div>
    </article>

    <section class="comments">
      <hr>
      <h3><?= htmlspecialchars($i18n->t('comments.title')) ?> (<?= count($comments) ?>)</h3>

      <?php if ($msg): ?><div class="msg-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="msg-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <div class="comments-list">
        <?php if (empty($comments)): ?>
          <p class="muted"><?= htmlspecialchars($i18n->t('comments.no_comments')) ?></p>
        <?php else: ?>
          <?php foreach ($comments as $c): ?>
            <div class="comment-item">
              <div class="comment-meta">
                <strong><?= htmlspecialchars($c['author_name']) ?></strong> • 
                <?= date($i18n->t('common.date_fmt') ?: 'd.m.Y', strtotime($c['created_at'])) ?>
              </div>
              <div class="comment-text"><?= nl2br(htmlspecialchars($c['content'])) ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="comment-form">
        <h4>Kommentar schreiben</h4>
        <form action="?id=<?= $id ?>&lang=<?= $i18n->locale() ?>" method="POST">
          <input type="hidden" name="action" value="comment">
          
          <label><?= htmlspecialchars($i18n->t('comments.label_author')) ?>*</label>
          <input type="text" name="name" required placeholder="Dein Name">

          <label>E-Mail (optional, wird nicht angezeigt)</label>
          <input type="email" name="email" placeholder="Deine E-Mail">

          <label><?= htmlspecialchars($i18n->t('comments.label_content')) ?>*</label>
          <textarea name="content" rows="5" required placeholder="Dein Kommentar..."></textarea>

          <label>Spam-Schutz: Was ist <?= $_SESSION['spam_a'] ?> + <?= $_SESSION['spam_b'] ?>?</label>
          <input type="number" name="spam_ans" required>

          <button type="submit" class="btn">Absenden</button>
        </form>
      </div>
    </section>
  </main>
</body>
</html>