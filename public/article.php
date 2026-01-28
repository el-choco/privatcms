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
    'nav_contact'   => $iniLang['frontend']['nav_contact'] ?? 'Contact',
    'admin'         => $iniLang['frontend']['login_link'] ?? 'Admin'
];

$sb_cat_title = $t['sb_cat_title'];
$sb_comm_title = $t['sb_comm_title'];
$sb_no_comm = $t['sb_no_comm'];
$t_by = $iniLang['frontend']['by'] ?? 'by';
$t_tags = $t['sb_tags_title'];

$i18n = I18n::fromConfig($ini, $_GET['lang'] ?? null);
$db  = new Database($ini['database'] ?? []);
$pdo = $db->pdo();

$slug = $_GET['slug'] ?? null;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$post = false;

if ($slug) {
    $stmt = $pdo->prepare('SELECT p.*, c.name as category_name, c.color as category_color, u.username as author_name FROM posts p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN users u ON p.author_id = u.id WHERE p.slug=? AND p.status="published"');
    $stmt->execute([$slug]);
    $post = $stmt->fetch();
    if ($post) $id = $post['id']; 
} elseif ($id > 0) {
    $stmt = $pdo->prepare('SELECT p.*, c.name as category_name, c.color as category_color, u.username as author_name FROM posts p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN users u ON p.author_id = u.id WHERE p.id=? AND p.status="published"');
    $stmt->execute([$id]);
    $post = $stmt->fetch();
}

if (!$post) { http_response_code(404); echo "Not found"; exit; }

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

$stmtTags = $pdo->prepare("SELECT t.name, t.slug FROM tags t JOIN post_tags pt ON t.id = pt.tag_id WHERE pt.post_id = ? ORDER BY t.name ASC");
$stmtTags->execute([$id]);
$postTags = $stmtTags->fetchAll();

$cats = $pdo->query("SELECT c.id, c.name, COUNT(p.id) as count FROM categories c LEFT JOIN posts p ON p.category_id = c.id AND p.status = 'published' GROUP BY c.id ORDER BY c.name ASC")->fetchAll();

$latestComments = $pdo->query("SELECT c.content, c.author_name, c.created_at, p.id as post_id, p.slug as post_slug, p.title FROM comments c JOIN posts p ON c.post_id = p.id WHERE c.status = 'approved' ORDER BY c.created_at DESC LIMIT 5")->fetchAll();

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
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link href="/assets/styles/main.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/monokai-sublime.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    .comm-list { display: block !important; gap: 0 !important; }
    .comm-item { 
        padding: 12px 0; 
        border-bottom: 1px solid var(--border); 
        display: flex; 
        flex-direction: column; 
        gap: 4px;
    }
    .comm-item:last-child { border-bottom: none; }
    :not(pre) > code{background-color:#23241f;color:#f8f8f2;padding:2px 6px;border-radius:4px;font-family:'Fira Code',Consolas,monospace;font-size:.9em;border:1px solid #3e3d32}
  </style>
</head>
<body>

  <?php include 'header.php'; ?>

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
                        <span class="cat-badge" style="background-color: <?= htmlspecialchars($post['category_color'] ?? '#3182ce') ?>"><?= htmlspecialchars($post['category_name']) ?></span>
                    <?php endif; ?>
                </div>
                <h1 style="font-size: 2.5rem; margin: 0 0 20px 0; color: inherit;"><?= htmlspecialchars($post['title']) ?></h1>
              </header>
              
              <?php if (!empty($post['hero_image'])): ?>
                <img src="/uploads/<?= htmlspecialchars($post['hero_image']) ?>"style="display: block; margin: auto; width: 75%;">
              <?php endif; ?>

              <?php if (!empty($post['excerpt'])): ?>
                <div class="article-teaser" style="text-align: center;text-decoration: underline;margin-top: 50px;margin-bottom: 50px;">
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
                            <a href="/artikel/<?= htmlspecialchars($lc['post_slug'] ?: $lc['post_id']) ?>#comments" class="comm-link">
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

  <?php include 'footer.php'; ?>

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