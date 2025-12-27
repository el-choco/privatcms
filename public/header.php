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

if (!isset($iniLang)) $iniLang = [];

$l_forum   = $iniLang['forum']['title'] ?? 'Forum';
$l_login   = $iniLang['auth']['btn_login'] ?? 'Login';
$l_logout  = $iniLang['auth']['logout'] ?? 'Logout';
$l_profile = $iniLang['profile']['title'] ?? 'Profil';
$l_admin   = $t['admin'] ?? 'Admin';
$l_contact = $iniLang['frontend']['nav_contact'] ?? ($iniLang['nav']['contact'] ?? 'Kontakt');
// NEU: Wiki Variable definiert
$l_wiki    = $iniLang['wiki']['nav_title'] ?? 'Wiki';

$menuLinks = [];
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT * FROM menu_items ORDER BY position ASC");
        $menuLinks = $stmt->fetchAll();
    } catch (Exception $e) {}
}

$isLoggedIn = !empty($_SESSION['user_id']);
?>
<style>
.theme-switch-wrapper{display:flex;align-items:center}
.theme-switch{position:relative;display:inline-block;width:60px;height:30px;margin-bottom:0}
.theme-switch input{opacity:0;width:0;height:0}
.slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background-color: rgba(255, 255, 255, 0.2);transition:.4s;border-radius:34px}
.slider:before{position:absolute;content:"\f185";font-family:"Font Awesome 6 Free";font-weight:900;height:22px;width:22px;left:4px;bottom:4px;background-color:#fff;transition:.4s;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:15px;color:#FFD43B}
input:checked + .slider{background-color:rgba(255, 255, 255, 0.2);}
input:checked + .slider:before{transform:translateX(30px);content:"\f186";color:#4a5568}
</style>

<header class="site-header">
    <div class="header-container">
      <a href="/" class="site-title"><?= htmlspecialchars($settings['blog_title'] ?? 'PrivatCMS') ?></a>
      
      <div class="header-actions">
        
        <?php if (!empty($menuLinks)): ?>
            <?php foreach($menuLinks as $link): ?>
                <a href="<?= htmlspecialchars($link['link']) ?>" class="btn-nav">
                    <?php if (!empty($link['icon'])): ?>
                        <i class="<?= htmlspecialchars($link['icon']) ?>" style="margin-right: 5px;"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($link['label']) ?>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <a href="/contact.php" class="btn-nav">
                <i class="fa-solid fa-envelope" style="margin-right: 5px;"></i> <?= htmlspecialchars($l_contact) ?>
            </a>
        <?php endif; ?>
        
        <a href="/forum.php" class="btn-nav">
            <i class="fa-solid fa-comments"></i> <?= htmlspecialchars($l_forum) ?>
        </a>
        
        <a href="/wiki.php" class="btn-nav">
            <i class="fa-solid fa-book"></i> <?= htmlspecialchars($l_wiki) ?>
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
            <div class="theme-switch-wrapper">
                <label class="theme-switch" for="theme-toggle">
                    <input type="checkbox" id="theme-toggle">
                    <span class="slider"></span>
                </label>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'editor'])): ?>
            <a href="/admin/" class="btn-admin"><?= htmlspecialchars($l_admin) ?></a>
        <?php endif; ?>
      </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('theme-toggle');
    const currentTheme = document.documentElement.getAttribute('data-theme');
    
    if (toggle && currentTheme === 'dark') {
        toggle.checked = true;
    }
});
</script>