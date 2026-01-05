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
        // FIX: Zugriff über 'common.'
        $error = $i18n->t('common.error_fill_fields') ?? "Bitte Namen und Kommentar ausfüllen.";
    } elseif ($spam_ans !== ($_SESSION['spam_a'] + $_SESSION['spam_b'])) {
        // FIX: Zugriff über 'common.'
        $error = $i18n->t('common.error_spam_wrong') ?? "Spam-Schutz: Falsches Ergebnis.";
    } else {
        $stmt = $pdo->prepare('INSERT INTO comments (post_id, author_name, author_email, content, status, created_at) VALUES (?, ?, ?, ?, "pending", NOW())');
        $stmt->execute([$id, $name, $email, $content]);
        // FIX: Zugriff über 'common.'
        $msg = $i18n->t('common.success_comment_pending') ?? "Danke! Dein Kommentar wird nach Prüfung freigeschaltet.";
        $_SESSION['spam_a'] = rand(1, 10);
        $_SESSION['spam_b'] = rand(1, 10);
    }
}

$stmt = $pdo->prepare('SELECT author_name, content, created_at FROM comments WHERE post_id = ? AND status = "approved" ORDER BY created_at DESC');
$stmt->execute([$id]);
$comments = $stmt->fetchAll();

$settings = [];
try {
    foreach ($pdo->query("SELECT * FROM settings")->fetchAll() as $row) { 
        $settings[$row['setting_key']] = $row['setting_value']; 
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($i18n->locale()) ?>" data-theme="<?= ($settings['dark_mode_enabled'] ?? '0') === '1' ? 'light' : 'light' ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($post['title']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/assets/styles/main.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/monokai-sublime.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <style>
    body { margin: 0; padding: 0; background-color: #f0f2f5; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; transition: background 0.3s; }
    [data-theme="dark"] body { background-color: #18191a; color: #e4e6eb; }
    
    .top-nav { background-color: #1877f2; color: white; padding: 12px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); width: 100%; margin-bottom: 30px; }
    .nav-container { max-width: 1200px; margin: 0 auto; width: 95%; display: flex; justify-content: space-between; align-items: center; }
    .brand { font-size: 24px; font-weight: bold; text-decoration: none; color: white; }
    .btn-home { background-color: rgba(255,255,255,0.2); color: white; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; transition: background 0.2s; }
    .btn-home:hover { background-color: rgba(255,255,255,0.3); }
    
    .theme-toggle { background: none; border: 1px solid rgba(255,255,255,0.3); color: white; padding: 6px 12px; border-radius: 20px; cursor: pointer; font-size: 1.2rem; }

    .container { max-width: 1200px; margin: 0 auto; width: 95%; padding-bottom: 50px; }
    .article { background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    [data-theme="dark"] .article { background: #242526; color: #e4e6eb; }
    
    .article-meta { color: #65676b; font-size: 0.9rem; margin-bottom: 15px; display: flex; gap: 15px; }
    [data-theme="dark"] .article-meta { color: #b0b3b8; }
    
    .cat-badge { background: #e7f3ff; color: #1877f2; padding: 2px 8px; border-radius: 4px; font-weight: bold; font-size: 0.8rem; }
    
    .article-teaser { font-size: 1.25rem; line-height: 1.6; color: #1c1e21; font-weight: 500; margin: 20px 0 30px 0; padding: 20px; background: #f0f2f5; border-radius: 8px; border-left: 6px solid #1877f2; }
    [data-theme="dark"] .article-teaser { background: #3a3b3c; color: #e4e6eb; }
    
    .article-body { font-size: 1.1rem; line-height: 1.7; color: #1c1e21; }
    [data-theme="dark"] .article-body { color: #e4e6eb; }
    
    .article-body h1, .article-body h2, .article-body h3 { color: inherit; }
    .article-body img { max-width: 100%; border-radius: 8px; }
    
    .article-body pre {
        background: #23241f !important;
        color: #f8f8f2 !important;
        padding: 15px;
        border-radius: 8px;
        overflow-x: auto;
        font-family: 'Fira Code', Consolas, monospace;
        margin: 20px 0;
        border: 1px solid #333;
    }
    
    .article-body pre code {
        background: transparent !important;
        padding: 0 !important;
        border: none !important;
        color: inherit !important;
        font-family: inherit;
        font-size: 0.95rem;
    }

    .article-body p code, .article-body li code {
        background: rgba(0,0,0,0.1);
        padding: 2px 5px;
        border-radius: 4px;
        color: #d14; 
        font-size: 0.9em;
        font-family: 'Fira Code', Consolas, monospace;
    }
    [data-theme="dark"] .article-body p code { background: rgba(255,255,255,0.1); color: #ff6b6b; }

    .comments-section { margin-top: 50px; border-top: 1px solid #ced0d4; padding-top: 30px; }
    .comment-item { background: #f0f2f5; padding: 15px; border-radius: 18px; margin-bottom: 10px; display: inline-block; min-width: 300px; }
    [data-theme="dark"] .comment-item { background: #3a3b3c; color: #e4e6eb; }
    
    .comment-author { font-weight: bold; color: #050505; font-size: 0.9rem; }
    [data-theme="dark"] .comment-author { color: #e4e6eb; }
    
    .comment-form { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #ced0d4; margin-top: 30px; }
    [data-theme="dark"] .comment-form { background: #242526; border-color: #3e4042; }
    
    .btn-submit { background: #1877f2; color: white; padding: 10px 20px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; width: 100%; }
  </style>
</head>
<body>

  <nav class="top-nav">
    <div class="nav-container">
      <a href="/" class="brand"><?= htmlspecialchars($settings['blog_title'] ?? 'PiperBlog') ?></a>
      <div style="display:flex; gap:10px; align-items:center;">
        <?php if (($settings['dark_mode_enabled'] ?? '0') === '1'): ?>
            <button id="theme-toggle" class="theme-toggle">🌓</button>
        <?php endif; ?>
        <a href="/" class="btn-home"><?= htmlspecialchars($i18n->t('common.nav_home') ?? 'Startseite') ?></a>
      </div>
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
        <h1 style="font-size: 2.5rem; margin: 0 0 20px 0; color: inherit;"><?= htmlspecialchars($post['title']) ?></h1>
      </header>
      
      <?php if (!empty($post['hero_image'])): ?>
        <img src="/uploads/<?= htmlspecialchars($post['hero_image']) ?>" style="width:100%; height:auto; border-radius:8px; margin-bottom:20px;">
      <?php endif; ?>

      <?php if (!empty($post['excerpt'])): ?>
        <div class="article-teaser">
            <?= nl2br(htmlspecialchars($post['excerpt'])) ?>
        </div>
      <?php endif; ?>

      <div class="article-body">
          <?= $parsedown->text($post['content']) ?>
      </div>
      
      <?php if (!empty($post['download_file'])): ?>
        <div style="margin-top:30px; border-top:1px solid #eee; padding-top:20px;">
            <a href="/uploads/<?= htmlspecialchars($post['download_file']) ?>" class="btn-submit" style="text-decoration:none; display:inline-block; width:auto;" download>📥 <?= htmlspecialchars($i18n->t('common.download_btn') ?? 'Datei Downloaden') ?></a>
        </div>
      <?php endif; ?>

      <section class="comments-section">
        <h3><?= htmlspecialchars($i18n->t('common.comments_headline') ?? 'Kommentare') ?> (<?= count($comments) ?>)</h3>
        
        <?php if($msg): ?><div style="background:#d1e7dd; color:#0f5132; padding:15px; border-radius:6px; margin-bottom:20px;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if($error): ?><div style="background:#f8d7da; color:#842029; padding:15px; border-radius:6px; margin-bottom:20px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <?php foreach ($comments as $c): ?>
            <div style="margin-bottom: 15px;">
                <div class="comment-item">
                    <div class="comment-author"><?= htmlspecialchars($c['author_name']) ?></div>
                    <div style="color: inherit;"><?= nl2br(htmlspecialchars($c['content'])) ?></div>
                </div>
                <div style="font-size: 0.75rem; color: #65676b; margin-left: 12px;"> <?= htmlspecialchars($i18n->t('common.comment_date_at') ?? 'am') ?> <?= date('d.m.Y', strtotime($c['created_at'])) ?></div>
            </div>
        <?php endforeach; ?>

        <div class="comment-form">
            <h4 style="margin-top:0;"><?= htmlspecialchars($i18n->t('common.write_comment_headline') ?? 'Einen Kommentar schreiben') ?></h4>
            <form method="POST">
                <input type="hidden" name="action" value="comment">
                <input type="text" name="name" placeholder="<?= htmlspecialchars($i18n->t('common.placeholder_name') ?? 'Dein Name') ?>" required style="width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid #ced0d4; border-radius: 6px; box-sizing: border-box;">
                <textarea name="content" rows="3" placeholder="<?= htmlspecialchars($i18n->t('common.placeholder_comment') ?? 'Dein Kommentar...') ?>" required style="width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid #ced0d4; border-radius: 6px; box-sizing: border-box; resize: none;"></textarea>
                
                <div style="background: #f0f2f5; padding: 10px; border-radius: 6px; margin-bottom: 10px; font-size: 0.9rem; color:#333;">
                    <?= sprintf($i18n->t('common.spam_protection') ?? 'Spam-Schutz: Was ist %d + %d?', $_SESSION['spam_a'], $_SESSION['spam_b']) ?>
                    <input type="number" name="spam_ans" required style="width: 60px; padding: 5px; margin-left: 10px; border: 1px solid #ced0d4; border-radius: 4px;">
                </div>
                
                <button type="submit" class="btn-submit"><?= htmlspecialchars($i18n->t('common.btn_submit_comment') ?? 'Kommentieren') ?></button>
            </form>
        </div>
      </section>
    </article>
  </main>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/dockerfile.min.js"></script>
  <script>
    hljs.highlightAll();

    const toggleBtn = document.getElementById('theme-toggle');
    if (toggleBtn) {
        const html = document.documentElement;
        const savedTheme = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        toggleBtn.addEventListener('click', () => {
            const current = html.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
        });
    }
  </script>
</body>
</html>