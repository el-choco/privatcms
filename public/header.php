<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($settings)) $settings = [];
if (!isset($t)) $t = [];
if (!isset($currentLang)) $currentLang = 'de';
if (!isset($languages)) $languages = [
    'de' => ['label' => 'Deutsch',  'flag' => 'https://flagcdn.com/w40/de.png'],
    'en' => ['label' => 'English',  'flag' => 'https://flagcdn.com/w40/gb.png'],
    'fr' => ['label' => 'Français', 'flag' => 'https://flagcdn.com/w40/fr.png'],
    'es' => ['label' => 'Español',  'flag' => 'https://flagcdn.com/w40/es.png']
];

$l_forum   = $iniLang['forum']['title'] ?? 'Forum';
$l_login   = $iniLang['auth']['btn_login'] ?? 'Login';
$l_logout  = $iniLang['auth']['logout'] ?? 'Logout';
$l_profile = $iniLang['profile']['title'] ?? 'Profile';
$l_admin   = $t['admin'] ?? 'Admin';

$menuLinks = [];
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT * FROM menu_items ORDER BY position ASC");
        $menuLinks = $stmt->fetchAll();
    } catch (Exception $e) {}
}

$isLoggedIn = !empty($_SESSION['user_id']);
?>
<header class="site-header">
    <div class="header-container">
      <a href="/" class="site-title"><?= htmlspecialchars($settings['blog_title'] ?? 'PiperBlog') ?></a>
      <div class="header-actions">
        
        <?php if (!empty($menuLinks)): ?>
            <?php foreach($menuLinks as $link): ?>
                <a href="<?= htmlspecialchars($link['link']) ?>" class="btn-nav"><?= htmlspecialchars($link['label']) ?></a>
            <?php endforeach; ?>
        <?php else: ?>
            <a href="/" class="btn-nav">Home</a>
            <a href="/contact.php" class="btn-nav">Contact</a>
        <?php endif; ?>
        
        <a href="/forum.php" class="btn-nav">
            <i class="fa-solid fa-comments"></i> <?= htmlspecialchars($l_forum) ?>
        </a>

        <?php if ($isLoggedIn): ?>
            <a href="/profile.php" class="btn-nav">
                <i class="fa-solid fa-id-card"></i> <?= htmlspecialchars($l_profile) ?>
            </a>
            <a href="/logout.php" class="btn-nav" style="background: rgba(220, 38, 38, 0.2); color: #fecaca;">
                <i class="fa-solid fa-right-from-bracket"></i> <?= htmlspecialchars($l_logout) ?>
            </a>
        <?php else: ?>
            <a href="/login.php" class="btn-nav">
                <i class="fa-solid fa-user"></i> <?= htmlspecialchars($l_login) ?>
            </a>
        <?php endif; ?>
        
        <div class="lang-dropdown" id="langDropdown">
            <div class="lang-trigger" onclick="toggleLang()">
                <img src="<?= $languages[$currentLang]['flag'] ?? '' ?>" alt="<?= $currentLang ?>">
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
            <button id="theme-toggle" class="theme-toggle" aria-label="Theme">🌓</button>
        <?php endif; ?>
        
        <a href="/admin/" class="btn-admin"><?= htmlspecialchars($l_admin) ?></a>
      </div>
    </div>
</header>