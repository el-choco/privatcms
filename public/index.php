<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../src/App/Database.php';
if (file_exists(__DIR__ . '/../src/App/MailService.php')) {
    require_once __DIR__ . '/../src/App/MailService.php';
}

use App\Database;

$currentLang = $_SESSION['lang'] ?? 'de';
$i18n = [
    'de' => [
        'title_fallback' => 'PiperBlog',
        'admin' => 'Admin-Bereich',
        'toggle_theme' => 'Dark Mode umschalten',
        'sticky' => '📌 Fixiert',
        'read_more' => 'Weiterlesen',
        'no_image' => 'Kein Bild',
        'date_format' => 'd.m.Y'
    ],
    'en' => [
        'title_fallback' => 'PiperBlog',
        'admin' => 'Admin Area',
        'toggle_theme' => 'Toggle Dark Mode',
        'sticky' => '📌 Pinned',
        'read_more' => 'Read more',
        'no_image' => 'No Image',
        'date_format' => 'Y-m-d'
    ]
];
$t = $i18n[$currentLang] ?? $i18n['de'];

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

$stmt = $pdo->query('
  SELECT p.id, p.title, p.excerpt, p.hero_image, p.created_at, p.is_sticky, c.name AS category
  FROM posts p
  LEFT JOIN categories c ON c.id = p.category_id
  WHERE p.status = "published"
  ORDER BY p.is_sticky DESC, p.created_at DESC
  LIMIT ' . (int)($settings['posts_per_page'] ?? 12)
);
$posts = $stmt->fetchAll();
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
        --bg-body: #f0f2f5;
        --bg-card: #ffffff;
        --text-main: #1c1e21;
        --text-muted: #65676b;
        --border: #ccd0d5;
        --primary: #1877f2;
        --header-bg: #1877f2;
        --header-text: #ffffff;
        --bg-img: #ffffff; 
    }
    [data-theme="dark"] {
        --bg-body: #18191a;
        --bg-card: #242526;
        --text-main: #e4e6eb;
        --text-muted: #b0b3b8;
        --border: #3e4042;
        --primary: #2d88ff;
        --header-bg: #242526;
        --header-text: #e4e6eb;
        --bg-img: #ffffff; 
    }
    body { background-color: var(--bg-body); color: var(--text-main); margin: 0; font-family: -apple-system, sans-serif; transition: background 0.3s, color 0.3s; }
    .site-header { background-color: var(--header-bg); color: var(--header-text); padding: 12px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); width: 100%; margin-bottom: 30px; transition: background 0.3s; }
    .header-container { max-width: 1200px; margin: 0 auto; width: 95%; display: flex; justify-content: space-between; align-items: center; }
    .site-title { margin: 0; font-size: 24px; font-weight: bold; color: var(--header-text); text-decoration: none; }
    .header-actions { display: flex; align-items: center; gap: 15px; }
    .btn-admin { background-color: rgba(255,255,255,0.2); color: var(--header-text); text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; }
    .theme-toggle { background: none; border: 1px solid rgba(255,255,255,0.3); color: var(--header-text); padding: 6px 12px; border-radius: 20px; cursor: pointer; font-size: 1.2rem; }
    .theme-toggle:hover { background: rgba(255,255,255,0.1); }
    .container { max-width: 1200px; margin: 0 auto; width: 95%; }
    .posts-grid { display: flex; flex-wrap: wrap; gap: 25px; margin-bottom: 50px; }
    .post-card:first-child { flex: 1 1 100%; display: flex; flex-direction: row; min-height: 400px; border-top: 6px solid var(--primary); }
    .post-card:first-child .post-card__media { width: 60%; height: auto; background: var(--bg-img); }
    .post-card:first-child .post-card__content { width: 40%; padding: 40px; display: flex; flex-direction: column; justify-content: center; }
    .post-card:first-child .post-card__title { font-size: 2.2rem; }
    .post-card:first-child .post-card__media img { object-fit: cover; padding: 0; }
    .post-card { flex: 1 1 calc(33.333% - 25px); background: var(--bg-card); border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 2px 10px rgba(0,0,0,0.06); transition: transform 0.2s, box-shadow 0.2s; border-top: 4px solid var(--border); }
    .post-card.is-sticky { border-top: 4px solid #e53e3e !important; background-color: var(--bg-card); position: relative; }
    .post-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); border-top-color: var(--primary); }
    .post-card.is-sticky:hover { border-top-color: #e53e3e; }
    .post-card__media { width: 100%; height: 200px; background: var(--bg-img); display: flex; align-items: center; justify-content: center; overflow: hidden; border-bottom: 1px solid var(--border); }
    .post-card__media img { width: 100%; height: 100%; object-fit: contain; padding: 15px; box-sizing: border-box; transition: transform 0.5s; }
    .post-card:hover .post-card__media img { transform: scale(1.05); }
    .post-card__content { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
    .post-card__title { margin: 0 0 10px 0; font-size: 1.3rem; line-height: 1.3; }
    .post-card__title a { text-decoration: none; color: var(--text-main); }
    .post-card__meta { margin-bottom: 12px; display: flex; align-items: center; gap: 10px; font-size: 0.85rem; }
    .badge { background: #e7f3ff; color: #1877f2; padding: 3px 10px; border-radius: 5px; font-weight: bold; }
    .badge-sticky { background: #e53e3e; color: white; padding: 3px 10px; border-radius: 5px; font-weight: bold; }
    .date { color: var(--text-muted); }
    .post-card__excerpt { color: var(--text-main); opacity: 0.8; line-height: 1.5; margin-bottom: 20px; font-size: 0.95rem; }
    .post-card__cta { margin-top: auto; background: var(--bg-body); color: var(--primary); text-align: center; padding: 10px; border-radius: 6px; text-decoration: none; font-weight: bold; transition: background 0.2s; }
    .post-card__cta:hover { background: var(--primary); color: white; }
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
      <a href="/" class="site-title"><?= htmlspecialchars($settings['blog_title'] ?? 'PiperBlog') ?></a>
      
      <div class="header-actions">
        <?php if (($settings['dark_mode_enabled'] ?? '0') === '1'): ?>
            <button id="theme-toggle" class="theme-toggle" aria-label="<?= htmlspecialchars($t['toggle_theme']) ?>">🌓</button>
        <?php endif; ?>
        
        <a href="/admin/" class="btn-admin"><?= htmlspecialchars($t['admin']) ?></a>
      </div>
    </div>
  </header>

  <main class="container">
    <section class="posts-grid">
      <?php foreach ($posts as $p): ?>
        <article class="post-card <?= !empty($p['is_sticky']) ? 'is-sticky' : '' ?>">
          <a class="post-card__media" href="/article.php?id=<?= (int)$p['id'] ?>">
            <?php if (!empty($p['hero_image'])): ?>
              <img src="/uploads/<?= htmlspecialchars($p['hero_image']) ?>" alt="<?= htmlspecialchars($p['title']) ?>" loading="lazy">
            <?php else: ?>
              <div style="height:100%; display:flex; align-items:center; justify-content:center; color:#8b9dc3; font-weight:bold;"><?= htmlspecialchars($t['no_image']) ?></div>
            <?php endif; ?>
          </a>
          <div class="post-card__content">
            <div class="post-card__meta">
              <?php if (!empty($p['is_sticky'])): ?>
                  <span class="badge-sticky"><?= htmlspecialchars($t['sticky']) ?></span>
              <?php endif; ?>
              
              <?php if (!empty($p['category'])): ?><span class="badge"><?= htmlspecialchars($p['category']) ?></span><?php endif; ?>
              <span class="date"><?= date($t['date_format'], strtotime($p['created_at'])) ?></span>
            </div>
            <h2 class="post-card__title"><a href="/article.php?id=<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['title']) ?></a></h2>
            <p class="post-card__excerpt"><?= htmlspecialchars($p['excerpt'] ?? '') ?></p>
            <a class="post-card__cta" href="/article.php?id=<?= (int)$p['id'] ?>"><?= htmlspecialchars($t['read_more']) ?></a>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
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
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        });
    }
  </script>
</body>
</html>