<?php
declare(strict_types=1);
session_start();

if (isset($_GET['lang']) && in_array($_GET['lang'], ['de', 'en', 'fr', 'es'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
$currentLang = $_SESSION['lang'] ?? 'de';

$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$iniLang = file_exists($langFile) ? parse_ini_file($langFile, true) : [];

$t = [
    'title_fallback' => $iniLang['frontend']['welcome_title'] ?? 'PiperBlog',
    'admin'          => $iniLang['frontend']['login_link'] ?? 'Admin',
    'toggle_theme'   => $iniLang['settings']['check_dark_mode'] ?? 'Theme',
    'sticky'         => $iniLang['posts']['tooltip_sticky'] ?? '📌',
    'read_more'      => $iniLang['frontend']['read_more'] ?? 'Read more',
    'no_image'       => $iniLang['post_edit']['no_image'] ?? 'No Image',
    'date_format'    => $iniLang['common']['date_fmt'] ?? 'd.m.Y',
    'sb_cat_title'   => $iniLang['categories']['title'] ?? 'Categories',
    'sb_comm_title'  => $iniLang['comments']['title'] ?? 'Comments',
    'sb_no_comm'     => $iniLang['comments']['no_comments'] ?? '-',
    'by'             => $iniLang['frontend']['by'] ?? 'by'
];

require_once __DIR__ . '/../src/App/Database.php';
use App\Database;

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED);
$db  = new Database($ini['database']);
$pdo = $db->pdo();
$pdo->exec("SET NAMES utf8mb4");

$settings = [];
try {
    foreach ($pdo->query("SELECT * FROM settings")->fetchAll() as $row) { 
        $settings[$row['setting_key']] = $row['setting_value']; 
    }
} catch (Exception $e) { }

$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$limit = (int)($settings['posts_per_page'] ?? 12);

$sql = 'SELECT p.id, p.title, p.excerpt, p.hero_image, p.created_at, p.is_sticky, c.name AS category, u.username AS author_name
        FROM posts p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN users u ON p.author_id = u.id
        WHERE p.status = "published"';

$params = [];

if ($categoryId > 0) {
    $sql .= ' AND p.category_id = ?';
    $params[] = $categoryId;
}

$sql .= ' ORDER BY p.is_sticky DESC, p.created_at DESC LIMIT ' . $limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

$cats = $pdo->query("SELECT c.id, c.name, COUNT(p.id) as count FROM categories c LEFT JOIN posts p ON p.category_id = c.id AND p.status = 'published' GROUP BY c.id ORDER BY c.name ASC")->fetchAll();
$latestComments = $pdo->query("SELECT c.content, c.author_name, c.created_at, p.id as post_id, p.title FROM comments c JOIN posts p ON c.post_id = p.id WHERE c.status = 'approved' ORDER BY c.created_at DESC LIMIT 5")->fetchAll();

$languages = [
    'de' => ['label' => 'Deutsch',  'flag' => 'https://flagcdn.com/w40/de.png'],
    'en' => ['label' => 'English',  'flag' => 'https://flagcdn.com/w40/gb.png'],
    'fr' => ['label' => 'Français', 'flag' => 'https://flagcdn.com/w40/fr.png'],
    'es' => ['label' => 'Español',  'flag' => 'https://flagcdn.com/w40/es.png']
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>" data-theme="light">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($settings['blog_title'] ?? $ini['app']['title'] ?? $t['title_fallback']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/assets/styles/main.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/monokai-sublime.min.css">
  <style>
    :root {
        --bg-body: #f0f2f5; --bg-card: #ffffff; --text-main: #1c1e21; --text-muted: #65676b;
        --border: #ccd0d5; --primary: #1877f2; --header-bg: #1877f2; --header-text: #ffffff;
        --bg-img: #ffffff; 
    }
    [data-theme="dark"] {
        --bg-body: #18191a; --bg-card: #242526; --text-main: #e4e6eb; --text-muted: #b0b3b8;
        --border: #3e4042; --primary: #2d88ff; --header-bg: #242526; --header-text: #e4e6eb;
        --bg-img: #ffffff; 
    }
    body { background-color: var(--bg-body); color: var(--text-main); margin: 0; font-family: -apple-system, sans-serif; transition: background 0.3s, color 0.3s; scroll-behavior: smooth; }
    
    .site-header { background-color: var(--header-bg); color: var(--header-text); padding: 12px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 15px; max-width: 1500px; margin-right: auto; margin-left: auto; border-radius: 6px; }
    .header-container { max-width: 1600px; margin: 0 auto; width: 95%; display: flex; justify-content: space-between; align-items: center; }
    .site-title { font-size: 24px; font-weight: bold; color: var(--header-text); text-decoration: none; }
    .header-actions { display: flex; align-items: center; gap: 15px; }
    .btn-admin { background-color: rgba(255,255,255,0.2); color: var(--header-text); text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 0.9rem; }
    .theme-toggle { background: none; border: 1px solid rgba(255,255,255,0.3); color: var(--header-text); padding: 6px 12px; border-radius: 20px; cursor: pointer; font-size: 1.2rem; }
    
    .lang-dropdown { position: relative; }
    .lang-trigger { display: flex; align-items: center; gap: 6px; cursor: pointer; background: rgba(255,255,255,0.15); padding: 6px 10px; border-radius: 6px; color: var(--header-text); font-weight: 600; font-size: 0.9rem; }
    .lang-trigger img { width: 20px; border-radius: 3px; }
    .lang-menu { display: none; position: absolute; right: 0; top: 120%; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); z-index: 1000; min-width: 150px; overflow: hidden; }
    .lang-menu.show { display: block; }
    .lang-option { display: flex; align-items: center; gap: 10px; padding: 10px 15px; color: var(--text-main); text-decoration: none; font-size: 0.95rem; }
    .lang-option:hover { background: var(--bg-body); color: var(--primary); }
    .lang-option img { width: 18px; border-radius: 2px; }

    .container { max-width: 1535px; margin: 0 auto; width: 95%; }
    .layout-wrapper { display: flex; gap: 30px; align-items: flex-start; }
    .main-content { flex: 1; min-width: 0; }
    .sidebar { width: 320px; flex-shrink: 0; position: sticky; top: 20px; display: flex; flex-direction: column; gap: 20px; }

    .posts-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 25px; margin-bottom: 50px; }
    .post-card { background: var(--bg-card); border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 2px 10px rgba(0,0,0,0.06); transition: transform 0.2s; border-top: 4px solid var(--border); }
    .post-card.is-sticky { border-top: 4px solid #e53e3e !important; }
    .post-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); border-top-color: var(--primary); }
    .post-card__media { width: 100%; height: 200px; background: var(--bg-img); display: flex; align-items: center; justify-content: center; overflow: hidden; border-bottom: 1px solid var(--border); }
    .post-card__media img { width: 100%; height: 100%; object-fit: contain; padding: 15px; box-sizing: border-box; }
    .post-card__content { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
    .post-card__title { margin: 0 0 10px 0; font-size: 1.3rem; line-height: 1.3; }
    .post-card__title a { text-decoration: none; color: var(--text-main); }
    .post-card__meta { margin-bottom: 12px; display: flex; align-items: center; gap: 10px; font-size: 0.85rem; flex-wrap: wrap; }
    .badge { background: #e7f3ff; color: #1877f2; padding: 3px 10px; border-radius: 5px; font-weight: bold; }
    .badge-sticky { background: #e53e3e; color: white; padding: 3px 10px; border-radius: 5px; font-weight: bold; }
    .date { color: var(--text-muted); }
    .author { color: var(--text-muted); display: flex; align-items: center; gap: 4px; }
    .post-card__excerpt { color: var(--text-main); opacity: 0.8; line-height: 1.5; margin-bottom: 20px; font-size: 0.95rem; }
    .post-card__cta { margin-top: auto; background: var(--bg-body); color: var(--primary); text-align: center; padding: 10px; border-radius: 6px; text-decoration: none; font-weight: bold; transition: background 0.2s; }
    .post-card__cta:hover { background: var(--primary); color: white; }

    @media (min-width: 1200px) {
        .post-card.highlight { grid-column: span 2; flex-direction: row; }
        .post-card.highlight .post-card__media { width: 50%; height: auto; border-bottom: none; border-right: 1px solid var(--border); }
        .post-card.highlight .post-card__content { width: 50%; padding: 40px; justify-content: center; }
        .post-card.highlight .post-card__title { font-size: 2rem; }
    }

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

    #backToTop { position: fixed; bottom: 30px; right: 30px; z-index: 999; background: var(--primary); color: white; border: none; width: 50px; height: 50px; border-radius: 50%; font-size: 24px; cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.2); opacity: 0; pointer-events: none; transition: opacity 0.3s, transform 0.3s; display: flex; align-items: center; justify-content: center; }
    #backToTop.show { opacity: 1; pointer-events: all; }
    #backToTop:hover { transform: translateY(-3px); }

    @media (max-width: 1000px) {
        .sidebar { display: none !important; }
        .layout-wrapper { display: block; }
    }
  </style>
</head>
<body>
  <header class="site-header">
    <div class="header-container">
      <a href="/" class="site-title"><?= htmlspecialchars($settings['blog_title'] ?? $ini['app']['title'] ?? $t['title_fallback']) ?></a>
      <div class="header-actions">
        <div class="lang-dropdown" id="langDropdown">
            <div class="lang-trigger" onclick="toggleLang()">
                <img src="<?= $languages[$currentLang]['flag'] ?>" alt="<?= $currentLang ?>">
                <span><?= strtoupper($currentLang) ?></span>
                <span style="font-size: 10px;">▼</span>
            </div>
            <div class="lang-menu" id="langMenu">
                <?php foreach($languages as $code => $data): ?>
                    <a href="?lang=<?= $code ?>" class="lang-option"><img src="<?= $data['flag'] ?>"> <?= $data['label'] ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if (($settings['dark_mode_enabled'] ?? '0') === '1'): ?>
            <button id="theme-toggle" class="theme-toggle" aria-label="<?= htmlspecialchars($t['toggle_theme']) ?>">🌓</button>
        <?php endif; ?>
        <a href="/admin/" class="btn-admin"><?= htmlspecialchars($t['admin']) ?></a>
      </div>
    </div>
  </header>

  <main class="container">
    <div class="layout-wrapper">
        <section class="main-content">
            <div class="posts-grid">
                <?php $i = 0; foreach ($posts as $p): $i++; ?>
                    <article class="post-card <?= (!empty($p['is_sticky'])) ? 'is-sticky' : '' ?> <?= ($i === 1) ? 'highlight' : '' ?>">
                    <a class="post-card__media" href="/article.php?id=<?= (int)$p['id'] ?>">
                        <?php if (!empty($p['hero_image'])): ?>
                        <img src="/uploads/<?= htmlspecialchars($p['hero_image']) ?>" alt="<?= htmlspecialchars($p['title']) ?>" loading="lazy">
                        <?php else: ?>
                        <div style="height:100%; display:flex; align-items:center; justify-content:center; color:#8b9dc3; font-weight:bold;"><?= htmlspecialchars($t['no_image']) ?></div>
                        <?php endif; ?>
                    </a>
                    <div class="post-card__content">
                        <div class="post-card__meta">
                        <?php if (!empty($p['is_sticky'])): ?><span class="badge-sticky"><?= htmlspecialchars($t['sticky']) ?></span><?php endif; ?>
                        <?php if (!empty($p['category'])): ?><span class="badge"><?= htmlspecialchars($p['category']) ?></span><?php endif; ?>
                        <span class="date"><?= date($t['date_format'], strtotime($p['created_at'])) ?></span>
                        <span class="author">
                             • <?= htmlspecialchars($t['by']) ?> 
                             <strong><?= htmlspecialchars($p['author_name'] ?? 'Admin') ?></strong>
                        </span>
                        </div>
                        <h2 class="post-card__title"><a href="/article.php?id=<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['title']) ?></a></h2>
                        <p class="post-card__excerpt"><?= htmlspecialchars($p['excerpt'] ?? '') ?></p>
                        <a class="post-card__cta" href="/article.php?id=<?= (int)$p['id'] ?>"><?= htmlspecialchars($t['read_more']) ?></a>
                    </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <aside class="sidebar">
            <div class="sidebar-widget">
                <h3 class="sidebar-title"><?= htmlspecialchars($t['sb_cat_title']) ?></h3>
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
                <h3 class="sidebar-title"><?= htmlspecialchars($t['sb_comm_title']) ?></h3>
                <?php if(empty($latestComments)): ?>
                    <div style="color:var(--text-muted);font-size:0.9rem;"><?= htmlspecialchars($t['sb_no_comm']) ?></div>
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