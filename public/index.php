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
    'by'             => $iniLang['frontend']['by'] ?? 'by',
    'sb_search_title'=> $iniLang['frontend']['search_title'] ?? 'Search',
    'search_ph'      => $iniLang['frontend']['search_placeholder'] ?? 'Search...',
    'no_results'     => $iniLang['frontend']['search_no_results'] ?? 'No posts found.',
    'search_btn'     => $iniLang['frontend']['search_button'] ?? 'Go',
    'page_prev'      => $iniLang['frontend']['pagination_prev'] ?? '«',
    'page_next'      => $iniLang['frontend']['pagination_next'] ?? '»',
    'sb_tags_title'  => $iniLang['frontend']['tags_title'] ?? 'Tags',
    'footer_total'   => $iniLang['frontend']['footer_stats_total'] ?? 'Total Visits',
    'footer_today'   => $iniLang['frontend']['footer_stats_today'] ?? 'Today',
    'nav_contact'    => $iniLang['frontend']['nav_contact'] ?? 'Contact'
];

require_once __DIR__ . '/../src/App/Database.php';
use App\Database;

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED);
$db  = new Database($ini['database']);
$pdo = $db->pdo();
$pdo->exec("SET NAMES utf8mb4");

$today = date('Y-m-d');
if (!isset($_SESSION['viewed_index_' . $today])) {
    try {
        $pdo->prepare("INSERT INTO daily_stats (date, views) VALUES (?, 1) ON DUPLICATE KEY UPDATE views = views + 1")->execute([$today]);
        $_SESSION['viewed_index_' . $today] = true;
    } catch (Exception $e) {}
}

try {
    $totalViews = (int)$pdo->query("SELECT SUM(views) FROM daily_stats")->fetchColumn();
    $todayViews = (int)$pdo->query("SELECT views FROM daily_stats WHERE date = CURDATE()")->fetchColumn();
} catch (Exception $e) { $totalViews = 0; $todayViews = 0; }

$settings = [];
try {
    foreach ($pdo->query("SELECT * FROM settings")->fetchAll() as $row) { 
        $settings[$row['setting_key']] = $row['setting_value']; 
    }
} catch (Exception $e) { }

$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$tagSlug = isset($_GET['tag']) ? trim($_GET['tag']) : '';
$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = (int)($settings['posts_per_page'] ?? 12);
$offset = ($page - 1) * $limit;

$sqlBase = ' FROM posts p 
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN users u ON p.author_id = u.id ';

$where = ' WHERE p.status = "published" ';
$params = [];

if ($tagSlug !== '') {
    $sqlBase .= ' JOIN post_tags pt ON p.id = pt.post_id JOIN tags t ON pt.tag_id = t.id ';
    $where .= ' AND t.slug = ? ';
    $params[] = $tagSlug;
}

if ($categoryId > 0) {
    $where .= ' AND p.category_id = ? ';
    $params[] = $categoryId;
}

