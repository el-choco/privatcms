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
    'title_fallback' => $iniLang['frontend']['welcome_title'] ?? 'PiperBlog',
    'admin'          => $iniLang['frontend']['login_link'] ?? 'Admin',
    'toggle_theme'   => $iniLang['settings']['check_dark_mode'] ?? 'Theme',
    'footer_total'   => $iniLang['frontend']['footer_stats_total'] ?? 'Total Visits',
    'footer_today'   => $iniLang['frontend']['footer_stats_today'] ?? 'Today',
    'nav_home'       => $iniLang['common']['nav_home'] ?? 'Home',
    'nav_contact'    => $iniLang['frontend']['nav_contact'] ?? 'Contact'
];

$db  = new Database($ini['database'] ?? []);
$pdo = $db->pdo();

$slug = $_GET['slug'] ?? '';
$page = false;

if ($slug) {
    $stmt = $pdo->prepare('SELECT * FROM pages WHERE slug=? AND status="published"');
    $stmt->execute([$slug]);
    $page = $stmt->fetch();
}

if (!$page) { http_response_code(404); echo "404 - Page not found"; exit; }

$parsedown = new Parsedown();
$parsedown->setSafeMode(false); 

$settings = [];
try {
    foreach ($pdo->query("SELECT * FROM settings")->fetchAll() as $row) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}

try {
    $totalViews = (int)$pdo->query("SELECT SUM(views) FROM daily_stats")->fetchColumn();
    $todayViews = (int)$pdo->query("SELECT views FROM daily_stats WHERE date = CURDATE()")->fetchColumn();
} catch (Exception $e) { $totalViews = 0; $todayViews = 0; }

$languages = [
    'de' => ['label' => 'Deutsch',  'flag' => 'https://flagcdn.com/w40/de.png'],
    'en' => ['label' => 'English',  'flag' => 'https://flagcdn.com/w40/gb.png'],
    'fr' => ['label' => 'Français', 'flag' => 'https://flagcdn.com/w40/fr.png'],
    'es' => ['label' => 'Español',  'flag' => 'https://flagcdn.com/w40/es.png']
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>" data-theme="<?= ($settings['dark_mode_enabled'] ?? '0') === '1' ? 'light' : 'light' ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($page['title']) ?> - <?= htmlspecialchars($settings['blog_title'] ?? 'PiperBlog') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link href="/assets/styles/main.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/monokai-sublime.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    :not(pre) > code{background-color:#23241f;color:#f8f8f2;padding:2px 6px;border-radius:4px;font-family:'Fira Code',Consolas,monospace;font-size:.9em;border:1px solid #3e3d32}
  </style>  
</head>
<body>
  
  <?php include 'header.php'; ?>

  <main class="container">
    <article class="page-card">
        <h1 class="page-title"><?= htmlspecialchars($page['title']) ?></h1>
        <div class="page-content">
            <?= $parsedown->text($page['content']) ?>
        </div>
    </article>
  </main>

  <?php include 'footer.php'; ?>

  <button id="backToTop">↑</button>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
  <script>
    hljs.highlightAll();
    
    function toggleLang() { 
        const el = document.getElementById('langMenu');
        if(el) el.classList.toggle('show'); 
    }
    window.addEventListener('click', function(e) {
        const dropdown = document.getElementById('langDropdown');
        if (dropdown && !dropdown.contains(e.target)) {
            const menu = document.getElementById('langMenu');
            if(menu) menu.classList.remove('show');
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
        backToTopBtn.classList.toggle('show', window.scrollY > 300);
    });
    backToTopBtn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  </script>
</body>
</html>