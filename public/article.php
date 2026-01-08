<?php
declare(strict_types=1);
session_start();

if (isset($_GET['lang']) && in_array($_GET['lang'], ['de', 'en', 'fr', 'es'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
$currentLang = $_SESSION['lang'] ?? 'de';

require_once __DIR__ . '/../src/App/Database.php';
require_once __DIR__ . '/../src/App/I18n.php';
require_once __DIR__ . '/../src/App/Parsedown.php';

use App\Database;
use App\I18n;

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];

$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$iniLang = file_exists($langFile) ? parse_ini_file($langFile, true) : [];

$t = [
    'date_format'   => $iniLang['common']['date_fmt'] ?? 'd.m.Y',
    'footer_total'  => $iniLang['frontend']['footer_stats_total'] ?? 'Total Visits',
    'footer_today'  => $iniLang['frontend']['footer_stats_today'] ?? 'Today',
    'stat_views'    => $iniLang['frontend']['stat_views'] ?? 'Views',
    'sb_cat_title'  => $iniLang['categories']['title'] ?? 'Categories',
    'sb_comm_title' => $iniLang['comments']['title'] ?? 'Comments',
    'sb_no_comm'    => $iniLang['comments']['no_comments'] ?? '-',
    'sb_tags_title' => $iniLang['frontend']['tags_title'] ?? 'Tags',
    'nav_contact'   => $iniLang['frontend']['nav_contact'] ?? 'Contact'
];

$sb_cat_title = $t['sb_cat_title'];
$sb_comm_title = $t['sb_comm_title'];
$sb_no_comm = $t['sb_no_comm'];
$t_by = $iniLang['frontend']['by'] ?? 'by';
$t_tags = $t['sb_tags_title'];

$i18n = I18n::fromConfig($ini, $_GET['lang'] ?? null);
$db  = new Database($ini['database'] ?? []);
$pdo = $db->pdo();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0 && !isset($_SESSION['viewed_post_' . $id])) {
    try {
        $pdo->prepare("UPDATE posts SET views = views + 1 WHERE id = ?")->execute([$id]);
        $_SESSION['viewed_post_' . $id] = true;
    } catch (Exception $e) {}
}

try {
    $totalViews = (int)$pdo->query("SELECT SUM(views) FROM daily_stats")->fetchColumn();
    $todayViews = (int)$pdo->query("SELECT views FROM daily_stats WHERE date = CURDATE()")->fetchColumn();
} catch (Exception $e) { $totalViews = 0; $todayViews = 0; }

$stmt = $pdo->prepare('SELECT p.*, c.name as category_name, u.username as author_name FROM posts p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN users u ON p.author_id = u.id WHERE p.id=? AND p.status="published"');
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) { http_response_code(404); echo "Not found"; exit; }

$stmtTags = $pdo->prepare("SELECT t.name, t.slug FROM tags t JOIN post_tags pt ON t.id = pt.tag_id WHERE pt.post_id = ? ORDER BY t.name ASC");
$stmtTags->execute([$id]);
$postTags = $stmtTags->fetchAll();

$cats = $pdo->query("SELECT c.id, c.name, COUNT(p.id) as count FROM categories c LEFT JOIN posts p ON p.category_id = c.id AND p.status = 'published' GROUP BY c.id ORDER BY c.name ASC")->fetchAll();
$latestComments = $pdo->query("SELECT c.content, c.author_name, c.created_at, p.id as post_id, p.title FROM comments c JOIN posts p ON c.post_id = p.id WHERE c.status = 'approved' ORDER BY c.created_at DESC LIMIT 5")->fetchAll();

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
        $error = $i18n->t('common.error_fill_fields') ?? "Please fill in name and comment.";
    } elseif ($spam_ans !== ($_SESSION['spam_a'] + $_SESSION['spam_b'])) {
        $error = $i18n->t('common.error_spam_wrong') ?? "Spam Check: Wrong answer.";
    } else {
        $stmt = $pdo->prepare('INSERT INTO comments (post_id, author_name, author_email, content, status, created_at) VALUES (?, ?, ?, ?, "pending", NOW())');
        $stmt->execute([$id, $name, $email, $content]);
        $msg = $i18n->t('common.success_comment_pending') ?? "Thanks! Your comment is awaiting moderation.";
        $_SESSION['spam_a'] = rand(1, 10);
        $_SESSION['spam_b'] = rand(1, 10);
    }
}