if ($searchQuery !== '') {
    $where .= ' AND (p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ?) ';
    $like = '%' . $searchQuery . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$countStmt = $pdo->prepare('SELECT COUNT(DISTINCT p.id) ' . $sqlBase . $where);
$countStmt->execute($params);
$totalPosts = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($totalPosts / $limit);

$sql = 'SELECT DISTINCT p.id, p.title, p.slug, p.excerpt, p.hero_image, p.created_at, p.is_sticky, c.name AS category, c.color AS category_color, u.username AS author_name ' . 
        $sqlBase . $where . 
        ' ORDER BY p.is_sticky DESC, p.created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

$cats = $pdo->query("SELECT c.id, c.name, COUNT(p.id) as count FROM categories c LEFT JOIN posts p ON p.category_id = c.id AND p.status = 'published' GROUP BY c.id ORDER BY c.name ASC")->fetchAll();
$allTags = $pdo->query("SELECT t.name, t.slug, COUNT(pt.post_id) as count FROM tags t JOIN post_tags pt ON t.id = pt.tag_id GROUP BY t.id ORDER BY count DESC")->fetchAll();

$latestComments = $pdo->query("SELECT c.content, c.author_name, c.created_at, p.id as post_id, p.slug as post_slug, p.title FROM comments c JOIN posts p ON c.post_id = p.id WHERE c.status = 'approved' ORDER BY c.created_at DESC LIMIT 5")->fetchAll();

$languages = [
    'de' => ['label' => 'Deutsch',  'flag' => 'https://flagcdn.com/w40/de.png'],
    'en' => ['label' => 'English',  'flag' => 'https://flagcdn.com/w40/gb.png'],
    'fr' => ['label' => 'Français', 'flag' => 'https://flagcdn.com/w40/fr.png'],
    'es' => ['label' => 'Español',  'flag' => 'https://flagcdn.com/w40/es.png']
];

function buildUrl($newPage) {
    $params = $_GET;
    $params['page'] = $newPage;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>" data-theme="light">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($settings['blog_title'] ?? $ini['app']['title'] ?? $t['title_fallback']) ?></title>
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
            <?php if (!empty($tagSlug)): ?>
                <div style="margin-bottom: 20px; font-size: 1.2rem; color: var(--text-muted);">
                    🏷️ <?= htmlspecialchars($t['sb_tags_title']) ?>: <strong><?= htmlspecialchars($tagSlug) ?></strong>
                    <a href="/" style="font-size: 0.8rem; margin-left: 10px; text-decoration: none; color: var(--primary);">[x]</a>
                </div>
            <?php endif; ?>

            <div class="posts-grid">
                <?php if (empty($posts)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 50px; color: var(--text-muted); font-size: 1.1rem; background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border);">
                        <?= htmlspecialchars($t['no_results']) ?>
                    </div>
                <?php else: ?>
                    <?php $i = 0; foreach ($posts as $p): $i++; ?>
                        <article class="post-card <?= (!empty($p['is_sticky'])) ? 'is-sticky' : '' ?> <?= ($i === 1 && $searchQuery === '' && $categoryId === 0 && $page === 1 && $tagSlug === '') ? 'highlight' : '' ?>">
                        <a class="post-card__media" href="/artikel/<?= htmlspecialchars($p['slug'] ?: $p['id']) ?>">
                            <?php if (!empty($p['hero_image'])): ?>
                            <img src="/uploads/<?= htmlspecialchars($p['hero_image']) ?>" alt="<?= htmlspecialchars($p['title']) ?>" loading="lazy">
                            <?php else: ?>
                            <div style="height:100%; display:flex; align-items:center; justify-content:center; color:#8b9dc3; font-weight:bold;"><?= htmlspecialchars($t['no_image']) ?></div>
                            <?php endif; ?>
                        </a>
                        <div class="post-card__content">
                            <div class="post-card__meta">
                            <?php if (!empty($p['is_sticky'])): ?><span class="badge-sticky"><?= htmlspecialchars($t['sticky']) ?></span><?php endif; ?>
                            <?php if (!empty($p['category'])): ?><span class="cat-badge" style="background-color: <?= htmlspecialchars($p['category_color'] ?? '#3182ce') ?>"><?= htmlspecialchars($p['category']) ?></span><?php endif; ?>
                            <span class="date"><?= date($t['date_format'], strtotime($p['created_at'])) ?></span>
                            <span class="author">
                                • <?= htmlspecialchars($t['by']) ?> 
                                <strong><?= htmlspecialchars($p['author_name'] ?? 'Admin') ?></strong>
                            </span>
                            </div>
                            <h2 class="post-card__title"><a href="/artikel/<?= htmlspecialchars($p['slug'] ?: $p['id']) ?>"><?= htmlspecialchars($p['title']) ?></a></h2>
                            <p class="post-card__excerpt"><?= htmlspecialchars($p['excerpt'] ?? '') ?></p>
                            <a class="post-card__cta" href="/artikel/<?= htmlspecialchars($p['slug'] ?: $p['id']) ?>"><?= htmlspecialchars($t['read_more']) ?></a>
                        </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?= buildUrl($page - 1) ?>" class="page-link"><?= htmlspecialchars($t['page_prev']) ?></a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="<?= buildUrl($i) ?>" class="page-link <?= ($i === $page) ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?= buildUrl($page + 1) ?>" class="page-link"><?= htmlspecialchars($t['page_next']) ?></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </section>

        <aside class="sidebar">
            <div class="sidebar-widget">
                <h3 class="sidebar-title">🔍 <?= htmlspecialchars($t['sb_search_title']) ?></h3>
                <form action="/index.php" method="get" style="display:flex; gap:8px;">
                    <input type="text" name="q" placeholder="<?= htmlspecialchars($t['search_ph']) ?>" value="<?= htmlspecialchars($searchQuery) ?>" style="flex:1; padding:8px 12px; border:1px solid var(--border); border-radius:6px; background:var(--bg-body); color:var(--text-main); font-family:inherit;">
                    <button type="submit" style="background:var(--primary); color:white; border:none; padding:8px 12px; border-radius:6px; cursor:pointer; font-weight:bold;"><?= htmlspecialchars($t['search_btn']) ?></button>
                </form>
            </div>

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

            <?php if(!empty($allTags)): ?>
            <div class="sidebar-widget">
                <h3 class="sidebar-title">🏷️ <?= htmlspecialchars($t['sb_tags_title']) ?></h3>
                <div class="tag-cloud">
                    <?php foreach($allTags as $tag): ?>
                        <a href="/index.php?tag=<?= htmlspecialchars($tag['slug']) ?>" class="tag-item">
                            <?= htmlspecialchars($tag['name']) ?>
                            <span class="count">(<?= $tag['count'] ?>)</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

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