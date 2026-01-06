<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentLang = $_SESSION['lang'] ?? 'de';

if (!empty($_SESSION['timezone'])) {
    date_default_timezone_set($_SESSION['timezone']);
}

$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$t = file_exists($langFile) ? parse_ini_file($langFile, true) : [];

if (!empty($_SESSION['date_fmt'])) {
    $t['common']['date_fmt'] = $_SESSION['date_fmt'];
    $t['common']['date_fmt_full'] = $_SESSION['date_fmt'] . ' H:i'; 
}

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$current_page = basename($_SERVER['PHP_SELF']);

$userRole = $_SESSION['admin']['role'] ?? 'viewer';
$isAdmin = $userRole === 'admin';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(($ini['app']['name'] ?? 'Blog') . ' - ' . ($t['admin_login']['title'] ?? 'Admin')) ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap">
    <style>
        body, html { margin: 0; padding: 0; height: 100vh; overflow: hidden; font-family: 'Inter', sans-serif; background: #f7fafc; }
        .admin-wrapper { display: flex; height: 100vh; width: 100vw; }
        
        .admin-sidebar { width: 260px; background: #1a202c; color: #fff; flex-shrink: 0; display: flex; flex-direction: column; }
        .sidebar-brand { padding: 25px 20px; font-size: 1.5rem; font-weight: bold; border-bottom: 1px solid #2d3748; color: #63b3ed; }
        
        .admin-sidebar nav { flex: 1; padding: 15px 0; overflow-y: auto; }
        .admin-sidebar nav a { display: block; padding: 12px 20px; color: #cbd5e0; text-decoration: none; transition: 0.2s; border-left: 3px solid transparent; }
        .admin-sidebar nav a:hover, .admin-sidebar nav a.active { background: #2d3748; color: #fff; border-left-color: #63b3ed; }
        
        .sidebar-footer { padding: 20px; border-top: 1px solid #2d3748; }

        .main-viewport { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .top-header { background: #fff; padding: 20px 30px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; min-height: 70px; box-sizing: border-box; }
        .top-header h1 { margin: 0; font-size: 1.4rem; color: #2d3748; }
        .content-area { flex: 1; padding: 30px; overflow-y: auto; }

        .card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
        .btn { padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; border: 1px solid #e2e8f0; background: #fff; transition: 0.2s; color: #4a5568; }
        .btn-primary { background: #3182ce; color: #fff; border: none; }
        .btn-primary:hover { background: #2b6cb0; }
        
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; color: #4a5568; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
        .alert-success { background: #c6f6d5; color: #276749; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #9ae6b4; }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <aside class="admin-sidebar">
        <div class="sidebar-brand"><?= htmlspecialchars($ini['app']['name'] ?? 'Admin') ?></div>
        
        <nav>
            <a href="dashboard.php" class="<?= $current_page=='dashboard.php'?'active':'' ?>">
                <?= htmlspecialchars($t['dashboard']['title'] ?? 'Dashboard') ?>
            </a>
            <a href="posts.php" class="<?= ($current_page=='posts.php' || $current_page=='post-create.php' || $current_page=='post-edit.php')?'active':'' ?>">
                <?= htmlspecialchars($t['posts']['title'] ?? 'Beiträge') ?>
            </a>
            <a href="files.php" class="<?= $current_page=='files.php'?'active':'' ?>">
                <?= htmlspecialchars($t['files']['title'] ?? 'Dateien') ?>
            </a>
            
            <?php if ($isAdmin): ?>
            <a href="categories.php" class="<?= $current_page=='categories.php'?'active':'' ?>">
                <?= htmlspecialchars($t['categories']['title'] ?? 'Kategorien') ?>
            </a>
            <a href="comments.php" class="<?= $current_page=='comments.php'?'active':'' ?>">
                <?= htmlspecialchars($t['comments']['title'] ?? 'Kommentare') ?>
            </a>
            <a href="users.php" class="<?= ($current_page=='users.php' || $current_page=='user-edit.php')?'active':'' ?>">
                <?= htmlspecialchars($t['users']['title'] ?? 'Benutzer') ?>
            </a>
            <a href="activity-log.php" class="<?= $current_page=='activity-log.php'?'active':'' ?>">
                <?= htmlspecialchars($t['activity_log']['title'] ?? 'Logbuch') ?>
            </a>
            
            <a href="change-language.php" class="<?= $current_page=='change-language.php'?'active':'' ?>">
                <?= htmlspecialchars($t['language_settings']['title'] ?? 'Sprache & Region') ?>
            </a>

            <a href="settings.php" class="<?= $current_page=='settings.php'?'active':'' ?>">
                <?= htmlspecialchars($t['settings']['title'] ?? 'Einstellungen') ?>
            </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <a href="logout.php" style="color: #fc8181; text-decoration: none; font-size: 14px; display: flex; align-items: center; gap: 8px;">
                <?= htmlspecialchars($t['common']['logout'] ?? 'Abmelden') ?>
                <span style="font-size: 12px; opacity: 0.7;">(<?= htmlspecialchars($_SESSION['admin']['username'] ?? 'User') ?>)</span>
            </a>
        </div>
    </aside>
    <main class="main-viewport">