$stmt = $pdo->prepare('SELECT author_name, content, created_at FROM comments WHERE post_id = ? AND status = "approved" ORDER BY created_at ASC');
$stmt->execute([$id]);
$comments = $stmt->fetchAll();

$settings = [];
try {
    foreach ($pdo->query("SELECT * FROM settings")->fetchAll() as $row) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}

$languages = [
    'de' => ['label' => 'Deutsch',  'flag' => 'https://flagcdn.com/w40/de.png'],
    'en' => ['label' => 'English',  'flag' => 'https://flagcdn.com/w40/gb.png'],
    'fr' => ['label' => 'Français', 'flag' => 'https://flagcdn.com/w40/fr.png'],
    'es' => ['label' => 'Español',  'flag' => 'https://flagcdn.com/w40/es.png']
];
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
    :root { --bg-body: #f0f2f5; --bg-card: #ffffff; --text-main: #1c1e21; --text-muted: #65676b; --border: #ccd0d5; --primary: #1877f2; }
    [data-theme="dark"] { --bg-body: #18191a; --bg-card: #242526; --text-main: #e4e6eb; --text-muted: #b0b3b8; --border: #3e4042; --primary: #2d88ff; }
    
    body { background-color: var(--bg-body); color: var(--text-main); margin: 0; font-family: -apple-system, sans-serif; transition: background 0.3s, color 0.3s; scroll-behavior: smooth; }
    
    .top-nav { background-color: var(--primary); color: white; padding: 12px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); width: 100%; margin-bottom: 30px; max-width: 1567px; margin-right: auto; margin-left: auto; border-radius: 6px; }
    .nav-container { max-width: 1800px; margin: 0 auto; width: 95%; display: flex; justify-content: space-between; align-items: center; }
    .brand { font-size: 24px; font-weight: bold; text-decoration: none; color: white; }
    .btn-home { background-color: rgba(255,255,255,0.2); color: white; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; transition: background 0.2s; }
    .btn-contact { background-color: rgba(255,255,255,0.2); color: white; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 0.9rem; margin-right: 5px; }
    .btn-contact:hover { background-color: rgba(255,255,255,0.3); }
    .theme-toggle { background: none; border: 1px solid rgba(255,255,255,0.3); color: white; padding: 6px 12px; border-radius: 20px; cursor: pointer; font-size: 1.2rem; }

    .lang-dropdown { position: relative; }
    .lang-trigger { display: flex; align-items: center; gap: 6px; cursor: pointer; background: rgba(255,255,255,0.15); padding: 6px 10px; border-radius: 6px; color: white; font-weight: 600; font-size: 0.9rem; }
    .lang-trigger img { width: 20px; border-radius: 3px; }
    .lang-menu { display: none; position: absolute; right: 0; top: 120%; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); z-index: 1000; min-width: 150px; overflow: hidden; }
    .lang-menu.show { display: block; }
    .lang-option { display: flex; align-items: center; gap: 10px; padding: 10px 15px; color: var(--text-main); text-decoration: none; font-size: 0.95rem; }
    .lang-option:hover { background: var(--bg-body); color: var(--primary); }
    .lang-option img { width: 18px; border-radius: 2px; }

    .container { max-width: 1600px; margin: 0 auto; width: 95%; padding-bottom: 50px; }
    .layout-wrapper { display: flex; gap: 30px; align-items: flex-start; }
    .main-content { flex: 1; min-width: 0; }
    
    .article { background: var(--bg-card); padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .article-meta { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 15px; display: flex; gap: 15px; align-items: center; }
    .cat-badge { background: #e7f3ff; color: #1877f2; padding: 2px 8px; border-radius: 4px; font-weight: bold; font-size: 0.8rem; }
    .article-teaser { font-size: 1.25rem; line-height: 1.6; color: var(--text-main); margin: 20px 0 30px 0; padding: 20px; background: var(--bg-body); border-radius: 8px; border-left: 6px solid var(--primary); }
    .article-body { font-size: 1.1rem; line-height: 1.7; color: var(--text-main); }
    .article-body img { max-width: 100%; border-radius: 8px; }
    .article-body pre { background: #23241f !important; color: #f8f8f2 !important; padding: 15px; border-radius: 8px; overflow-x: auto; font-family: 'Fira Code', Consolas, monospace; margin: 20px 0; }
    
    .tag-container { margin-top: 30px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .tag-label { font-size: 0.9rem; font-weight: bold; color: var(--text-muted); margin-right: 5px; }
    .tag-badge { background: #edf2f7; color: var(--text-muted); text-decoration: none; padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 500; transition: all 0.2s; border: 1px solid transparent; }
    .tag-badge:hover { background: #e7f3ff; color: var(--primary); border-color: #bee3f8; transform: translateY(-1px); }

    .sidebar { width: 320px; flex-shrink: 0; position: sticky; top: 20px; display: flex; flex-direction: column; gap: 20px; }
    .sidebar-widget { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
    .sidebar-title { margin: 0 0 15px 0; font-size: 1.1rem; color: var(--primary); border-bottom: 2px solid #e7f3ff; padding-bottom: 8px; display: flex; align-items: center; gap: 8px; }
    
    .cat-list { list-style: none; padding: 0; margin: 0; }
    .cat-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--border); }
    .cat-item:last-child { border-bottom: none; }
    .cat-item a { text-decoration: none; color: var(--text-main); font-weight: 500; }
    .cat-item a:hover { color: var(--primary); }
    .cat-count { background: #e7f3ff; color: var(--primary); font-size: 0.8rem; font-weight: bold; padding: 2px 8px; border-radius: 10px; }

    .comm-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 15px; }
    .comm-item { display: flex; flex-direction: column; gap: 4px; font-size: 0.9rem; }
    .comm-meta { font-size: 0.8rem; color: var(--text-muted); display: flex; align-items: center; gap: 5px; }
    .comm-author { font-weight: bold; color: var(--text-main); }
    .comm-link { text-decoration: none; color: var(--primary); font-weight: 500; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .comm-text { font-style: italic; color: var(--text-muted); font-size: 0.85rem; border-left: 2px solid var(--border); padding-left: 8px; margin-top: 2px; }

    .comments-section { margin-top: 50px; border-top: 1px solid var(--border); padding-top: 30px; }
    .comment-item { background: var(--bg-body); padding: 15px; border-radius: 18px; margin-bottom: 10px; border: 1px solid transparent; }
    .comment-item.is-reply { margin-left: 50px; border: 1px solid #bbeeFF; background: #f8fbff; position: relative; }
    .comment-item.is-reply::before { content: "↪"; position: absolute; left: -25px; top: 15px; font-size: 20px; color: var(--primary); opacity: 0.5; }
    [data-theme="dark"] .comment-item.is-reply { background: #2a303c; border-color: #3e4c62; }
    
    .comment-author { font-weight: bold; color: var(--text-main); font-size: 0.9rem; }
    .comment-form { background: var(--bg-card); padding: 20px; border-radius: 12px; border: 1px solid var(--border); margin-top: 30px; }
    .btn-submit { background: var(--primary); color: white; padding: 10px 20px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; width: 100%; }

    #backToTop { position: fixed; bottom: 30px; right: 30px; z-index: 999; background: var(--primary); color: white; border: none; width: 50px; height: 50px; border-radius: 50%; font-size: 24px; cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.2); opacity: 0; pointer-events: none; transition: opacity 0.3s, transform 0.3s; display: flex; align-items: center; justify-content: center; }
    #backToTop.show { opacity: 1; pointer-events: all; }
    #backToTop:hover { transform: translateY(-3px); }

    @media (max-width: 1000px) {
        .sidebar { display: none !important; }
        .layout-wrapper { display: block; }
        .comment-item.is-reply { margin-left: 20px; }
    }
  </style>
</head>
<body>

  <nav class="top-nav">
    <div class="nav-container">
      <a href="/" class="brand"><?= htmlspecialchars($settings['blog_title'] ?? 'PiperBlog') ?></a>
      <div style="display:flex; gap:10px; align-items:center;">
        <a href="/contact.php" class="btn-contact"><?= htmlspecialchars($t['nav_contact']) ?></a>
        <div class="lang-dropdown" id="langDropdown">
            <div class="lang-trigger" onclick="toggleLang()">
                <img src="<?= $languages[$currentLang]['flag'] ?>" alt="<?= $currentLang ?>">
                <span><?= strtoupper($currentLang) ?></span>
                <span style="font-size: 10px;">▼</span>
            </div>
            <div class="lang-menu" id="langMenu">
                <?php foreach($languages as $code => $data): ?>
                    <a href="?lang=<?= $code ?>&id=<?= $id ?>" class="lang-option">
                        <img src="<?= $data['flag'] ?>" alt="<?= $code ?>">
                        <?= $data['label'] ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (($settings['dark_mode_enabled'] ?? '0') === '1'): ?>
            <button id="theme-toggle" class="theme-toggle">🌓</button>
        <?php endif; ?>
        <a href="/" class="btn-home"><?= htmlspecialchars($i18n->t('common.nav_home') ?? 'Home') ?></a>
      </div>
    </div>
  </nav>

  <main class="container">
    <div class="layout-wrapper">
        
        <section class="main-content">
            <article class="article">
              <header>
                <div class="article-meta">
                    <span>📅 <?= date('d.m.Y', strtotime($post['created_at'])) ?></span>
                    <span>• <?= htmlspecialchars($t_by) ?> <strong><?= htmlspecialchars($post['author_name'] ?? 'Admin') ?></strong></span>
                    <span>👁️ <?= number_format((int)($post['views'] ?? 0)) ?> <?= htmlspecialchars($t['stat_views']) ?></span>
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

              <?php if (!empty($postTags)): ?>
                <div class="tag-container">
                    <span class="tag-label">🏷️ <?= htmlspecialchars($t_tags) ?>:</span>
                    <?php foreach ($postTags as $tag): ?>
                        <a href="/index.php?tag=<?= htmlspecialchars($tag['slug']) ?>" class="tag-badge"><?= htmlspecialchars($tag['name']) ?></a>
                    <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <section class="comments-section">
                <h3><?= htmlspecialchars($i18n->t('common.comments_headline') ?? 'Comments') ?> (<?= count($comments) ?>)</h3>
                
                <?php if($msg): ?><div style="background:#d1e7dd; color:#0f5132; padding:15px; border-radius:6px; margin-bottom:20px;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
                <?php if($error): ?><div style="background:#f8d7da; color:#842029; padding:15px; border-radius:6px; margin-bottom:20px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

                <?php foreach ($comments as $c): 
                    $isReply = ($c['author_name'] === ($post['author_name'] ?? '')) || $c['author_name'] === 'Admin';
                ?>
                    <div style="margin-bottom: 15px;">
                        <div class="comment-item <?= $isReply ? 'is-reply' : '' ?>">
                            <div class="comment-author">
                                <?= htmlspecialchars($c['author_name']) ?>
                                <?php if($isReply): ?>
                                    <span style="font-size:0.7em; background:var(--primary); color:#fff; padding:2px 6px; border-radius:4px; margin-left:5px; font-weight:normal;">Team</span>
                                <?php endif; ?>
                            </div>
                            <div style="color: inherit;"><?= nl2br(htmlspecialchars($c['content'])) ?></div>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-left: <?= $isReply ? '62px' : '12px' ?>;"> 
                            <?= htmlspecialchars($i18n->t('common.comment_date_at') ?? 'at') ?> <?= date('d.m.Y', strtotime($c['created_at'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="comment-form">
                    <h4 style="margin-top:0;"><?= htmlspecialchars($i18n->t('common.write_comment_headline') ?? 'Einen Kommentar schreiben') ?></h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="comment">
                        <input type="text" name="name" placeholder="<?= htmlspecialchars($i18n->t('common.placeholder_name') ?? 'Dein Name') ?>" required style="width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box;">
                        <textarea name="content" rows="3" placeholder="<?= htmlspecialchars($i18n->t('common.placeholder_comment') ?? 'Dein Kommentar...') ?>" required style="width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box; resize: none;"></textarea>
                        
                        <div style="background: var(--bg-body); padding: 10px; border-radius: 6px; margin-bottom: 10px; font-size: 0.9rem; color:var(--text-main);">
                            <?= sprintf($i18n->t('common.spam_protection') ?? 'Spam-Schutz: Was ist %d + %d?', $_SESSION['spam_a'], $_SESSION['spam_b']) ?>
                            <input type="number" name="spam_ans" required style="width: 60px; padding: 5px; margin-left: 10px; border: 1px solid var(--border); border-radius: 4px;">
                        </div>
                        
                        <button type="submit" class="btn-submit"><?= htmlspecialchars($i18n->t('common.btn_submit_comment') ?? 'Kommentieren') ?></button>
                    </form>
                </div>
              </section>
            </article>
        </section>

        <aside class="sidebar">
            <div class="sidebar-widget">
                <h3 class="sidebar-title"><?= htmlspecialchars($sb_cat_title) ?></h3>
                <ul class="cat-list">
                    <?php foreach($cats as $cat): ?>
                    <li class="cat-item">
                        <a href="/index.php?category=<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></a>
                        <span class="cat-count"><?= $cat['count'] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="sidebar-widget">
                <h3 class="sidebar-title"><?= htmlspecialchars($sb_comm_title) ?></h3>
                <?php if(empty($latestComments)): ?>
                    <div style="color:var(--text-muted);font-size:0.9rem;"><?= htmlspecialchars($sb_no_comm) ?></div>
                <?php else: ?>
                    <ul class="comm-list">
                        <?php foreach($latestComments as $lc): ?>
                        <li class="comm-item">
                            <div class="comm-meta">
                                <span class="comm-author"><?= htmlspecialchars($lc['author_name']) ?></span>
                                <span>• <?= date($t['date_format'], strtotime($lc['created_at'])) ?></span>
                            </div>
                            <a href="/article.php?id=<?= $lc['post_id'] ?>#comments" class="comm-link">
                                “<?= htmlspecialchars($lc['title']) ?>”
                            </a>
                            <div class="comm-text">
                                <?= htmlspecialchars(mb_substr($lc['content'], 0, 80)) . '...' ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </aside>

    </div>
  </main>

  <footer style="text-align: center; padding: 40px 20px; color: var(--text-muted); font-size: 0.9rem; margin-top: 50px; border-top: 1px solid var(--border);">
    <div style="margin-bottom: 10px;">
        &copy; <?= date('Y') ?> <?= htmlspecialchars($settings['blog_title'] ?? 'PiperBlog') ?>
    </div>
    <div>
        📊 <?= htmlspecialchars($t['footer_total']) ?>: <strong><?= number_format($totalViews) ?></strong>
        &bull; 
        📅 <?= htmlspecialchars($t['footer_today']) ?>: <strong><?= number_format($todayViews) ?></strong>
    </div>
  </footer>

  <button id="backToTop" title="Nach oben">↑</button>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/dockerfile.min.js"></script>
  <script>
    hljs.highlightAll();

    function toggleLang() { document.getElementById('langMenu').classList.toggle('show'); }
    window.addEventListener('click', function(e) {
        if (!document.getElementById('langDropdown').contains(e.target)) {
            document.getElementById('langMenu').classList.remove('show');
        }
    });

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

    const backToTopBtn = document.getElementById('backToTop');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 300) backToTopBtn.classList.add('show');
        else backToTopBtn.classList.remove('show');
    });
    backToTopBtn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  </script>
</body>
</html>